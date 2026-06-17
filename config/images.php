<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When false, every upload is stored verbatim (no resize / re-encode).
    | Useful for debugging or environments without a working GD/Imagick build.
    */
    'optimize' => env('IMAGE_OPTIMIZE', true),

    /*
    |--------------------------------------------------------------------------
    | Intervention Image driver
    |--------------------------------------------------------------------------
    | "gd" works out of the box on virtually every PHP build (and is the only
    | one available on this XAMPP install). "imagick" gives slightly better
    | quality/format coverage where the extension is present.
    */
    'driver' => env('IMAGE_DRIVER', 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Decompression-bomb guard
    |--------------------------------------------------------------------------
    | Images whose pixel count exceeds this many megapixels are stored as-is
    | rather than decoded into memory, protecting the worker from OOM. The raw
    | byte size is already capped by per-field upload validation.
    */
    'max_megapixels' => (int) env('IMAGE_MAX_MEGAPIXELS', 64),

    /*
    |--------------------------------------------------------------------------
    | Optimisation profiles
    |--------------------------------------------------------------------------
    | Each profile bounds the longest edge, sets the encoder quality and picks
    | an output format:
    |   - "auto" keeps JPEGs as JPEG and converts PNG/WebP sources to WebP so
    |     transparency survives while size still drops.
    |   - "webp" / "jpg" / "png" force a specific format.
    | Re-encoding through GD inherently strips EXIF/GPS/camera metadata.
    */
    'profiles' => [

        // General imagery: avatars, product shots, logos, hero/branding, gym
        // (court) photos. Bias toward small files.
        'default' => [
            'max_width'  => 1920,
            'max_height' => 1920,
            'quality'    => 82,
            'format'     => 'auto',
        ],

        // Payment proof / receipt scans. Keep text legible: larger bounds and
        // higher quality, still far smaller than an untouched phone photo.
        'receipt' => [
            'max_width'  => 2200,
            'max_height' => 2200,
            'quality'    => 88,
            'format'     => 'auto',
        ],
    ],
];
