<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed demo catalogue data. No user accounts are created: provision an
     * administrator interactively with `php artisan app:create-admin`.
     */
    public function run(): void
    {
        $this->call([
            UsersTableSeeder::class,
            CategoriesTableSeeder::class,
            TagsTableSeeder::class,
            ProductsTableSeeder::class,
        ]);
    }
}
