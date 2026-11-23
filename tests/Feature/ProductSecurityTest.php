<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function image(string $name = 'picture.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='
        ));
    }

    public function test_upload_rejects_scripts_svg_and_images_with_executable_extensions(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(), 'api');
        foreach ([
            UploadedFile::fake()->createWithContent('shell.php', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "unsafe";'),
            UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            $this->image('shell.php'),
        ] as $file) {
            $this->postJson('/api/product/upload', ['file' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_upload_stores_valid_images_with_generated_names_outside_public_source_directory(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(), 'api');
        $response = $this->postJson('/api/product/upload', ['file' => $this->image()])
            ->assertOk()->assertJsonPath('success', true);
        $path = $response->json('data.path');
        $this->assertMatchesRegularExpression('~^uploads/[A-Za-z0-9]{40}\.png$~', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringNotContainsString('picture', $path);
    }

    public function test_upload_rejects_missing_and_oversized_files(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $this->postJson('/api/product/upload', [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_guests_cannot_upload_files(): void
    {
        $this->postJson('/api/product/upload', ['file' => $this->image()])->assertUnauthorized();
    }

    public function test_product_crud_validates_relations_and_tags_and_requires_admin_for_delete(): void
    {
        $user = User::factory()->create();
        $category = Category::create(['name' => 'Category']);
        $data = [
            'name' => 'Product', 'description' => 'Description', 'price' => '19.95',
            'category_id' => $category->id, 'tags' => [['text' => 'First']],
        ];
        $this->actingAs($user, 'api');
        $response = $this->postJson('/api/product', $data)->assertOk();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('tags', ['name' => 'First']);
        $this->getJson('/api/product/'.$id)->assertOk()->assertJsonPath('data.name', 'Product');
        $this->putJson('/api/product/'.$id, [...$data, 'price' => '-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->putJson('/api/product/'.$id, [...$data, 'tags' => [['text' => ['invalid']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('tags.0.text');
        $this->putJson('/api/product/'.$id, [...$data, 'photo' => 'upload/evil.php'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->putJson('/api/product/'.$id, [...$data, 'name' => 'Updated', 'tags' => []])
            ->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->assertDatabaseMissing('product_tag', ['product_id' => $id]);
        $this->deleteJson('/api/product/'.$id)->assertForbidden();
        $this->actingAs(User::factory()->create(['type' => 'admin']), 'api')
            ->deleteJson('/api/product/'.$id)->assertOk();
        $this->assertSoftDeleted('products', ['id' => $id]);
    }
}
