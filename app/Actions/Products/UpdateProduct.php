<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Updates a product and, when tag names are given, its tags in one
 * transaction. When the photo was replaced, the previous upload is deleted
 * from the "public" disk, but only if this API created it and no other
 * product (deleted products included) still uses it.
 */
final class UpdateProduct
{
    public function __construct(private readonly SyncProductTags $syncTags) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated product fields, without "tags".
     * @param  list<string>|null  $tagNames  Null leaves the tags unchanged.
     */
    public function handle(Product $product, array $attributes, ?array $tagNames): Product
    {
        $previousPhoto = $product->photo;

        DB::transaction(function () use ($product, $attributes, $tagNames): void {
            $product->update($attributes);

            if ($tagNames !== null) {
                $this->syncTags->handle($product, $tagNames);
            }
        });

        if ($previousPhoto !== $product->photo && is_string($previousPhoto)
            && preg_match(StoreProductImage::PATH_PATTERN, $previousPhoto)
            && ! Product::withTrashed()->where('photo', $previousPhoto)->exists()) {
            Storage::disk('public')->delete($previousPhoto);
        }

        return $product;
    }
}
