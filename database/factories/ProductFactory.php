<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Product;
use Faker\Generator as Faker;

$factory->define(Product::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
        'description' => $faker->text,
        'price' => $faker->numberBetween($min = 100000, $max = 900000),
        'category_id' => 5,
        'photo' => 'images/cars/cima_1912_top_01.jpg.ximg.l_full_m.smart.jpg',
    ];
});
