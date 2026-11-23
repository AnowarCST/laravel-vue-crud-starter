<?php

namespace Tests\Support;

use GdImage;
use RuntimeException;

/**
 * Builds small test images (and hand-made metadata) with GD.
 */
final class ImageFixtures
{
    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public static function opaque(int $width, int $height): GdImage
    {
        $image = self::canvas($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 30, 120, 200));

        return $image;
    }

    /**
     * 2x1 image: pixel (0, 0) is opaque, pixel (1, 0) is fully transparent.
     */
    public static function halfTransparent(): GdImage
    {
        $image = self::canvas(2, 1);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 255, 0));
        imagesetpixel($image, 1, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));

        return $image;
    }

    public static function png(GdImage $image): string
    {
        return self::capture(fn () => imagepng($image));
    }

    public static function jpeg(GdImage $image): string
    {
        return self::capture(fn () => imagejpeg($image, null, 90));
    }

    public static function webp(GdImage $image): string
    {
        return self::capture(fn () => imagewebp($image, null, 90));
    }

    /**
     * A PNG chunk with a valid CRC.
     */
    public static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * Insert a segment (e.g. 0xE1 APP1 or 0xFE comment) right after the JPEG
     * start-of-image marker.
     *
     * @param  int<0, 255>  $marker
     */
    public static function insertJpegSegment(string $jpeg, int $marker, string $payload): string
    {
        return substr($jpeg, 0, 2)
            ."\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload
            .substr($jpeg, 2);
    }

    /**
     * Payload of an APP1 EXIF segment (big-endian TIFF) with an orientation
     * tag, an image description and a GPS block (latitude 52 deg 31' 12" N).
     */
    public static function exif(int $orientation, string $description, bool $littleEndian = false): string
    {
        $short = $littleEndian ? 'v' : 'n';
        $long = $littleEndian ? 'V' : 'N';
        $entry = fn (int $tag, int $type, int $count, string $value): string => pack($short, $tag).pack($short, $type).pack($long, $count).$value;

        $description .= "\0";
        $ifd0Offset = 8;
        $descriptionOffset = $ifd0Offset + 2 + 3 * 12 + 4;
        $gpsOffset = $descriptionOffset + strlen($description);
        $latitudeOffset = $gpsOffset + 2 + 2 * 12 + 4;

        $tiff = ($littleEndian ? 'II' : 'MM').pack($short, 42).pack($long, $ifd0Offset)
            // IFD0: ImageDescription (ASCII), Orientation (SHORT), GPS IFD pointer (LONG).
            .pack($short, 3)
            .$entry(0x010E, 2, strlen($description), pack($long, $descriptionOffset))
            .$entry(0x0112, 3, 1, pack($short, $orientation)."\0\0")
            .$entry(0x8825, 4, 1, pack($long, $gpsOffset))
            .pack($long, 0)
            .$description
            // GPS IFD: GPSLatitudeRef = "N", GPSLatitude = 52/1, 31/1, 12/1.
            .pack($short, 2)
            .$entry(0x0001, 2, 2, "N\0\0\0")
            .$entry(0x0002, 5, 3, pack($long, $latitudeOffset))
            .pack($long, 0)
            .pack($long.'6', 52, 1, 31, 1, 12, 1);

        return "Exif\0\0".$tiff;
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not create a test image.');
        }

        return $image;
    }

    /**
     * @param  callable(): bool  $write
     */
    private static function capture(callable $write): string
    {
        ob_start();
        $written = $write();
        $contents = (string) ob_get_clean();

        if (! $written || $contents === '') {
            throw new RuntimeException('GD could not encode a test image.');
        }

        return $contents;
    }
}
