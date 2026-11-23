<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo data: safe to run repeatedly, never deletes rows.
 */
class TagsTableSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tag 1', 'Tag 2'] as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}
