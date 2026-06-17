<?php

namespace App\Notifications;

use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable, HonorsUserChannelPreferences;

    /** @param iterable<\App\Models\Product> $products */
    public function __construct(public readonly iterable $products) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable, ['in_app', 'email']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Low-stock alert');
        $mail->line('The following products are at or below their threshold:');
        foreach ($this->products as $p) {
            $mail->line("- {$p->name} (SKU {$p->sku}) — {$p->stock_quantity} left");
        }
        $mail->action('Open inventory', url('/admin/products'));
        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'  => 'low_stock',
            'count' => is_countable($this->products) ? count($this->products) : iterator_count($this->products),
        ];
    }
}
