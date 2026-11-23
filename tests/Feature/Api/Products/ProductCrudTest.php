<?php

namespace Tests\Feature\Api\Products;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Create, read, update and delete of products, and validation of SKU,
 * stock, status, price, category and tags. Photos are covered by
 * Tests\Feature\Security\UploadSecurityTest.
 */
class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser();
        $this->category = Category::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Desk Lamp', 'sku' => 'LAMP-1', 'description' => 'A lamp.', 'price' => '34.90',
            'stock' => 10, 'status' => 'active', 'category_id' => $this->category->id, ...$overrides,
        ];
    }

    public function test_product_crud_validates_relations_and_tags_and_requires_admin_for_delete(): void
    {
        $data = $this->payload(['name' => 'Product', 'sku' => 'PRD-1', 'price' => '19.95', 'tags' => [['text' => 'First']]]);
        $response = $this->assertApiSuccess($this->postJson('/api/product', $data), 'Product Created Successfully');
        $id = $this->jsonInt($response, 'data.id');
        $this->assertDatabaseHas('tags', ['name' => 'First']);
        $this->assertApiSuccess($this->getJson('/api/product/'.$id), 'Product Details')
            ->assertJsonPath('data.name', 'Product')
            ->assertJsonPath('data.tags.0.text', 'First');
        $this->putJson('/api/product/'.$id, [...$data, 'price' => '-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->putJson('/api/product/'.$id, [...$data, 'tags' => [['text' => ['invalid']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('tags.0.text');
        $this->putJson('/api/product/'.$id, [...$data, 'photo' => 'upload/evil.php'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->putJson('/api/product/'.$id, [...$data, 'category_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->assertApiSuccess($this->putJson('/api/product/'.$id, [...$data, 'name' => 'Updated', 'tags' => []]), 'Product Information has been updated')
            ->assertJsonPath('data.name', 'Updated');
        $this->assertDatabaseMissing('product_tag', ['product_id' => $id]);
        $this->deleteJson('/api/product/'.$id)->assertForbidden();

        $this->actingAsAdmin();
        $this->assertApiSuccess($this->deleteJson('/api/product/'.$id), 'Product has been Deleted');
        $this->assertSoftDeleted('products', ['id' => $id]);
        $this->assertGenericNotFound($this->getJson('/api/product/'.$id));
    }

    public function test_updating_without_tags_keeps_them(): void
    {
        $id = $this->jsonInt($this->postJson('/api/product', $this->payload(['tags' => [['text' => 'Kept']]]))->assertOk(), 'data.id');
        $data = $this->payload();

        $this->putJson('/api/product/'.$id, [...$data, 'name' => 'Renamed'])->assertOk();

        $this->getJson('/api/product/'.$id)->assertJsonPath('data.tags.0.text', 'Kept');
    }

    public function test_unknown_ids_return_404(): void
    {
        $this->actingAsAdmin();

        $this->assertGenericNotFound($this->getJson('/api/product/999999'));
        $this->assertGenericNotFound($this->putJson('/api/product/999999', $this->payload()));
        $this->assertGenericNotFound($this->deleteJson('/api/product/999999'));
    }

    public function test_guests_cannot_manage_products(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/product')->assertUnauthorized();
        $this->postJson('/api/product', $this->payload())->assertUnauthorized();
    }

    public function test_sku_is_required_on_create_upper_cased_and_unique(): void
    {
        $this->postJson('/api/product', $this->payload(['sku' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The SKU field is required.']);

        $id = $this->jsonInt($this->postJson('/api/product', $this->payload(['sku' => ' lamp-1 ']))
            ->assertOk()->assertJsonPath('data.sku', 'LAMP-1'), 'data.id');

        $this->postJson('/api/product', $this->payload(['sku' => 'Lamp-1']))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku' => 'The SKU has already been taken.']);

        // Updating a product with its own SKU is allowed.
        $this->putJson('/api/product/'.$id, $this->payload(['name' => 'Renamed']))->assertOk();

        // A deleted product still owns its SKU (the column is unique).
        Product::findOrFail($id)->delete();
        $this->postJson('/api/product', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSkus(): iterable
    {
        yield 'space' => ['AB 12'];
        yield 'underscore' => ['AB_12'];
        yield 'non-ASCII letter' => ['ÄB-1'];
        yield 'slash' => ['AB/1'];
        yield 'longer than 64 characters' => [str_repeat('A', 65)];
        yield 'array' => [['AB']];
    }

    #[DataProvider('invalidSkus')]
    public function test_invalid_skus_are_rejected(mixed $sku): void
    {
        $this->postJson('/api/product', $this->payload(['sku' => $sku]))
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    public function test_sku_may_be_left_out_when_updating_a_product_that_has_none(): void
    {
        $legacy = Product::factory()->for($this->category)->create(['sku' => null]);
        $taken = Product::factory()->for($this->category)->create(['sku' => 'TAKEN-1']);
        $data = $this->payload();
        unset($data['sku']);

        $this->putJson('/api/product/'.$legacy->id, $data)->assertOk()->assertJsonPath('data.sku', null);
        $this->putJson('/api/product/'.$legacy->id, [...$data, 'sku' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->putJson('/api/product/'.$legacy->id, [...$data, 'sku' => $taken->sku])
            ->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidStockStatusAndPrice(): iterable
    {
        foreach ([
            'stock' => [-1, 1000001, 1.5, 'many', null],
            'status' => ['deleted', 'Active', '', ['active']],
            'price' => [-0.01, 100000000, '99999999.999', '12.345', '1e3', 'free'],
        ] as $field => $values) {
            foreach ($values as $value) {
                yield $field.'='.var_export($value, true) => [$field, $value];
            }
        }
    }

    #[DataProvider('invalidStockStatusAndPrice')]
    public function test_stock_status_and_price_are_validated(string $field, mixed $value): void
    {
        $this->postJson('/api/product', $this->payload([$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_largest_stock_and_price_are_accepted(): void
    {
        $this->postJson('/api/product', $this->payload(['stock' => 1000000, 'status' => 'draft', 'price' => '99999999.99']))
            ->assertOk()->assertJsonPath('data.stock', 1000000)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.price', '99999999.99');
    }

    public function test_stock_and_status_default_to_zero_and_active(): void
    {
        $data = $this->payload(['price' => 5]);
        unset($data['stock'], $data['status']);

        $response = $this->postJson('/api/product', $data)->assertOk()
            ->assertJsonPath('data.stock', 0)->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.price', '5.00');
        $this->assertDatabaseHas('products', ['id' => $this->jsonInt($response, 'data.id'), 'stock' => 0, 'status' => 'active']);
    }
}
