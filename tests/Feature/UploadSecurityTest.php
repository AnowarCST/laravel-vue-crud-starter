<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(), 'api');
    }

    public function test_rejects_executable_and_disguised_uploads(): void
    {
        $png = base64_decode(self::PNG);

        foreach ([
            'php source as .php' => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'),
            'php source as .png' => UploadedFile::fake()->createWithContent('shell.png', '<?php system($_GET["c"]);'),
            'phtml' => UploadedFile::fake()->createWithContent('shell.phtml', $png),
            'double extension' => UploadedFile::fake()->createWithContent('photo.png.php', $png),
            'svg with script' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
            'gif/php polyglot' => UploadedFile::fake()->createWithContent('poly.gif', "GIF89a\x01\x00\x01\x00<?php system('id'); ?>"),
            'html' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
        ] as $case => $file) {
            $this->postJson('/api/product/upload', ['file' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('file');
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_png_polyglot_is_stored_with_a_generated_non_executable_name(): void
    {
        // A valid PNG carrying a PHP payload: accepted as an image, but its
        // name and extension come from the server, so it can never be run.
        $file = UploadedFile::fake()->createWithContent('innocent.png', base64_decode(self::PNG).'<?php system("id"); ?>');

        $path = $this->postJson('/api/product/upload', ['file' => $file])->assertOk()->json('data.path');

        $this->assertMatchesRegularExpression('~^uploads/[A-Za-z0-9]{40}\.png$~', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_product_photo_only_accepts_paths_issued_by_the_upload_endpoint(): void
    {
        $category = Category::factory()->create();
        $base = ['name' => 'P', 'description' => 'D', 'price' => '1', 'category_id' => $category->id];

        foreach ([
            '../.env', 'uploads/../../.env', '/etc/passwd', 'uploads/shell.php',
            'uploads/'.str_repeat('a', 40).'.php', 'uploads/'.str_repeat('a', 40).'.png/../x',
            'https://evil.example/x.png', 'images/cars/panel01_img01.jpg',
        ] as $photo) {
            $this->postJson('/api/product', $base + ['photo' => $photo])
                ->assertUnprocessable()->assertJsonValidationErrors('photo');
        }

        $path = $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG)),
        ])->json('data.path');
        $created = $this->postJson('/api/product', $base + ['photo' => $path])->assertOk()
            ->assertJsonPath('data.photo', $path);
        $this->assertStringEndsWith('/storage/'.$path, $created->json('data.photo_url'));
    }

    public function test_legacy_photo_may_be_resubmitted_unchanged_and_replaced_uploads_are_removed(): void
    {
        $product = Product::factory()->create(['photo' => 'images/cars/panel01_img01.jpg']);
        $base = ['name' => 'P', 'description' => 'D', 'price' => '1', 'category_id' => $product->category_id];

        $this->putJson('/api/product/'.$product->id, $base + ['photo' => 'images/cars/panel01_img01.jpg'])->assertOk()
            ->assertJsonPath('data.photo_url', 'http://localhost/images/cars/panel01_img01.jpg');
        $this->putJson('/api/product/'.$product->id, $base + ['photo' => 'images/cars/other.jpg'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $upload = fn () => $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG)),
        ])->json('data.path');

        $first = $upload();
        $this->putJson('/api/product/'.$product->id, $base + ['photo' => $first])->assertOk();
        $second = $upload();
        $this->putJson('/api/product/'.$product->id, $base + ['photo' => $second])->assertOk();

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }
}
