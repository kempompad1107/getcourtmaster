<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Per-tenant cache facade for dashboard + revenue queries.
 *
 * Wraps Cache::remember() and tracks every key it stores in a tenant-scoped
 * index, so a single Payment event can invalidate every cached revenue/
 * dashboard query for that tenant in one call — even when the cache driver
 * (database) does not support tags.
 *
 * Tenant 0 is reserved for platform-wide ("super admin") entries.
 */
class DashboardCache
{
    private const INDEX_PREFIX = 'dashboard_index';
    private const INDEX_TTL    = 86400; // 24h — long enough to outlive any tracked entry

    public function remember(int $tenantId, string $key, int $ttl, Closure $callback): mixed
    {
        $this->trackKey($tenantId, $key);
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Forget every key tracked under this tenant. Called from PaymentObserver
     * whenever a Payment row is created, updated, or deleted so dashboard +
     * revenue reports reflect new money immediately.
     */
    public function invalidateTenant(int $tenantId): void
    {
        $indexKey = $this->indexKey($tenantId);
        $keys = Cache::get($indexKey, []);
        foreach ($keys as $k) {
            Cache::forget($k);
        }
        Cache::forget($indexKey);
    }

    private function trackKey(int $tenantId, string $key): void
    {
        $indexKey = $this->indexKey($tenantId);
        $keys = Cache::get($indexKey, []);
        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($indexKey, $keys, self::INDEX_TTL);
        }
    }

    private function indexKey(int $tenantId): string
    {
        return self::INDEX_PREFIX . ".{$tenantId}";
    }
}
