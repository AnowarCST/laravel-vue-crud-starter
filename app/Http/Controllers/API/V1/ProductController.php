<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Requests\Products\ProductRequest;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends BaseController
{
    public function index()
    {
        return $this->sendResponse(Product::latest()->with('category', 'tags')->paginate(10), 'Product list');
    }

    public function show($id)
    {
        return $this->sendResponse(Product::with('category', 'tags')->findOrFail($id), 'Product Details');
    }

    public function store(ProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->safe()->except('tags'));
            $this->syncTags($product, $request->validated('tags', []));

            return $product;
        });

        return $this->sendResponse($product, 'Product Created Successfully');
    }

    public function update(ProductRequest $request, $id)
    {
        $product = Product::findOrFail($id);
        $this->authorize('update', $product);
        $previousPhoto = $product->photo;

        DB::transaction(function () use ($request, $product) {
            $product->update($request->safe()->except('tags'));
            if ($request->has('tags')) {
                $this->syncTags($product, $request->validated('tags', []));
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

    private function syncTags(Product $product, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tag) {
            $ids[] = Tag::firstOrCreate(['name' => $tag['text']])->id;
        }
        $product->tags()->sync($ids);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $this->authorize('delete', $product);
        $product->delete();

        return $this->sendResponse($product, 'Product has been Deleted');
    }

    public function upload(Request $request)
    {
        $this->authorize('create', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        // store() names the file Str::random(40) plus an extension guessed from
        // the detected MIME type (never the client-supplied name/extension),
        // e.g. uploads/<40 alphanumerics>.png on the "public" disk.
        $path = $request->file('file')->store('uploads', 'public');

        return $this->sendResponse([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Image uploaded');
    }
}
