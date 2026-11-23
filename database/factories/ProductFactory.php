<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Everyday retail products, for example "Compact Desk Organiser".
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        /** @var string $adjective */
        $adjective = fake()->randomElement(['Compact', 'Classic', 'Foldable', 'Rechargeable', 'Reusable', 'Portable', 'Large', 'Travel']);
        /** @var string $item */
        $item = fake()->randomElement(['Desk Organiser', 'Lunch Box', 'Phone Stand', 'Laundry Basket', 'Reading Light', 'Shopping Bag', 'Kitchen Scale', 'Alarm Clock', 'Picture Frame', 'Umbrella']);

        return [
            'name' => $adjective.' '.$item,
            'sku' => fake()->unique()->regexify('[A-Z]{2}-[0-9]{6}'),
            'description' => fake()->sentence(12),
            'price' => number_format(fake()->randomFloat(2, 2, 250), 2, '.', ''),
            'stock' => fake()->numberBetween(0, 200),
            'status' => ProductStatus::Active,
            'category_id' => Category::factory(),
            'photo' => null,
        ];
    }

    /**
     * @param  int<0, max>  $stock
     */
    public function stock(int $stock): static
    {
        return $this->state(['stock' => $stock]);
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(ProductStatus::Draft);
    }

    public function archived(): static
    {
        return $this->status(ProductStatus::Archived);
    }
}
