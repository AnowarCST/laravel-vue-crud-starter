<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo data (keyed by product name); requires CategoriesTableSeeder.
 */
class ProductsTableSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'name' => 'Cima',
                'description' => 'The charm of this car. Choose CIMA. It is the pride and pleasure of choosing one vertex.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[4],
                'photo' => 'images/cars/cima_1912_top_01.jpg.ximg.l_full_m.smart.jpg',
            ],
            [
                'name' => 'Fuga',
                'description' => 'The Infiniti brand is highly regarded for its advanced design and powerful performance in each of its markets, including the U.S., Canada, Europe, Russia, the Middle East, China and Korea. With its highly refined style and responsiveness, Infiniti promises a driving experience with unparalleled appeal.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[1],
                'photo' => 'images/cars/fuga_1912_top_02.jpg.ximg.l_full_m.smart.jpg',
            ],
            [
                'name' => 'Skyline',
                'description' => 'Datsun will provide an appealing and sustainable motoring experience to optimistic up-and-coming customers in high-growth markets. Datsun combines Nissan Motor\'s 80 years of Japanese car-making expertise with the nearly century-old Datsun Brand DNA. Datsun vehicles will be Local Products ensured by a Global Brand, and starts sales in India, Indonesia, Russia and South Africa from 2014.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[1],
                'photo' => 'images/cars/sylphy_1803_top_002.jpg.ximg.l_full_m.smart.jpg',
            ],
            [
                'name' => 'Sylphy',
                'description' => 'Comfort to enjoy driving while relaxing.
            The interior space on a class that has become even wider, and ample rear seats.
            Here is a state-of-the-art environment where all occupants are comfortably wrapped.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[0],
                'photo' => 'images/cars/sylphy_1803_top_002.jpg.ximg.l_full_m.smart.jpg',
            ],
            [
                'name' => 'Teana',
                'description' => 'Comfort of "modern living". Consideration of "hospitality". Tiana has found a value that has never been in a sedan before. Refining its modern living and hospitality, Tiana offers a cruise-like, elegant travel experience. Lets go to Tiana Cruz.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[1],
                'photo' => 'images/cars/teana_1803_top_002.jpg.ximg.l_full_m.smart.jpg',
            ],
            [
                'name' => 'Leaf',
                'description' => 'NISSAN CROSSING. The Nissan Gallery in Ginza will now be born again as NISSAN CROSSING, a base to dispatch the Nissan brand globally.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[1],
                'photo' => 'images/cars/panel01_img01.jpg',
            ],
            [
                'name' => 'Skyline 2 ',
                'description' => 'Details Datsun will provide an appealing and sustainable motoring experience to optimistic up-and-coming customers in high-growth markets. Datsun combines Nissan Motor\'s 80 years of Japanese car-making expertise with the nearly century-old Datsun Brand DNA. Datsun vehicles will be Local Products ensured by a Global Brand, and starts sales in India, Indonesia, Russia and South Africa from 2014.',
                'price' => (string) random_int(1000000, 100000000),
                'category' => CategoriesTableSeeder::NAMES[2],
                'photo' => 'images/cars/sylphy_1803_top_002.jpg.ximg.l_full_m.smart.jpg',
            ],
        ];

        foreach ($products as $product) {
            $category = Category::firstOrCreate(['name' => $product['category']]);

            Product::firstOrCreate(['name' => $product['name']], [
                'description' => $product['description'],
                'price' => $product['price'],
                'photo' => $product['photo'],
                'category_id' => $category->id,
            ]);
        }
    }
}
