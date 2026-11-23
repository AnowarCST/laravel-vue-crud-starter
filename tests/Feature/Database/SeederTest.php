<?php

namespace Tests\Feature\Database;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The optional demo data (php artisan db:seed).
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_is_idempotent_and_creates_no_accounts(): void
    {
        $this->seed();
        $counts = [Category::count(), Tag::count(), Product::count(), DB::table('product_tag')->count()];
        $this->seed();

        $this->assertSame(0, User::count());
        $this->assertSame([6, 8, 14, $counts[3]], $counts);
        $this->assertSame($counts, [Category::count(), Tag::count(), Product::count(), DB::table('product_tag')->count()]);
    }

    public function test_seeded_products_are_complete_and_their_images_exist(): void
    {
        $this->seed();

        foreach (Product::with('category', 'tags')->get() as $product) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9-]+$/', (string) $product->sku);
            $this->assertContains($product->status, ProductStatus::cases());
            $this->assertNotNull($product->category, $product->name);
            $this->assertNotEmpty($product->tags, $product->name);
            $this->assertIsString($product->photo);
            $this->assertMatchesRegularExpression('~^images/products/[a-z0-9-]+\.webp$~', $product->photo);
            $this->assertFileExists(public_path($product->photo));
            $this->assertLessThan(60 * 1024, (int) filesize(public_path($product->photo)), $product->photo);
            $this->assertSame('http://localhost/'.$product->photo, $product->photo_url);
        }

        $this->assertSame(1, Product::query()->outOfStock()->count());
        $this->assertGreaterThanOrEqual(2, Product::query()->lowStock()->count());
        $this->assertSame(Product::count(), Product::distinct()->count('photo'));
    }

    public function test_seeder_keeps_existing_rows_and_does_not_recreate_deleted_demo_products(): void
    {
        $legacy = Product::factory()->create(['name' => 'Legacy product', 'sku' => null]);
        $this->seed();
        $mouse = Product::where('sku', 'EL-1001')->firstOrFail();
        $mouse->update(['name' => 'Renamed mouse', 'stock' => 7]);
        $mouse->tags()->sync([]);
        Product::where('sku', 'EL-1002')->firstOrFail()->delete();

        $this->seed();

        $this->assertModelExists($legacy);
        $this->assertSame(['Renamed mouse', 7], [$mouse->fresh()?->name, $mouse->fresh()?->stock]);
        $this->assertSame(0, $mouse->tags()->count());
        $this->assertSame(1, Product::withTrashed()->where('sku', 'EL-1002')->count());
        $this->assertSoftDeleted('products', ['sku' => 'EL-1002']);
    }

    public function test_users_seeder_does_not_provision_a_known_admin(): void
    {
        $this->seed(UsersTableSeeder::class);
        $this->assertDatabaseCount('users', 0);
    }
}
