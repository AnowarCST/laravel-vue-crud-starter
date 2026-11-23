<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductIndexRequest;
use App\Http\Requests\Products\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\Tag;
use App\Support\ImageReencoder;
use App\Support\UnreadableImageException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductController extends Controller
{
    /**
     * Paginated product list with optional search, filters and sorting; see
     * ProductIndexRequest for the accepted query parameters.
     */
    public function index(ProductIndexRequest $request): ApiResponse
    {
        $query = Product::query()->with('category', 'tags');

        if (($search = $request->filterText('search')) !== null) {
            $this->search($query, $search);
        }
        if (($categoryId = $request->filterInt('category_id')) !== null) {
            $query->where('category_id', $categoryId);
        }
        if (($tagId = $request->filterInt('tag')) !== null) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->where('tags.id', $tagId));
        }
        if (($status = $request->filterText('status')) !== null) {
            $query->where('status', $status);
        }
        match ($request->filterText('stock')) {
            'in' => $query->where('stock', '>', 0),
            'low' => $query->whereBetween('stock', [1, Product::LOW_STOCK_THRESHOLD]),
            'out' => $query->where('stock', 0),
            default => null,
        };

        [$column, $direction] = $request->sort();
        // The id breaks ties (seeded rows share a created_at value) so pages
        // do not overlap.
        $query->orderBy($column, $direction)->orderBy('id', $direction);

        $products = $query->paginate($request->perPage())->withQueryString()
            ->through(fn (Product $product) => new ProductResource($product));

        return ApiResponse::success($products, 'Product list');
    }

    /**
     * Counts for the dashboard. Low stock is 1 to Product::LOW_STOCK_THRESHOLD
     * units; out of stock is 0.
     */
    public function stats(): ApiResponse
    {
        $byStatus = Product::query()->toBase()
            ->select('status')->selectRaw('count(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        $count = fn (string $status): int => is_numeric($byStatus[$status] ?? null) ? (int) $byStatus[$status] : 0;

        return ApiResponse::success([
            'total' => Product::count(),
            'active' => $count('active'),
            'draft' => $count('draft'),
            'archived' => $count('archived'),
            'low_stock' => Product::whereBetween('stock', [1, Product::LOW_STOCK_THRESHOLD])->count(),
            'out_of_stock' => Product::where('stock', 0)->count(),
        ], 'Product statistics');
    }

    /**
     * Case-insensitive match on name, SKU or description. "%" and "_" in the
     * search text are matched literally ("!" is the LIKE escape character,
     * which behaves the same on MySQL and SQLite).
     *
     * @param  Builder<Product>  $query
     */
    private function search(Builder $query, string $search): void
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

        $query->where(function (Builder $where) use ($pattern) {
            foreach (['name', 'sku', 'description'] as $column) {
                $where->orWhereRaw("{$column} like ? escape '!'", [$pattern]);
            }
        });
    }

    public function show(string $id): ApiResponse
    {
        return ApiResponse::success(new ProductResource(Product::with('category', 'tags')->findOrFail($id)), 'Product Details');
    }

    public function store(ProductRequest $request): ApiResponse
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('tags'));
            $this->syncTags($product, $request->tagNames());

            return $product;
        });

        return ApiResponse::success(new ProductResource($product), 'Product Created Successfully');
    }

    public function update(ProductRequest $request, string $id): ApiResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);
        $previousPhoto = $product->photo;

        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('tags'));
            if ($request->has('tags')) {
                $this->syncTags($product, $request->tagNames());
            }
        });

        // Remove the replaced upload, but only files this API created.
        if ($previousPhoto !== $product->photo && is_string($previousPhoto)
            && preg_match(ProductRequest::UPLOADED_PHOTO_PATTERN, $previousPhoto)
            && ! Product::withTrashed()->where('photo', $previousPhoto)->exists()) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return ApiResponse::success(new ProductResource($product), 'Product Information has been updated');
    }

    /**
     * @param  list<string>  $names
     */
    private function syncTags(Product $product, array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $ids[] = Tag::firstOrCreate(['name' => $name])->id;
        }
        $product->tags()->sync($ids);
    }

    public function destroy(string $id): ApiResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $product->delete();

        return ApiResponse::success(new ProductResource($product), 'Product has been Deleted');
    }

    public function upload(Request $request, ImageReencoder $reencoder): ApiResponse
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        $file = $request->file('file');

        // Store a re-encoded copy instead of the uploaded bytes so metadata
        // (EXIF/GPS) and anything hidden in or appended to the file is
        // dropped. The format is detected from the file contents, never taken
        // from the client-supplied name or extension.
        try {
            $image = $reencoder->reencode((string) $file->getRealPath());
        } catch (UnreadableImageException) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read as an image.',
            ]);
        }

        // e.g. uploads/<40 random letters and digits>.png on the "public" disk.
        $path = 'uploads/'.Str::random(40).'.'.$image['extension'];

        if (! Storage::disk('public')->put($path, $image['contents'])) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        return ApiResponse::success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Image uploaded');
    }
}
