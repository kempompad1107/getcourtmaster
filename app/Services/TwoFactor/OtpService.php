<?php

namespace App\Services\TwoFactor;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(private readonly int $codeLength = 6, private readonly int $ttlMinutes = 10) {}

    /**
     * Issue a fresh 6-digit OTP for an identifier (email or phone) and purpose.
     * Returns the plaintext code for delivery via Notification / SMS / Mailer.
     */
    public function issue(string $identifier, string $channel = 'email', string $purpose = 'login', ?User $user = null, ?string $ip = null): array
    {
        OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 10 ** $this->codeLength - 1), $this->codeLength, '0', STR_PAD_LEFT);

        $record = OtpCode::create([
            'user_id'    => $user?->id,
            'identifier' => $identifier,
            'channel'    => $channel,
            'code_hash'  => Hash::make($code),
            'purpose'    => $purpose,
            'expires_at' => now()->addMinutes($this->ttlMinutes),
            'ip'         => $ip,
        ]);

        return ['code' => $code, 'record' => $record, 'expires_at' => $record->expires_at];
    }

    /**
     * Verify a user-supplied OTP. Returns the matching OtpCode on success, null on failure.
     */
    public function verify(string $identifier, string $code, string $purpose = 'login'): ?OtpCode
    {
        $record = OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$record || $record->isExpired() || $record->attempts >= 5) {
            return null;
        }

        $record->increment('attempts');

        if (!Hash::check($code, $record->code_hash)) {
            return null;
        }

        $record->update(['used_at' => now()]);
        return $record;
    }
}
