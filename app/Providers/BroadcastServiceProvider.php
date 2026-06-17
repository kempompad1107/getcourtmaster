<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Guard against a misconfigured broadcaster (e.g. Pusher creds missing)
        // taking down every HTTP request. Channel auth/registration is optional
        // for the app to function — only events that actually broadcast care.
        try {
            Broadcast::routes(['middleware' => ['web', 'auth']]);

            require base_path('routes/channels.php');
        } catch (\Throwable $e) {
            Log::warning('Broadcast channels could not be registered: ' . $e->getMessage());
        }
    }
}
