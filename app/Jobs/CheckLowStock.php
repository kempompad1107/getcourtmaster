<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckLowStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InventoryService $inventory): void
    {
        Tenant::query()->each(function (Tenant $tenant) use ($inventory) {
            // Respect the tenant's "Low stock alert" notification toggle.
            if (!$tenant->wantsNotification('notify_low_stock')) return;

            $products = $inventory->lowStockForTenant($tenant->id);
            if ($products->isEmpty()) return;

            $owners = User::where('tenant_id', $tenant->id)
                ->whereIn('user_type', ['business_owner', 'staff'])
                ->where('is_active', true)
                ->get();

            foreach ($owners as $owner) {
                $owner->notify(new LowStockNotification($products));
            }
        });
    }
}
