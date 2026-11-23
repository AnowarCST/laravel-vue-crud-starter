<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product and its tags in one transaction.
 */
final class CreateProduct
{
    public function __construct(private readonly SyncProductTags $syncTags) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated product fields, without "tags".
     * @param  list<string>  $tagNames
     */
    public function handle(array $attributes, array $tagNames): Product
    {
        return DB::transaction(function () use ($attributes, $tagNames): Product {
            $product = Product::create($attributes);
            $this->syncTags->handle($product, $tagNames);

            return $product;
        });
    }
}
