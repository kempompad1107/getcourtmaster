<?php

namespace App\Services\TwoFactor;

use Illuminate\Support\Str;

/**
 * RFC 6238 TOTP implementation (compatible with Google Authenticator / Authy).
 * 6-digit codes, 30-second window, SHA1.
 */
class TotpService
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function generateSecret(int $length = 20): string
    {
        return $this->base32Encode(random_bytes($length));
    }

    /**
     * otpauth URI for QR code generation.
     */
    public function provisioningUri(string $secret, string $accountLabel, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountLabel);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a user-supplied code against the secret. ±1 step tolerance.
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }
        $time = (int) floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->compute($secret, $time + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtolower(Str::random(5)) . '-' . strtolower(Str::random(5));
        }
        return $codes;
    }

    private function compute(string $secret, int $counter): string
    {
        $binSecret = $this->base32Decode($secret);
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binCounter, $binSecret, true);

        $offset = ord($hash[19]) & 0x0F;
        $value = (
            (ord($hash[$offset]) & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $modulo = 10 ** self::DIGITS;
        return str_pad((string) ($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $bits = '';
        foreach (str_split($data) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $bits = str_pad($bits, ((int) ceil(strlen($bits) / 5)) * 5, '0', STR_PAD_RIGHT);
        foreach (str_split($bits, 5) as $chunk) {
            $output .= $alphabet[bindec($chunk)];
        }
        return $output;
    }

    private function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(trim($b32, "= \t\n\r\0\x0B"));
        $bits = '';
        foreach (str_split($b32) as $ch) {
            $idx = strpos($alphabet, $ch);
            if ($idx === false) continue;
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }
}
