<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Notifications\LowStockNotification;
use App\Services\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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

            if ($email = $tenant->notificationEmail()) {
                try {
                    $notification = new LowStockNotification($products);
                    Notification::route('mail', $email)->notify(new class($notification) extends \Illuminate\Notifications\Notification {
                        use \Illuminate\Bus\Queueable;
                        public function __construct(private readonly \Illuminate\Notifications\Notification $inner) {}
                        public function via(object $n): array { return ['mail']; }
                        public function toMail(object $n): mixed { return $this->inner->toMail($n); }
                    });
                } catch (\Throwable $e) {
                    Log::warning('notification_email CC failed (low stock)', ['email' => $email, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}