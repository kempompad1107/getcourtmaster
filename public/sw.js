const CACHE_NAME = 'courtmaster-v2';

// Resolve URLs relative to where the service worker is installed (e.g.
// /courtmaster/public/) instead of the domain root. Works under any subdirectory.
const BASE = new URL('.', self.location).pathname;
const OFFLINE_URL = BASE + 'offline';

const STATIC_ASSETS = [
    BASE,
    BASE + 'offline',
    BASE + 'manifest.json',
];

// Install: cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch strategy: network-first with offline fallback
self.addEventListener('fetch', (event) => {
    // Skip non-GET requests and cross-origin requests
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith(self.location.origin)) return;

    // Skip API requests (always network)
    if (event.request.url.includes('/api/')) return;

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Cache successful HTML responses for offline fallback
                if (response.ok && event.request.headers.get('accept')?.includes('text/html')) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(async () => {
                // Try cache, then offline page
                const cached = await caches.match(event.request);
                if (cached) return cached;

                if (event.request.headers.get('accept')?.includes('text/html')) {
                    return caches.match(OFFLINE_URL);
                }
            })
    );
});

// Background sync: queue failed booking requests
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-bookings') {
        event.waitUntil(syncPendingBookings());
    }
});

async function syncPendingBookings() {
    const db = await openDB();
    const pending = await db.getAll('pending_bookings');
    for (const booking of pending) {
        try {
            const res = await fetch('/api/v1/bookings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${booking.token}` },
                body: JSON.stringify(booking.data),
            });
            if (res.ok) {
                await db.delete('pending_bookings', booking.id);
            }
        } catch {}
    }
}

// Push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? { title: 'CourtMaster', body: 'New notification' };
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-96x96.png',
            data: { url: data.url ?? '/admin/dashboard' },
            actions: data.actions ?? [],
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data.url));
});

// Simple IndexedDB wrapper
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('courtmaster', 1);
        req.onupgradeneeded = (e) => {
            e.target.result.createObjectStore('pending_bookings', { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = (e) => {
            const db = e.target.result;
            resolve({
                getAll: (store) => new Promise((res, rej) => {
                    const tx = db.transaction(store, 'readonly');
                    const req = tx.objectStore(store).getAll();
                    req.onsuccess = () => res(req.result);
                    req.onerror = () => rej(req.error);
                }),
                delete: (store, id) => new Promise((res, rej) => {
                    const tx = db.transaction(store, 'readwrite');
                    const req = tx.objectStore(store).delete(id);
                    req.onsuccess = () => res();
                    req.onerror = () => rej(req.error);
                }),
            });
        };
        req.onerror = () => reject(req.error);
    });
}
