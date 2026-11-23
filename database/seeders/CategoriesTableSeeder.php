<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo data (keyed by category name): safe to run repeatedly,
 * never changes or deletes existing rows.
 */
class CategoriesTableSeeder extends Seeder
{
    /**
     * Category name => description.
     */
    public const CATEGORIES = [
        'Electronics' => 'Computer accessories, chargers and audio gear for home and office.',
        'Home & Kitchen' => 'Everyday items for cooking, serving and keeping the home tidy.',
        'Office Supplies' => 'Paper, pens and desk equipment for work and study.',
        'Sports & Outdoors' => 'Gear for exercise, travel and time outside.',
        'Personal Care' => 'Products for daily hygiene and grooming.',
        'Books' => 'Printed books on practical topics, including software development.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
