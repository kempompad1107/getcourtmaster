<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generate(string $data, int $size = 240): string
    {
        $svg = QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($data);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function verify(string $qrPayload, string $bookingNumber): bool
    {
        return trim($qrPayload) === $bookingNumber;
    }
}
