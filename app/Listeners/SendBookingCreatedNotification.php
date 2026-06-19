<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\Tenant;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SendBookingCreatedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Notify the customer
        $booking->customer->notify(new BookingCreatedNotification($booking));

        // Notify the admin via notification_email (gated by toggle)
        $tenant = Tenant::find($booking->tenant_id);
        if (!$tenant || !$tenant->wantsNotification('notify_new_booking')) {
            return;
        }
        if ($email = $tenant->notificationEmail()) {
            try {
                $notification = new BookingCreatedNotification($booking);
                Notification::route('mail', $email)->notify(new class($notification) extends Notification {
                    use \Illuminate\Bus\Queueable;
                    public function __construct(private readonly Notification $inner) {}
                    public function via(object $n): array { return ['mail']; }
                    public function toMail(object $n): mixed { return $this->inner->toMail($n); }
                });
            } catch (\Throwable $e) {
                Log::warning('notification_email failed (new booking)', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }
    }
}
