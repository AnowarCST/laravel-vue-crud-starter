<?php

namespace Tests\Feature\Security;

use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

/**
 * Uploaded images are decoded and re-encoded, so only the pixels are stored.
 */
class UploadReencodingTest extends TestCase
{
    use RefreshDatabase;

    private const PHP_PAYLOAD = '<?php system($_GET["c"]); ?>';

    private const HTML_PAYLOAD = '<script>alert(document.cookie)</script>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAsUser();
    }

    public function test_payloads_inside_and_after_a_png_are_not_stored(): void
    {
        $png = ImageFixtures::png(ImageFixtures::opaque(4, 3));
        // A tEXt chunk carrying PHP before IEND, plus HTML appended after IEND.
        $polyglot = substr($png, 0, -12)
            .ImageFixtures::pngChunk('tEXt', 'Comment'."\0".self::PHP_PAYLOAD)
            .substr($png, -12)
            .self::HTML_PAYLOAD;

        $stored = $this->uploadAndRead('photo.png', $polyglot, 'png');

        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('tEXt', $stored);
        $this->assertSame([4, 3], $this->dimensions($stored));
    }

    public function test_payloads_inside_and_after_a_jpeg_are_not_stored(): void
    {
        $jpeg = ImageFixtures::jpeg(ImageFixtures::opaque(4, 3));
        // A comment (COM) segment carrying PHP, plus HTML appended after EOI.
        $polyglot = ImageFixtures::insertJpegSegment($jpeg, 0xFE, self::PHP_PAYLOAD).self::HTML_PAYLOAD;

        $stored = $this->uploadAndRead('photo.jpg', $polyglot, 'jpg');

        $this->assertStringNotContainsString('<?php', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertSame([4, 3], $this->dimensions($stored));
    }

    public function test_exif_metadata_including_gps_is_removed_from_jpeg(): void
    {
        $jpeg = ImageFixtures::jpeg(ImageFixtures::opaque(4, 3));
        $withExif = ImageFixtures::insertJpegSegment($jpeg, 0xE1, ImageFixtures::exif(1, 'Private-camera-note'));
        $this->assertStringContainsString('Exif', $withExif);

        $stored = $this->uploadAndRead('photo.jpg', $withExif, 'jpg');

        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('Private-camera-note', $stored);
        $this->assertStringNotContainsString("\xFF\xE1", $stored, 'No APP1 (EXIF/XMP) segment may remain.');
    }

    public function test_exif_orientation_is_applied_before_metadata_is_removed(): void
    {
        // Orientation 6: the camera was turned, the image must be rotated 90
        // degrees clockwise to be displayed upright (4x3 becomes 3x4).
        $jpeg = ImageFixtures::jpeg(ImageFixtures::opaque(4, 3));
        $rotated = ImageFixtures::insertJpegSegment($jpeg, 0xE1, ImageFixtures::exif(6, 'note'));

        $stored = $this->uploadAndRead('photo.jpg', $rotated, 'jpg');

        $this->assertSame([3, 4], $this->dimensions($stored));
    }

    public function test_png_transparency_is_preserved(): void
    {
        $stored = $this->uploadAndRead('photo.png', ImageFixtures::png(ImageFixtures::halfTransparent()), 'png');

        $this->assertSame(6, ord($stored[25]), 'PNG colour type 6 (truecolour with alpha) expected.');
        $this->assertAlpha($stored, opaque: [0, 0], transparent: [1, 0]);
    }

    public function test_palette_png_transparency_is_preserved(): void
    {
        $palette = imagecreate(2, 1);
        $this->assertInstanceOf(GdImage::class, $palette);
        imagecolorallocate($palette, 200, 0, 0);
        $clear = (int) imagecolorallocate($palette, 0, 200, 0);
        imagecolortransparent($palette, $clear);
        imagesetpixel($palette, 1, 0, $clear);

        $stored = $this->uploadAndRead('photo.png', ImageFixtures::png($palette), 'png');

        $this->assertAlpha($stored, opaque: [0, 0], transparent: [1, 0]);
    }

    public function test_webp_transparency_is_preserved(): void
    {
        $stored = $this->uploadAndRead('photo.webp', ImageFixtures::webp(ImageFixtures::halfTransparent()), 'webp');

        $this->assertAlpha($stored, opaque: [0, 0], transparent: [1, 0]);
    }

    public function test_image_that_gd_cannot_decode_is_rejected_with_422(): void
    {
        // PNG signature and header only: the type and dimensions can be read,
        // so validation passes, but there is no pixel data to decode.
        $truncated = substr(ImageFixtures::png(ImageFixtures::opaque(4, 3)), 0, 33);

        $this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent('broken.png', $truncated),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file could not be read as an image.']);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function uploadAndRead(string $name, string $contents, string $extension): string
    {
        $path = $this->jsonString($this->postJson('/api/product/upload', [
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
        ])->assertOk(), 'data.path');

        $this->assertMatchesRegularExpression('~^uploads/[A-Za-z0-9]{40}\.'.$extension.'$~', $path);

        return (string) Storage::disk('public')->get($path);
    }

    /**
     * @return array{int, int}
     */
    private function dimensions(string $image): array
    {
        $size = getimagesizefromstring($image);
        $this->assertIsArray($size);

        return [$size[0], $size[1]];
    }

    /**
     * @param  array{int, int}  $opaque
     * @param  array{int, int}  $transparent
     */
    private function assertAlpha(string $image, array $opaque, array $transparent): void
    {
        $decoded = imagecreatefromstring($image);
        $this->assertInstanceOf(GdImage::class, $decoded);

        // GD alpha: 0 = fully opaque, 127 = fully transparent.
        $this->assertSame(0, (imagecolorat($decoded, ...$opaque) >> 24) & 0x7F);
        $this->assertSame(127, (imagecolorat($decoded, ...$transparent) >> 24) & 0x7F);
    }
}
