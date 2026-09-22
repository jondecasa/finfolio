/* Finfolio service worker — minimal, safe.
 * - App/Vite assets (hashed): cache-first.
 * - Navigations: network-first, fall back to a cached page when offline.
 * - Everything else: straight to network.
 * Bump CACHE to invalidate on deploy.
 */
const CACHE = 'finfolio-v2';
const OFFLINE_URL = '/offline';
const PRECACHE = ['/offline', '/favicon.svg', '/icon.svg', '/apple-touch-icon.png', '/favicon.ico', '/site.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).catch(() => {})
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Hashed build assets & icons — cache-first.
    if (url.pathname.startsWith('/build/') || /\.(?:png|svg|ico|woff2?)$/.test(url.pathname)) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((res) => {
                        const copy = res.clone();
                        caches.open(CACHE).then((c) => c.put(request, copy));
                        return res;
                    })
            )
        );
        return;
    }

    // Page navigations — network-first with offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL))
            )
        );
        return;
    }
});

// Price alert push notifications.
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Finfolio', {
            body: data.body || '',
            icon: '/apple-touch-icon.png',
            badge: '/favicon.ico',
            data: { url: data.url || '/' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const existing = clients.find((c) => c.url === url && 'focus' in c);
            return existing ? existing.focus() : self.clients.openWindow(url);
        })
    );
});
