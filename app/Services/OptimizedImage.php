<?php

namespace App\Services;

/**
 * The result of optimising an image: the re-encoded binary together with the
 * file extension and MIME type that match the chosen output format.
 */
final class OptimizedImage
{
    public function __construct(
        public readonly string $contents,
        public readonly string $extension,
        public readonly string $mimeType,
    ) {}
}
