<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

/**
 * The exact set of fields in each /api response, as documented in
 * docs/api/openapi.yaml and used by the SPA. A field that is added, removed
 * or renamed by mistake (for example a secret column) fails here.
 */
class ApiResponseFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const ENVELOPE = ['success', 'data', 'message'];

    private const PAGINATOR = ['current_page', 'first_page_url', 'from', 'last_page', 'last_page_url',
        'links' => ['*' => ['url', 'label', 'page', 'active']], 'next_page_url', 'path', 'per_page', 'prev_page_url', 'to', 'total'];

    private const USER = ['id', 'name', 'type', 'email', 'email_verified_at', 'created_at', 'updated_at'];

    private const CATEGORY = ['id', 'name', 'description', 'created_at', 'updated_at'];

    private const TAG = ['id', 'name', 'created_at', 'updated_at'];

    private const PRODUCT = ['id', 'name', 'sku', 'description', 'price', 'stock', 'status', 'category_id',
        'photo', 'created_at', 'updated_at', 'deleted_at', 'photo_url'];

    private const PRODUCT_TAG = ['text', 'id', 'pivot' => ['product_id', 'tag_id']];

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private static function envelope(array $data): array
    {
        return ['success', 'message', 'data' => $data];
    }

    /**
     * @param  array<int|string, mixed>  $item
     * @return array<int|string, mixed>
     */
    private static function page(array $item): array
    {
        return self::envelope([...self::PAGINATOR, 'data' => ['*' => $item]]);
    }

    public function test_user_and_profile_responses(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin, 'api');

        $this->getJson('/api/user')->assertOk()->assertExactJsonStructure(self::page(self::USER));
        $this->getJson('/api/user/'.$user->id)->assertOk()->assertExactJsonStructure(self::envelope(self::USER));
        $this->postJson('/api/user', [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'Unique-password-123!', 'type' => 'user',
        ])->assertOk()->assertExactJsonStructure(self::envelope(self::USER));
        $this->putJson('/api/user/'.$user->id, ['name' => 'Renamed'])
            ->assertOk()->assertExactJsonStructure(self::envelope(self::USER));
        $this->deleteJson('/api/user/'.$user->id)
            ->assertOk()->assertExactJsonStructure(self::envelope(['*' => self::USER]))->assertJsonCount(1, 'data');

        $this->getJson('/api/profile')->assertOk()->assertExactJsonStructure(self::envelope(self::USER));
        $this->putJson('/api/profile', ['name' => 'Me', 'email' => $admin->email])
            ->assertOk()->assertExactJsonStructure(self::envelope(self::USER));
        $this->postJson('/api/change-password', [
            'current_password' => 'Test-password-123!', 'new_password' => 'Another-password-123!',
            'confirm_password' => 'Another-password-123!',
        ])->assertOk()->assertExactJson(['success' => true, 'data' => [], 'message' => 'Password Has been updated']);
    }

    public function test_category_responses(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $category = Category::factory()->create(['name' => 'Kitchen']);

        $this->getJson('/api/category')->assertOk()->assertExactJsonStructure(self::page(self::CATEGORY));
        $this->getJson('/api/category/list')->assertOk()
            ->assertExactJson(['success' => true, 'data' => [(string) $category->id => 'Kitchen'], 'message' => 'Category list']);
        $this->getJson('/api/category/'.$category->id)->assertOk()->assertExactJsonStructure(self::envelope(self::CATEGORY));
        $this->putJson('/api/category/'.$category->id, ['name' => 'Garden'])
            ->assertOk()->assertExactJsonStructure(self::envelope(self::CATEGORY));

        // Only the saved fields: no description when none was sent.
        $this->postJson('/api/category', ['name' => 'Office'])->assertOk()
            ->assertExactJsonStructure(self::envelope(['id', 'name', 'created_at', 'updated_at']));
        $this->postJson('/api/category', ['name' => 'Travel', 'description' => 'Bags'])->assertOk()
            ->assertExactJsonStructure(self::envelope(self::CATEGORY));

        $this->deleteJson('/api/category/'.$category->id)->assertOk()->assertExactJsonStructure(self::envelope(self::CATEGORY));
    }

    public function test_tag_responses(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $tag = Tag::create(['name' => 'Bestseller']);

        $this->getJson('/api/tag')->assertOk()->assertExactJsonStructure(self::page(self::TAG));
        $this->getJson('/api/tag/list')->assertOk()
            ->assertExactJson(['success' => true, 'data' => [['name' => 'Bestseller', 'id' => $tag->id]], 'message' => 'Tag list']);
        $this->getJson('/api/tag/'.$tag->id)->assertOk()->assertExactJsonStructure(self::envelope(self::TAG));
        $this->postJson('/api/tag', ['name' => 'New', 'description' => 'Not a tag field'])
            ->assertOk()->assertExactJsonStructure(self::envelope(self::TAG));
        $this->putJson('/api/tag/'.$tag->id, ['name' => 'Renamed'])->assertOk()->assertExactJsonStructure(self::envelope(self::TAG));
        $this->deleteJson('/api/tag/'.$tag->id)->assertOk()->assertExactJsonStructure(self::envelope(self::TAG));
    }

    public function test_product_read_responses_include_the_category_and_tags(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $product = Product::factory()->create();
        $product->tags()->attach(Tag::create(['name' => 'Bestseller']));
        $orphan = Product::factory()->create();
        Category::query()->whereKey($orphan->category_id)->delete();

        $item = [...self::PRODUCT, 'category' => self::CATEGORY, 'tags' => ['*' => self::PRODUCT_TAG]];
        $this->getJson('/api/product?sort=oldest&per_page=1')->assertOk()->assertExactJsonStructure(self::page($item));
        $this->getJson('/api/product/'.$product->id)->assertOk()->assertExactJsonStructure(self::envelope($item))
            ->assertJsonPath('data.tags.0.pivot', ['product_id' => $product->id, 'tag_id' => $product->tags()->value('tags.id')]);

        // A product whose category was deleted: "category" is null.
        $this->getJson('/api/product/'.$orphan->id)->assertOk()
            ->assertExactJsonStructure(self::envelope([...self::PRODUCT, 'category', 'tags']))
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.tags', []);
    }

    public function test_product_write_responses_contain_only_the_saved_fields(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create(), 'api');
        $category = Category::factory()->create();
        $payload = ['name' => 'Lamp', 'description' => 'A lamp.', 'price' => '5', 'category_id' => $category->id];
        $created = array_values(array_diff(self::PRODUCT, ['photo', 'deleted_at']));

        $id = $this->jsonInt($this->postJson('/api/product', [...$payload, 'sku' => 'LAMP-1', 'tags' => [['text' => 'New']]])
            ->assertOk()->assertExactJsonStructure(self::envelope($created)), 'data.id');

        $path = $this->jsonString($this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', ImageFixtures::png(ImageFixtures::opaque(1, 1))),
        ])->assertOk()->assertExactJsonStructure(self::envelope(['path', 'url'])), 'data.path');
        $this->postJson('/api/product', [...$payload, 'sku' => 'LAMP-2', 'photo' => $path])
            ->assertOk()->assertExactJsonStructure(self::envelope([...$created, 'photo']));

        $this->putJson('/api/product/'.$id, [...$payload, 'tags' => [['text' => 'Other']]])
            ->assertOk()->assertExactJsonStructure(self::envelope(self::PRODUCT));
        $this->deleteJson('/api/product/'.$id)->assertOk()->assertExactJsonStructure(self::envelope(self::PRODUCT))
            ->assertJsonPath('data.deleted_at', fn (mixed $value): bool => is_string($value));

        $this->getJson('/api/product/stats')->assertOk()
            ->assertExactJsonStructure(self::envelope(['total', 'active', 'draft', 'archived', 'low_stock', 'out_of_stock']));
    }

    public function test_every_success_response_uses_the_envelope(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        foreach (['/api/profile', '/api/category', '/api/category/list', '/api/tag', '/api/tag/list', '/api/product', '/api/product/stats'] as $uri) {
            $response = $this->getJson($uri)->assertOk()->assertJsonPath('success', true);
            $this->assertSame(self::ENVELOPE, array_keys((array) $response->json()), $uri);
            $this->assertIsString($response->json('message'), $uri);
        }
    }
}
