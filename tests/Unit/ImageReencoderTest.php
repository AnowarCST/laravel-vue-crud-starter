<?php

namespace Tests\Unit;

use App\Support\ImageReencoder;
use App\Support\UnreadableImageException;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImageFixtures;

class ImageReencoderTest extends TestCase
{
    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function orientations(): iterable
    {
        foreach (range(1, 8) as $orientation) {
            yield "big-endian {$orientation}" => [$orientation, false];
            yield "little-endian {$orientation}" => [$orientation, true];
        }
    }

    #[DataProvider('orientations')]
    public function test_reads_the_exif_orientation_of_a_jpeg(int $orientation, bool $littleEndian): void
    {
        $jpeg = ImageFixtures::insertJpegSegment(
            ImageFixtures::jpeg(ImageFixtures::opaque(4, 3)),
            0xE1,
            ImageFixtures::exif($orientation, 'note', $littleEndian),
        );

        $this->assertSame($orientation, ImageReencoder::jpegOrientation($jpeg));
    }

    public function test_missing_or_damaged_exif_means_no_rotation(): void
    {
        $jpeg = ImageFixtures::jpeg(ImageFixtures::opaque(4, 3));

        $this->assertSame(1, ImageReencoder::jpegOrientation($jpeg));
        $this->assertSame(1, ImageReencoder::jpegOrientation(''));
        $this->assertSame(1, ImageReencoder::jpegOrientation(
            ImageFixtures::insertJpegSegment($jpeg, 0xE1, "Exif\0\0MM\0*\xFF\xFF\xFF\xFF"),
        ));
        $this->assertSame(1, ImageReencoder::jpegOrientation(
            ImageFixtures::insertJpegSegment($jpeg, 0xE1, ImageFixtures::exif(9, 'out of range')),
        ));
    }

    public function test_every_orientation_produces_an_upright_image(): void
    {
        // Upright, the image is 16x8: a red left half and a blue right half.
        // Each case below is that image as a camera with the given EXIF
        // orientation would store it; after re-encoding it must be upright.
        // (8x8 blocks keep JPEG colour subsampling from mixing the colours.)
        $red = [255, 0, 0];
        $blue = [0, 0, 255];
        $stored = [
            1 => [$red, $blue],   // as is (16x8, left to right)
            2 => [$blue, $red],   // mirrored (16x8)
            3 => [$blue, $red],   // upside down (16x8)
            4 => [$red, $blue],   // mirrored vertically (16x8)
            5 => [$red, $blue],   // transposed (8x16, top to bottom)
            6 => [$blue, $red],   // turned 90 degrees anticlockwise (8x16)
            7 => [$blue, $red],   // transverse (8x16)
            8 => [$red, $blue],   // turned 90 degrees clockwise (8x16)
        ];

        foreach ($stored as $orientation => [$first, $second]) {
            $vertical = $orientation >= 5;
            $source = ImageFixtures::opaque($vertical ? 8 : 16, $vertical ? 16 : 8);
            imagefilledrectangle($source, 0, 0, 7, 7, (int) imagecolorallocate($source, ...$first));
            imagefilledrectangle($source, $vertical ? 0 : 8, $vertical ? 8 : 0, $vertical ? 7 : 15, $vertical ? 15 : 7,
                (int) imagecolorallocate($source, ...$second));

            $result = $this->reencode(ImageFixtures::insertJpegSegment(
                ImageFixtures::jpeg($source), 0xE1, ImageFixtures::exif($orientation, 'note'),
            ));

            $decoded = imagecreatefromstring($result['contents']);
            $this->assertNotFalse($decoded);
            $this->assertSame([16, 8], [imagesx($decoded), imagesy($decoded)], "orientation {$orientation}");
            $this->assertColorNear($red, $decoded, 4, 4, "orientation {$orientation}, left half");
            $this->assertColorNear($blue, $decoded, 12, 4, "orientation {$orientation}, right half");
        }
    }

    public function test_rejects_files_that_are_not_supported_images(): void
    {
        foreach (['', 'plain text', "GIF89a\x01\x00\x01\x00\x00\x00\x00;"] as $contents) {
            try {
                $this->reencode($contents);
                $this->fail('An exception was expected.');
            } catch (UnreadableImageException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @return array{contents: string, extension: string}
     */
    private function reencode(string $contents): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, $contents);

        try {
            return (new ImageReencoder)->reencode($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param  array{int, int, int}  $expected
     */
    private function assertColorNear(array $expected, GdImage $image, int $x, int $y, string $message): void
    {
        $rgb = imagecolorat($image, $x, $y);
        $actual = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];

        foreach ($expected as $i => $channel) {
            // JPEG is lossy: allow a small difference.
            $this->assertEqualsWithDelta($channel, $actual[$i], 40, $message);
        }
    }
}
