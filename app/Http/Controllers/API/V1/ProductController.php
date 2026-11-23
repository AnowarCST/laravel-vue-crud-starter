<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\Products\ProductRequest;
use App\Models\Product;
use App\Models\Tag;
use App\Support\ImageReencoder;
use App\Support\UnreadableImageException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->sendResponse(Product::latest()->with('category', 'tags')->paginate(10), 'Product list');
    }

    public function show(string $id): JsonResponse
    {
        return $this->sendResponse(Product::with('category', 'tags')->findOrFail($id), 'Product Details');
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('tags'));
            $this->syncTags($product, $request->tagNames());

            return $product;
        });

        return $this->sendResponse($product, 'Product Created Successfully');
    }

    public function update(ProductRequest $request, string $id): JsonResponse
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

        return $this->sendResponse($product, 'Product Information has been updated');
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

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $product->delete();

        return $this->sendResponse($product, 'Product has been Deleted');
    }

    public function upload(Request $request, ImageReencoder $reencoder): JsonResponse
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

        return $this->sendResponse([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Image uploaded');
    }
}
