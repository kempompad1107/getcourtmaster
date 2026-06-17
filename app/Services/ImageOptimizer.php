<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Centralised image optimisation.
 *
 * Every image upload in the application is funnelled through here (via
 * {@see FileStorageService}) so the resize / compress / re-encode / strip-EXIF
 * rules live in exactly one place. Non-image uploads (PDFs, SVGs, anything we
 * can't safely raster) and images that fail to decode are reported as
 * "not optimisable" so callers transparently fall back to storing the original.
 */
class ImageOptimizer
{
    /**
     * MIME types we will decode and re-encode. SVG (vector), PDF and exotic
     * formats are deliberately excluded and pass through untouched.
     */
    private const PROCESSABLE = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly ImageManager $manager) {}

    /**
     * Optimise an uploaded image.
     *
     * @return OptimizedImage|null  Null when the file isn't a processable image
     *                              or optimisation is disabled/failed — the
     *                              caller should then store the file as-is.
     */
    public function optimize(UploadedFile $file, ?ImageProfile $profile = null): ?OptimizedImage
    {
        if (! config('images.optimize', true)) {
            return null;
        }

        $path = $file->getRealPath();
        if ($path === false || ! $this->isProcessable($file, $path)) {
            return null;
        }

        try {
            $image = $this->manager->read($path);

            // Only ever scale DOWN: small images are left at their native size.
            $image->scaleDown($profile?->maxWidth, $profile?->maxHeight);

            return $this->encode($image, $profile ?? ImageProfile::default(), $file);
        } catch (Throwable $e) {
            // A corrupt or unsupported image must never break the upload flow.
            report($e);

            return null;
        }
    }

    /**
     * Produce an {@see UploadedFile} backed by the optimised bytes, for callers
     * that must hand a file object to another subsystem (e.g. Spatie Media
     * Library). Falls back to the original file when not optimisable.
     */
    public function optimizedUpload(UploadedFile $file, ?ImageProfile $profile = null): UploadedFile
    {
        $optimized = $this->optimize($file, $profile);
        if ($optimized === null) {
            return $file;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'imgopt_');
        file_put_contents($tmp, $optimized->contents);

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
            . '.' . $optimized->extension;

        // $test = true => skip is_uploaded_file() checks for this synthetic file.
        return new UploadedFile($tmp, $name, $optimized->mimeType, null, true);
    }

    private function isProcessable(UploadedFile $file, string $path): bool
    {
        // getMimeType() sniffs magic bytes, not the (spoofable) client header.
        if (! in_array($file->getMimeType(), self::PROCESSABLE, true)) {
            return false;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return false;
        }

        // Decompression-bomb guard: refuse to decode absurdly large canvases.
        $maxPixels = (int) config('images.max_megapixels', 64) * 1_000_000;

        return ($info[0] * $info[1]) <= $maxPixels;
    }

    private function encode(ImageInterface $image, ImageProfile $profile, UploadedFile $file): OptimizedImage
    {
        $format = $profile->format === 'auto'
            ? $this->autoFormat($file)
            : $profile->format;

        return match ($format) {
            'png'  => new OptimizedImage((string) $image->toPng(), 'png', 'image/png'),
            'jpg'  => new OptimizedImage((string) $image->toJpeg($profile->quality), 'jpg', 'image/jpeg'),
            default => new OptimizedImage((string) $image->toWebp($profile->quality), 'webp', 'image/webp'),
        };
    }

    /**
     * Keep JPEGs as JPEG; send PNG/WebP (which may carry transparency) to WebP
     * so the alpha channel survives while the file still shrinks.
     */
    private function autoFormat(UploadedFile $file): string
    {
        return $file->getMimeType() === 'image/jpeg' ? 'jpg' : 'webp';
    }
}
