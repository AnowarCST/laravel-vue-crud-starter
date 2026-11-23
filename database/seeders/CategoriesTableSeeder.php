<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo data: safe to run repeatedly, never deletes rows.
 */
class CategoriesTableSeeder extends Seeder
{
    public const NAMES = [
        'Electrical Vehicle/e-Power',
        'Compact Car',
        'Light Car',
        'Minivan',
        'Sports & Specialty',
        'Sedan',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::firstOrCreate(['name' => $name], ['description' => $name.' vehicles.']);
        }
    }
}
