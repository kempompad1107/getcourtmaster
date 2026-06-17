<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemaphoreSmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }
        /** @var SmsMessage $message */
        $message = $notification->toSms($notifiable);
        $phone = $this->resolvePhone($notifiable);
        if (!$phone || !$message?->text) {
            return;
        }

        $apiKey = config('services.semaphore.api_key');
        $sender = config('services.semaphore.sender_name', 'CourtMaster');
        if (!$apiKey) {
            Log::warning('Semaphore SMS skipped — no API key configured', ['to' => $phone]);
            return;
        }

        $response = Http::asForm()->timeout(10)->post('https://api.semaphore.co/api/v4/messages', [
            'apikey'     => $apiKey,
            'number'     => $phone,
            'message'    => $message->text,
            'sendername' => $sender,
        ]);

        if (!$response->successful()) {
            Log::warning('Semaphore SMS failed', ['status' => $response->status(), 'body' => $response->body()]);
        }
    }

    private function resolvePhone(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $val = $notifiable->routeNotificationFor('sms', null);
            if (!empty($val)) return $val;
        }
        return $notifiable->phone ?? null;
    }
}
