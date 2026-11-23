<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

/**
 * POST /api/product/upload only stores images, under generated names, and a
 * product's photo can only point at such an upload (or keep its seeded
 * value). The re-encoding itself is covered by UploadReencodingTest.
 */
class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAsUser();
    }

    /**
     * A valid 1x1 PNG.
     */
    private static function png(): string
    {
        return ImageFixtures::png(ImageFixtures::opaque(1, 1));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedFiles(): iterable
    {
        $png = 'PNG';

        yield 'php source as .php' => ['shell.php', '<?php system($_GET["c"]);'];
        yield 'php source as .png' => ['shell.png', '<?php system($_GET["c"]);'];
        yield 'php source as .jpg' => ['shell.jpg', '<?php echo "unsafe";'];
        yield 'png named .php' => ['shell.php', $png];
        yield 'png named .phtml' => ['shell.phtml', $png];
        yield 'double extension' => ['photo.png.php', $png];
        yield 'svg with onload' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'];
        yield 'svg with script' => ['script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'];
        yield 'gif/php polyglot' => ['poly.gif', "GIF89a\x01\x00\x01\x00<?php system('id'); ?>"];
        yield 'html' => ['page.html', '<script>alert(1)</script>'];
    }

    /**
     * @param  string  $contents  "PNG" stands for a valid PNG image.
     */
    #[DataProvider('rejectedFiles')]
    public function test_rejects_executable_and_disguised_uploads(string $name, string $contents): void
    {
        $file = UploadedFile::fake()->createWithContent($name, $contents === 'PNG' ? self::png() : $contents);

        $this->postJson('/api/product/upload', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rejects_missing_and_oversized_files(): void
    {
        $this->postJson('/api/product/upload', [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->create('large.png', 5121, 'image/png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_guests_cannot_upload_files(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('picture.png', self::png()),
        ])->assertUnauthorized();
    }

    public function test_valid_images_are_stored_with_generated_names(): void
    {
        $response = $this->assertApiSuccess($this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('picture.png', self::png()),
        ]), 'Image uploaded');

        $path = $this->jsonString($response, 'data.path');
        $this->assertMatchesRegularExpression('~^uploads/[A-Za-z0-9]{40}\.png$~', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringNotContainsString('picture', $path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('data.url'));
    }

    public function test_png_polyglot_is_stored_with_a_generated_name_and_without_the_payload(): void
    {
        // A valid PNG carrying a PHP payload: accepted as an image, but the
        // name and extension come from the server and only the re-encoded
        // pixels are stored (see UploadReencodingTest for more cases).
        $file = UploadedFile::fake()->createWithContent('innocent.png', self::png().'<?php system("id"); ?>');

        $path = $this->jsonString($this->postJson('/api/product/upload', ['file' => $file])->assertOk(), 'data.path');

        $this->assertMatchesRegularExpression('~^uploads/[A-Za-z0-9]{40}\.png$~', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringNotContainsString('<?php', (string) Storage::disk('public')->get($path));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function rejectedPhotoValues(): iterable
    {
        foreach ([
            '../.env', 'uploads/../../.env', '/etc/passwd', 'uploads/shell.php',
            'uploads/'.str_repeat('a', 40).'.php', 'uploads/'.str_repeat('a', 40).'.png/../x',
            'https://evil.example/x.png', 'images/products/wireless-mouse.webp',
            'images/products/../../.env',
        ] as $photo) {
            yield $photo => [$photo];
        }
        yield 'array' => [['uploads/'.str_repeat('a', 40).'.png']];
        yield 'integer' => [12345];
    }

    #[DataProvider('rejectedPhotoValues')]
    public function test_product_photo_only_accepts_paths_issued_by_the_upload_endpoint(mixed $photo): void
    {
        $category = Category::factory()->create();
        $base = ['name' => 'P', 'sku' => 'P-1', 'description' => 'D', 'price' => '1', 'category_id' => $category->id];

        $this->postJson('/api/product', $base + ['photo' => $photo])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_product_photo_accepts_an_uploaded_path(): void
    {
        $category = Category::factory()->create();
        $base = ['name' => 'P', 'sku' => 'P-1', 'description' => 'D', 'price' => '1', 'category_id' => $category->id];

        $path = $this->jsonString($this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', self::png()),
        ]), 'data.path');
        $created = $this->postJson('/api/product', $base + ['photo' => $path])->assertOk()
            ->assertJsonPath('data.photo', $path);
        $this->assertSame('/storage/'.$path, $this->jsonString($created, 'data.photo_url'));
    }

    /**
     * Images are served from the origin that served the page (CSP img-src
     * 'self'), so their URLs must not depend on APP_URL.
     */
    public function test_photo_urls_are_root_relative_whatever_app_url_says(): void
    {
        config(['app.url' => 'https://other.example', 'filesystems.disks.public.url' => 'https://other.example/storage']);
        Storage::forgetDisk('public');
        $product = Product::factory()->create(['photo' => 'images/products/coffee-mug.webp']);

        $upload = $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', self::png()),
        ])->assertOk();
        $this->assertSame('/storage/'.$this->jsonString($upload, 'data.path'), $this->jsonString($upload, 'data.url'));

        $this->getJson('/api/product/'.$product->id)->assertOk()
            ->assertJsonPath('data.photo_url', '/images/products/coffee-mug.webp');
    }

    public function test_seeded_photo_may_be_resubmitted_unchanged_and_replaced_uploads_are_removed(): void
    {
        $product = Product::factory()->create(['photo' => 'images/products/wireless-mouse.webp']);
        $base = ['name' => 'P', 'description' => 'D', 'price' => '1', 'category_id' => $product->category_id];

        $this->putJson('/api/product/'.$product->id, $base + ['photo' => 'images/products/wireless-mouse.webp'])->assertOk()
            ->assertJsonPath('data.photo_url', '/images/products/wireless-mouse.webp');
        foreach (['images/products/yoga-mat.webp', 'images/products/../../.env', 'images/products/wireless-mouse.webp/'] as $other) {
            $this->putJson('/api/product/'.$product->id, $base + ['photo' => $other])
                ->assertUnprocessable()->assertJsonValidationErrors('photo');
        }

        $upload = fn (): string => $this->jsonString($this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('a.png', self::png()),
        ]), 'data.path');

        $first = $upload();
        $this->putJson('/api/product/'.$product->id, $base + ['photo' => $first])->assertOk();
        $second = $upload();
        $this->putJson('/api/product/'.$product->id, $base + ['photo' => $second])->assertOk();

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    /**
     * @return iterable<string, array{string, non-empty-string|null}>
     */
    public static function photoUrls(): iterable
    {
        yield 'seeded image' => ['images/products/coffee-mug.webp', '/images/products/coffee-mug.webp'];
        yield 'upload' => ['uploads/'.str_repeat('a', 40).'.png', '/storage/uploads/'.str_repeat('a', 40).'.png'];
        yield 'traversal from images' => ['images/../.env', null];
        yield 'traversal from uploads' => ['uploads/../../.env', null];
        yield 'external URL' => ['https://evil.example/x.png', null];
        yield 'absolute path' => ['/etc/passwd', null];
        yield 'javascript URL' => ['javascript:alert(1)', null];
    }

    /**
     * @param  non-empty-string|null  $expected
     */
    #[DataProvider('photoUrls')]
    public function test_photo_url_is_only_generated_for_safe_relative_paths(string $photo, ?string $expected): void
    {
        $url = Product::factory()->make(['photo' => $photo])->photo_url;

        if ($expected === null) {
            $this->assertNull($url, $photo);
        } else {
            $this->assertSame($expected, $url);
        }
    }
}
