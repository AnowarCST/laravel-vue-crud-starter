<?php

namespace App\Actions\Products;

use App\Support\ImageReencoder;
use App\Support\UnreadableImageException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores a re-encoded copy of an uploaded product image on the "public"
 * disk and returns its path, for example uploads/<40 letters and digits>.png.
 *
 * The uploaded bytes are never stored: metadata (EXIF/GPS) and anything
 * hidden in or appended to the file is dropped. The format, and so the
 * extension, is detected from the file contents, never taken from the
 * client-supplied name or extension.
 */
final class StoreProductImage
{
    /**
     * Every path this action can return: Str::random(40) plus the extension
     * of the detected format (see ImageReencoder::EXTENSIONS).
     */
    public const PATH_PATTERN = '~^uploads/[A-Za-z0-9]{40}\.(jpg|jpeg|png|webp)$~';

    public function __construct(private readonly ImageReencoder $reencoder) {}

    /**
     * @throws UnreadableImageException when the file cannot be decoded as a supported image.
     */
    public function handle(UploadedFile $file): string
    {
        $image = $this->reencoder->reencode((string) $file->getRealPath());

        $path = 'uploads/'.Str::random(40).'.'.$image['extension'];

        if (! Storage::disk('public')->put($path, $image['contents'])) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        return $path;
    }
}
