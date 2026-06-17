<?php

use App\Services\FileStorageService;

if (! function_exists('file_storage')) {
    /**
     * Resolve the shared, driver-agnostic file storage service.
     */
    function file_storage(): FileStorageService
    {
        return app(FileStorageService::class);
    }
}

if (! function_exists('file_url')) {
    /**
     * Public URL for a stored path through the active storage disk.
     * Returns null for empty paths and passes absolute URLs through unchanged.
     */
    function file_url(?string $path, ?string $disk = null): ?string
    {
        return file_storage()->getFileUrl($path, $disk);
    }
}
