<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo data (keyed by tag name): safe to run repeatedly, never
 * deletes rows.
 */
class TagsTableSeeder extends Seeder
{
    public const NAMES = [
        'Bestseller',
        'New arrival',
        'Eco-friendly',
        'On sale',
        'Wireless',
        'Gift idea',
        'Limited stock',
        'Bundle',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}
