<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Centralised, driver-agnostic file storage.
 *
 * Every upload, delete, replace and URL lookup in the application funnels
 * through this service so that switching the underlying storage backend
 * (local ➜ S3 / Vultr Object Storage / Cloudflare R2 / DigitalOcean Spaces)
 * is purely a configuration change — no application code needs to be touched.
 *
 * The active disk is resolved from `config('filesystems.default')`
 * (driven by the `FILESYSTEM_DISK` env var) unless a caller explicitly
 * overrides it. Nothing here hard-codes "/storage/" or a filesystem path.
 */
class FileStorageService
{
    /**
     * Canonical top-level folders. Keep all upload destinations here so the
     * bucket / disk layout stays predictable across every storage backend.
     */
    public const FOLDER_TENANTS  = 'tenants';
    public const FOLDER_PROFILES = 'profiles';
    public const FOLDER_COURTS   = 'courts';
    public const FOLDER_PRODUCTS = 'products';
    public const FOLDER_RECEIPTS = 'receipts';
    public const FOLDER_TOURNAMENTS = 'tournaments';

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    /**
     * Resolve a disk instance. Passing null uses the application default disk,
     * so the same code path works on local, public or any cloud driver.
     */
    public function disk(?string $disk = null): Filesystem
    {
        return Storage::disk($disk ?? config('filesystems.default'));
    }

    /**
     * Store an uploaded file inside $folder and return the stored path
     * (relative to the disk root). The path — never a full filesystem path —
     * is what you persist on the model.
     *
     * @param  string|null  $filename  Optional explicit name (extension is
     *                                  preserved automatically); a hashed,
     *                                  collision-safe name is generated when omitted.
     * @param  ImageProfile|null  $imageProfile  Optimisation rules for image
     *                                  uploads. Defaults to the general profile;
     *                                  pass {@see ImageProfile::receipt()} for
     *                                  scans, or use the master switch in
     *                                  config/images.php to disable entirely.
     *                                  Non-image files always pass through as-is.
     */
    public function uploadFile(
        UploadedFile $file,
        string $folder,
        ?string $disk = null,
        ?string $filename = null,
        ?ImageProfile $imageProfile = null,
    ): string {
        $folder = trim($folder, '/');

        // Images are resized, compressed and stripped of EXIF before they ever
        // touch the disk; everything else is stored byte-for-byte.
        $optimized = $this->optimizer->optimize($file, $imageProfile ?? ImageProfile::default());
        if ($optimized !== null) {
            $name = $filename !== null
                ? $this->withExtension($filename, $optimized->extension)
                : Str::random(40) . '.' . $optimized->extension;

            $path = $folder . '/' . $name;
            $this->disk($disk)->put($path, $optimized->contents);

            return $path;
        }

        if ($filename !== null) {
            $filename = $this->normaliseFilename($filename, $file);

            return $this->disk($disk)->putFileAs($folder, $file, $filename);
        }

        // Hashed name => avoids collisions and leaking original filenames.
        return $this->disk($disk)->putFile($folder, $file);
    }

    /**
     * Delete a file if it exists. Safe to call with null / empty paths and
     * never throws when the file is already gone — returns whether something
     * was actually removed.
     */
    public function deleteFile(?string $path, ?string $disk = null): bool
    {
        if (! $this->exists($path, $disk)) {
            return false;
        }

        return $this->disk($disk)->delete($path);
    }

    /**
     * Replace a file: store $file, then delete $oldPath. When no new file is
     * supplied the old path is returned untouched, so this is safe to call
     * unconditionally from "update" flows.
     *
     * @return string|null  The new stored path, or the old one when no upload.
     */
    public function replaceFile(
        ?UploadedFile $file,
        ?string $oldPath,
        string $folder,
        ?string $disk = null,
        ?string $filename = null,
        ?ImageProfile $imageProfile = null,
    ): ?string {
        if ($file === null) {
            return $oldPath;
        }

        $newPath = $this->uploadFile($file, $folder, $disk, $filename, $imageProfile);

        if ($oldPath && $oldPath !== $newPath) {
            $this->deleteFile($oldPath, $disk);
        }

        return $newPath;
    }

    /**
     * Public URL for a stored path via the disk's own URL generator
     * (`Storage::url()` semantics). Returns null for empty paths and passes
     * through values that are already absolute URLs — e.g. avatars sourced
     * from a social login provider.
     */
    public function getFileUrl(?string $path, ?string $disk = null): ?string
    {
        if (empty($path)) {
            return null;
        }

        if ($this->isExternalUrl($path)) {
            return $path;
        }

        return $this->disk($disk)->url($path);
    }

    /**
     * Existence check using the Storage facade (works on every driver).
     */
    public function exists(?string $path, ?string $disk = null): bool
    {
        if (empty($path) || $this->isExternalUrl($path)) {
            return false;
        }

        return $this->disk($disk)->exists($path);
    }

    private function isExternalUrl(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function normaliseFilename(string $filename, UploadedFile $file): string
    {
        // Honour a caller-supplied name but guarantee a sensible extension.
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $ext = $file->getClientOriginalExtension() ?: $file->extension();
            if ($ext) {
                $filename .= '.' . $ext;
            }
        }

        return $filename;
    }

    /**
     * Swap a filename's extension for the one the optimiser actually produced
     * (e.g. a PNG re-encoded to WebP must be stored as ".webp").
     */
    private function withExtension(string $filename, string $extension): string
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '.' . $extension;
    }
}
