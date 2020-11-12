<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('tags')->truncate();

        DB::table('tags')->insert(
            [
                [
                    'name' => 'Tag 1'
                ],
                [
                    'name' => 'Tag 2'
                ],
            ]
        );
    }
}
