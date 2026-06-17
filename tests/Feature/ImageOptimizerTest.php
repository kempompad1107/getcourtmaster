<?php

namespace Tests\Feature;

use App\Services\ImageOptimizer;
use App\Services\ImageProfile;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Exercises the centralised optimiser directly — no database, so this is
 * unaffected by the MySQL-only migration that breaks the sqlite test suite.
 */
class ImageOptimizerTest extends TestCase
{
    private function makeImage(string $ext, int $width, int $height): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        // Some non-uniform content so the encoder has real work to do.
        imagefilledrectangle($img, 0, 0, (int) ($width / 2), $height, imagecolorallocate($img, 200, 30, 30));
        imagefilledrectangle($img, (int) ($width / 2), 0, $width, $height, imagecolorallocate($img, 30, 30, 200));

        $path = tempnam(sys_get_temp_dir(), 'src_') . '.' . $ext;
        match ($ext) {
            'png'  => imagepng($img, $path),
            'webp' => imagewebp($img, $path),
            default => imagejpeg($img, $path, 95),
        };
        imagedestroy($img);

        return new UploadedFile($path, "source.$ext", null, null, true);
    }

    public function test_oversized_jpeg_is_scaled_down_and_kept_as_jpeg(): void
    {
        $optimizer = app(ImageOptimizer::class);

        $result = $optimizer->optimize($this->makeImage('jpg', 4000, 3000), ImageProfile::default());

        $this->assertNotNull($result);
        $this->assertSame('jpg', $result->extension);

        [$w, $h] = getimagesizefromstring($result->contents);
        $this->assertLessThanOrEqual(1920, $w);
        $this->assertLessThanOrEqual(1920, $h);
        // Aspect ratio preserved (4:3).
        $this->assertEqualsWithDelta(4 / 3, $w / $h, 0.02);
    }

    public function test_png_is_converted_to_webp_to_preserve_transparency(): void
    {
        $optimizer = app(ImageOptimizer::class);

        $result = $optimizer->optimize($this->makeImage('png', 800, 600), ImageProfile::default());

        $this->assertNotNull($result);
        $this->assertSame('webp', $result->extension);
        $this->assertSame('image/webp', $result->mimeType);
    }

    public function test_non_image_uploads_pass_through_untouched(): void
    {
        $optimizer = app(ImageOptimizer::class);

        $pdf = UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf');

        $this->assertNull($optimizer->optimize($pdf, ImageProfile::receipt()));
    }

    public function test_master_switch_disables_optimisation(): void
    {
        config(['images.optimize' => false]);
        $optimizer = app(ImageOptimizer::class);

        $this->assertNull($optimizer->optimize($this->makeImage('jpg', 4000, 3000)));
    }
}
