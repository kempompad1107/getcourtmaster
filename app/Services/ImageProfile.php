<?php

namespace App\Services;

/**
 * Immutable description of how a class of images should be optimised.
 *
 * Profiles are defined in config/images.php so the rules can be tuned without
 * touching code. Resolve one with {@see ImageProfile::named()} (or the
 * convenience constructors) and hand it to {@see ImageOptimizer} /
 * {@see FileStorageService::uploadFile()}.
 */
final class ImageProfile
{
    public function __construct(
        public readonly int $maxWidth,
        public readonly int $maxHeight,
        public readonly int $quality,
        /** "auto" | "webp" | "jpg" | "png" */
        public readonly string $format,
    ) {}

    /**
     * Build a profile from a config/images.php key, falling back to "default".
     */
    public static function named(string $name): self
    {
        $config = config("images.profiles.$name")
            ?? config('images.profiles.default');

        return new self(
            maxWidth:  (int) ($config['max_width'] ?? 1920),
            maxHeight: (int) ($config['max_height'] ?? 1920),
            quality:   (int) ($config['quality'] ?? 82),
            format:    (string) ($config['format'] ?? 'auto'),
        );
    }

    /** General imagery (avatars, products, logos, gym photos…). */
    public static function default(): self
    {
        return self::named('default');
    }

    /** Receipt / payment-proof scans — tuned for text legibility. */
    public static function receipt(): self
    {
        return self::named('receipt');
    }
}
