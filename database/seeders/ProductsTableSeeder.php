<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo catalogue, keyed by SKU: products that already exist
 * (including deleted ones) are left unchanged, tags included, and nothing is
 * deleted. Each product
 * uses an original illustration from public/images/products (sources in
 * resources/images/products). All products, brands, book titles and authors
 * are invented.
 */
class ProductsTableSeeder extends Seeder
{
    /**
     * @var list<array{sku: string, name: string, category: string, price: string, stock: int, status: ProductStatus, image: string, tags: list<string>, description: string}>
     */
    public const PRODUCTS = [
        [
            'sku' => 'EL-1001', 'name' => 'Wireless Mouse', 'category' => 'Electronics',
            'price' => '24.99', 'stock' => 120, 'status' => ProductStatus::Active, 'image' => 'wireless-mouse',
            'tags' => ['Wireless', 'Bestseller'],
            'description' => 'A compact mouse with a silent click and a 2.4 GHz USB receiver. One AA battery lasts up to 12 months.',
        ],
        [
            'sku' => 'EL-1002', 'name' => 'Mechanical Keyboard', 'category' => 'Electronics',
            'price' => '89.00', 'stock' => 35, 'status' => ProductStatus::Active, 'image' => 'mechanical-keyboard',
            'tags' => ['New arrival'],
            'description' => 'A full-size keyboard with tactile switches and white backlighting. Connects over a detachable USB-C cable.',
        ],
        [
            'sku' => 'EL-1003', 'name' => '65W USB-C Charger', 'category' => 'Electronics',
            'price' => '39.95', 'stock' => 4, 'status' => ProductStatus::Active, 'image' => 'usb-c-charger',
            'tags' => ['Bestseller', 'Limited stock'],
            'description' => 'A small wall charger with two USB-C ports. Powerful enough for most laptops, tablets and phones.',
        ],
        [
            'sku' => 'EL-1004', 'name' => 'Noise-Cancelling Headphones', 'category' => 'Electronics',
            'price' => '179.00', 'stock' => 0, 'status' => ProductStatus::Active, 'image' => 'noise-cancelling-headphones',
            'tags' => ['Wireless', 'Gift idea'],
            'description' => 'Over-ear Bluetooth headphones with active noise cancelling and up to 30 hours of battery life.',
        ],
        [
            'sku' => 'HK-3001', 'name' => 'Ceramic Coffee Mug', 'category' => 'Home & Kitchen',
            'price' => '12.00', 'stock' => 12, 'status' => ProductStatus::Archived, 'image' => 'ceramic-coffee-mug',
            'tags' => ['On sale', 'Gift idea'],
            'description' => 'A 350 ml stoneware mug with a matte glaze. Safe for the dishwasher and microwave.',
        ],
        [
            'sku' => 'HK-3002', 'name' => 'Bamboo Cutting Board', 'category' => 'Home & Kitchen',
            'price' => '22.00', 'stock' => 5, 'status' => ProductStatus::Active, 'image' => 'bamboo-cutting-board',
            'tags' => ['Eco-friendly', 'Limited stock'],
            'description' => 'A sturdy 40 x 28 cm board made from bamboo, with a juice groove around the edge.',
        ],
        [
            'sku' => 'OF-4001', 'name' => 'LED Desk Lamp', 'category' => 'Office Supplies',
            'price' => '34.90', 'stock' => 18, 'status' => ProductStatus::Active, 'image' => 'led-desk-lamp',
            'tags' => ['New arrival'],
            'description' => 'An adjustable desk lamp with three colour temperatures and a dimmer. Uses 8 W at full brightness.',
        ],
        [
            'sku' => 'OF-4002', 'name' => 'A5 Dotted Notebook', 'category' => 'Office Supplies',
            'price' => '8.50', 'stock' => 300, 'status' => ProductStatus::Active, 'image' => 'a5-dotted-notebook',
            'tags' => ['Bestseller', 'Eco-friendly'],
            'description' => 'A hardcover notebook with 160 pages of dotted, recycled paper and a ribbon bookmark.',
        ],
        [
            'sku' => 'OF-4003', 'name' => 'Gel Pen Set', 'category' => 'Office Supplies',
            'price' => '6.99', 'stock' => 2, 'status' => ProductStatus::Active, 'image' => 'gel-pen-set',
            'tags' => ['Bundle', 'On sale'],
            'description' => 'Ten 0.5 mm gel pens in assorted colours. Quick-drying ink that does not smudge.',
        ],
        [
            'sku' => 'SP-2001', 'name' => 'Stainless Steel Water Bottle', 'category' => 'Sports & Outdoors',
            'price' => '19.50', 'stock' => 210, 'status' => ProductStatus::Active, 'image' => 'water-bottle',
            'tags' => ['Eco-friendly', 'Bestseller'],
            'description' => 'A 750 ml insulated bottle that keeps drinks cold for 24 hours or hot for 12.',
        ],
        [
            'sku' => 'SP-2002', 'name' => 'Yoga Mat', 'category' => 'Sports & Outdoors',
            'price' => '29.00', 'stock' => 45, 'status' => ProductStatus::Active, 'image' => 'yoga-mat',
            'tags' => ['Eco-friendly'],
            'description' => 'A 6 mm non-slip mat made from natural rubber. Comes with a carrying strap.',
        ],
        [
            'sku' => 'SP-2003', 'name' => 'Everyday Backpack', 'category' => 'Sports & Outdoors',
            'price' => '59.00', 'stock' => 40, 'status' => ProductStatus::Draft, 'image' => 'everyday-backpack',
            'tags' => ['New arrival', 'Gift idea'],
            'description' => 'A 20 litre water-resistant backpack with a padded laptop sleeve and two side pockets.',
        ],
        [
            'sku' => 'PC-5001', 'name' => 'Electric Toothbrush', 'category' => 'Personal Care',
            'price' => '49.99', 'stock' => 60, 'status' => ProductStatus::Active, 'image' => 'electric-toothbrush',
            'tags' => ['Gift idea', 'Bestseller'],
            'description' => 'A rechargeable toothbrush with a two-minute timer and three cleaning modes. Includes two brush heads.',
        ],
        [
            'sku' => 'BK-6001', 'name' => 'Readable by Design (paperback)', 'category' => 'Books',
            'price' => '34.00', 'stock' => 15, 'status' => ProductStatus::Active, 'image' => 'programming-book',
            'tags' => ['Bestseller', 'Bundle'],
            'description' => 'A practical guide to clear names, small functions and useful tests, by the fictional author Sam Okafor. 320 pages.',
        ],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $item) {
            $category = Category::firstOrCreate(
                ['name' => $item['category']],
                ['description' => CategoriesTableSeeder::CATEGORIES[$item['category']]],
            );

            // withTrashed(): a deleted demo product keeps its SKU, so it must
            // not be created again (the SKU column is unique).
            $product = Product::withTrashed()->firstOrCreate(['sku' => $item['sku']], [
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
                'stock' => $item['stock'],
                'status' => $item['status'],
                'category_id' => $category->id,
                'photo' => 'images/products/'.$item['image'].'.webp',
            ]);

            if ($product->wasRecentlyCreated) {
                $product->tags()->sync(
                    array_map(fn (string $name): int => Tag::firstOrCreate(['name' => $name])->id, $item['tags'])
                );
            }
        }
    }
}
