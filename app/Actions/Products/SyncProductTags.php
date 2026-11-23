<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\Tag;

/**
 * Replaces a product's tags with the given names, creating tags that do not
 * exist yet. An empty list removes every tag from the product.
 */
final class SyncProductTags
{
    /**
     * @param  list<string>  $names
     */
    public function handle(Product $product, array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            $ids[] = Tag::firstOrCreate(['name' => $name])->id;
        }

        $product->tags()->sync($ids);
    }
}
