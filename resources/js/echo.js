// Laravel Echo client for Pusher.
// Loads at runtime; if the keys aren't set in env, Echo simply isn't initialized.

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_PUSHER_APP_KEY;
const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';
const host = import.meta.env.VITE_PUSHER_HOST;

if (key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        wsHost: host || `ws-${cluster}.pusher.com`,
        wsPort: import.meta.env.VITE_PUSHER_PORT || 80,
        wssPort: import.meta.env.VITE_PUSHER_PORT || 443,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        },
    });
} else {
    console.info('[Echo] Skipped — VITE_PUSHER_APP_KEY not configured.');
}
