<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        /** @var string $area */
        $area = fake()->randomElement(['Garden', 'Kitchen', 'Office', 'Travel', 'Bathroom', 'Fitness', 'Kids', 'Pet']);

        /** @var string $kind */
        $kind = fake()->randomElement(['Essentials', 'Accessories', 'Supplies', 'Storage', 'Tools', 'Gifts', 'Care', 'Basics']);

        return [
            'name' => $area.' '.$kind,
            'description' => fake()->sentence(),
        ];
    }
}
