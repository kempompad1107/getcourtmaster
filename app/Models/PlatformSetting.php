<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Platform-wide (SaaS-level) key/value settings. Unlike TenantSetting these are
 * not scoped to any tenant — they configure the platform itself, e.g. the
 * PayMongo/Stripe credentials used to collect subscription dues from tenants.
 */
class PlatformSetting extends Model
{
    protected $table = 'platform_settings';
    protected $fillable = ['key', 'value'];

    public const PAYMENT_CREDENTIALS_KEY = 'payment_credentials';
    public const BRANDING_KEY = 'branding';

    /** Cache key for the branding blob — read on (almost) every page render. */
    private const BRANDING_CACHE = 'platform.branding';

    /** Read a JSON-encoded setting value. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();
        if (!$row || $row->value === null) {
            return $default;
        }
        $decoded = json_decode($row->value, true);
        return $decoded === null && $row->value !== 'null' ? $default : $decoded;
    }

    /** Write a JSON-encoded setting value (upsert). */
    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value)],
        );
    }

    /**
     * Platform PayMongo/Stripe credentials. The blob is encrypted at rest — it
     * holds live secret keys — mirroring Tenant::$casts['payment_credentials'].
     *
     * @return array{paymongo?: array, stripe?: array}
     */
    public static function paymentCredentials(): array
    {
        $row = static::query()->where('key', self::PAYMENT_CREDENTIALS_KEY)->first();
        if (!$row || $row->value === null) {
            return [];
        }
        try {
            $decrypted = Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return [];
        }
        $decoded = json_decode($decrypted, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function setPaymentCredentials(array $creds): void
    {
        static::query()->updateOrCreate(
            ['key' => self::PAYMENT_CREDENTIALS_KEY],
            ['value' => Crypt::encryptString(json_encode($creds))],
        );
    }

    /**
     * Platform branding — stored file paths for the logo and favicon shown
     * across the whole product (sidebar brand, browser tab icon). These are not
     * secret, so they're cached to avoid a query on every page render.
     *
     * @return array{logo?: string, favicon?: string}
     */
    public static function branding(): array
    {
        return Cache::rememberForever(
            self::BRANDING_CACHE,
            fn () => (array) static::get(self::BRANDING_KEY, []),
        );
    }

    public static function setBranding(array $branding): void
    {
        static::put(self::BRANDING_KEY, array_filter($branding));
        Cache::forget(self::BRANDING_CACHE);
    }
}
