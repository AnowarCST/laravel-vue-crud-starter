<?php

namespace App\Support;

use GdImage;

/**
 * Decodes an uploaded image with GD and encodes a fresh copy in the same
 * format. Only the decoded pixels are written back, so anything else in the
 * original file is dropped: metadata (EXIF including GPS position, XMP, IPTC,
 * comments), embedded payloads such as PHP or HTML in ancillary chunks or
 * comments, and data appended after the end of the image.
 *
 * Transparency is kept for PNG and WebP. The EXIF orientation of a JPEG is
 * applied to the pixels before the metadata is discarded, so photos taken
 * with a rotated camera are still displayed the right way up.
 */
class ImageReencoder
{
    /**
     * Supported formats (IMAGETYPE_* constants) and the file extension used
     * when storing them.
     */
    public const EXTENSIONS = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const JPEG_QUALITY = 90;

    private const WEBP_QUALITY = 90;

    /**
     * The format is detected from the file contents; the file name and any
     * client-supplied MIME type are ignored.
     *
     * @param  string  $path  Local path of the uploaded file.
     * @return array{contents: string, extension: string}
     *
     * @throws UnreadableImageException when the file is not a supported image
     *                                  or GD cannot decode or encode it.
     */
    public function reencode(string $path): array
    {
        $info = $this->withoutWarnings(fn () => getimagesize($path));
        $extension = is_array($info) ? (self::EXTENSIONS[$info[2]] ?? null) : null;

        if ($extension === null) {
            throw new UnreadableImageException('The file is not a supported image.');
        }

        $image = $this->withoutWarnings(fn () => match ($extension) {
            'jpg' => imagecreatefromjpeg($path),
            'png' => imagecreatefrompng($path),
            'webp' => imagecreatefromwebp($path),
        });

        if (! $image instanceof GdImage) {
            throw new UnreadableImageException('The image could not be decoded.');
        }

        if ($extension === 'jpg') {
            $image = $this->applyJpegOrientation($image, $path);
        }

        if (! imageistruecolor($image)) {
            // Palette images (common for PNG) are converted so that a
            // transparent palette entry becomes a fully transparent pixel.
            imagepalettetotruecolor($image);
        }

        return ['contents' => $this->encode($image, $extension), 'extension' => $extension];
    }

    private function encode(GdImage $image, string $extension): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new UnreadableImageException('Could not open a temporary stream.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            $written = $this->withoutWarnings(fn () => match ($extension) {
                'jpg' => imagejpeg($image, $stream, self::JPEG_QUALITY),
                'png' => imagepng($image, $stream),
                'webp' => imagewebp($image, $stream, self::WEBP_QUALITY),
                default => false,
            });

            $contents = $written === true && rewind($stream) ? stream_get_contents($stream) : false;
        } finally {
            fclose($stream);
        }

        if (! is_string($contents) || $contents === '') {
            throw new UnreadableImageException('The image could not be encoded.');
        }

        return $contents;
    }

    /**
     * Rotate and/or mirror the pixels as described by the EXIF orientation
     * tag (values 1 to 8), because the tag itself is not kept.
     */
    private function applyJpegOrientation(GdImage $image, string $path): GdImage
    {
        $bytes = file_get_contents($path, length: 256 * 1024);
        $orientation = is_string($bytes) ? self::jpegOrientation($bytes) : 1;

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        // imagerotate() turns the image counter-clockwise by the given angle.
        $angle = match ($orientation) {
            3 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Read the orientation tag from the EXIF block of a JPEG file without
     * requiring the exif extension. Returns 1 (no change) when the file has
     * no EXIF block or the tag is missing or invalid.
     */
    public static function jpegOrientation(string $jpeg): int
    {
        $length = strlen($jpeg);
        $offset = 2; // Skip the start-of-image marker.

        while ($offset + 4 <= $length && $jpeg[$offset] === "\xFF") {
            $marker = ord($jpeg[$offset + 1]);

            // Metadata segments always come before the start of scan.
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $size = self::readInt('n', $jpeg, $offset + 2);
            if ($size < 2) {
                break;
            }

            if ($marker === 0xE1 && substr($jpeg, $offset + 4, 6) === "Exif\0\0") {
                return self::tiffOrientation(substr($jpeg, $offset + 10, $size - 8));
            }

            $offset += 2 + $size;
        }

        return 1;
    }

    private static function tiffOrientation(string $tiff): int
    {
        [$short, $long] = match (substr($tiff, 0, 2)) {
            'II' => ['v', 'V'], // little-endian
            'MM' => ['n', 'N'], // big-endian
            default => [null, null],
        };

        if ($short === null || $long === null || strlen($tiff) < 8) {
            return 1;
        }

        $ifd = self::readInt($long, $tiff, 4);
        $entries = self::readInt($short, $tiff, $ifd);

        for ($i = 0; $i < $entries; $i++) {
            $entry = $ifd + 2 + $i * 12;

            if ($entry + 12 > strlen($tiff)) {
                break;
            }

            if (self::readInt($short, $tiff, $entry) === 0x0112) {
                $value = self::readInt($short, $tiff, $entry + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /**
     * Unpack one integer, returning -1 when the data is too short.
     */
    private static function readInt(string $format, string $data, int $offset): int
    {
        $size = in_array($format, ['n', 'v'], true) ? 2 : 4;

        if ($offset < 0 || $offset + $size > strlen($data)) {
            return -1;
        }

        $value = unpack($format, $data, $offset);

        return is_array($value) && is_int($value[1]) ? $value[1] : -1;
    }

    /**
     * GD reports damaged input as PHP warnings, which Laravel would turn into
     * exceptions. Failures are detected from the return value instead.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withoutWarnings(callable $callback): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
