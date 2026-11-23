<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\StoreProductImage;
use App\Actions\Products\UpdateProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductImageRequest;
use App\Http\Requests\Products\ProductIndexRequest;
use App\Http\Requests\Products\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Support\UnreadableImageException;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Paginated product list with optional search, filters and sorting; see
     * ProductIndexRequest for the accepted query parameters.
     */
    public function index(ProductIndexRequest $request): ApiResponse
    {
        $query = Product::query()->with(['category', 'tags']);

        if (($search = $request->filterText('search')) !== null) {
            $query->search($search);
        }
        if (($categoryId = $request->filterInt('category_id')) !== null) {
            $query->where('category_id', $categoryId);
        }
        if (($tagId = $request->filterInt('tag')) !== null) {
            $query->taggedWith($tagId);
        }
        if (($status = $request->status()) !== null) {
            $query->where('status', $status);
        }
        if (($stockLevel = $request->stockLevel()) !== null) {
            $query->stockLevel($stockLevel);
        }

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
     * units; out of stock is 0. Deleted products are not counted.
     */
    public function stats(): ApiResponse
    {
        $byStatus = Product::query()->toBase()
            ->select('status')->selectRaw('count(*) as aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');

        $count = fn (ProductStatus $status): int => is_numeric($byStatus[$status->value] ?? null) ? (int) $byStatus[$status->value] : 0;

        return ApiResponse::success([
            'total' => Product::count(),
            'active' => $count(ProductStatus::Active),
            'draft' => $count(ProductStatus::Draft),
            'archived' => $count(ProductStatus::Archived),
            'low_stock' => Product::query()->lowStock()->count(),
            'out_of_stock' => Product::query()->outOfStock()->count(),
        ], 'Product statistics');
    }

    public function show(Product $product): ApiResponse
    {
        return ApiResponse::success(new ProductResource($product->load('category', 'tags')), 'Product Details');
    }

    public function store(ProductRequest $request, CreateProduct $createProduct): ApiResponse
    {
        $product = $createProduct->handle($request->productAttributes(), $request->tagNames() ?? []);

        return ApiResponse::success(new ProductResource($product), 'Product Created Successfully');
    }

    public function update(ProductRequest $request, Product $product, UpdateProduct $updateProduct): ApiResponse
    {
        $updateProduct->handle($product, $request->productAttributes(), $request->tagNames());

        return ApiResponse::success(new ProductResource($product), 'Product Information has been updated');
    }

    public function destroy(Product $product): ApiResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return ApiResponse::success(new ProductResource($product), 'Product has been Deleted');
    }

    public function upload(ProductImageRequest $request, StoreProductImage $storeImage): ApiResponse
    {
        try {
            $path = $storeImage->handle($request->uploadedFile());
        } catch (UnreadableImageException) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read as an image.',
            ]);
        }

        return ApiResponse::success([
            'path' => $path,
            'url' => Product::photoUrlFor($path),
        ], 'Image uploaded');
    }
}
