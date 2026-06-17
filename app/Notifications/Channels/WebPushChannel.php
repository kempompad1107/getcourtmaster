<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush')) {
            return;
        }

        $userId = $notifiable->id ?? null;
        if (!$userId) {
            return;
        }

        $subs = PushSubscription::where('user_id', $userId)->get();
        if ($subs->isEmpty()) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (!is_array($payload) || empty($payload['title'])) {
            return;
        }

        $driver = $this->resolveDriver();
        if (!$driver) {
            Log::info('WebPush skipped — driver not configured (set VAPID keys + install minishlink/web-push).', [
                'user_id'   => $userId,
                'sub_count' => $subs->count(),
            ]);
            return;
        }

        foreach ($subs as $sub) {
            try {
                $driver->send($sub, $payload);
                $sub->update(['last_sent_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('WebPush delivery failed', [
                    'sub_id' => $sub->id,
                    'error'  => $e->getMessage(),
                ]);
                if ($this->isGoneEndpoint($e)) {
                    $sub->delete();
                }
            }
        }
    }

    private function resolveDriver(): ?object
    {
        $vapidPublic  = config('services.webpush.public_key');
        $vapidPrivate = config('services.webpush.private_key');
        $vapidSubject = config('services.webpush.subject', 'mailto:noreply@example.com');

        if (!$vapidPublic || !$vapidPrivate) {
            return null;
        }
        if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
            return null;
        }

        return new class($vapidSubject, $vapidPublic, $vapidPrivate) {
            private \Minishlink\WebPush\WebPush $client;

            public function __construct(string $subject, string $pub, string $priv)
            {
                $this->client = new \Minishlink\WebPush\WebPush([
                    'VAPID' => ['subject' => $subject, 'publicKey' => $pub, 'privateKey' => $priv],
                ]);
            }

            public function send(PushSubscription $sub, array $payload): void
            {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]);
                $this->client->queueNotification($subscription, json_encode($payload));
                foreach ($this->client->flush() as $report) {
                    if (!$report->isSuccess()) {
                        throw new \RuntimeException($report->getReason());
                    }
                }
            }
        };
    }

    private function isGoneEndpoint(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), '410');
    }
}
