const CACHE_VERSION = 'migraineai-v2';
const CACHE_ASSETS = ['/logo-icon.png', '/manifest.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(CACHE_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) =>
                Promise.all(
                    cacheNames
                        .filter((cacheName) => cacheName !== CACHE_VERSION)
                        .map((cacheName) => caches.delete(cacheName))
                )
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const isHttp = /^https?:/i.test(req.url);
    const isHTML = req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html');

    // Never cache HTML/navigation requests (avoids stale pages / 409 conflicts)
    if (isHTML || !isHttp) {
        event.respondWith(fetch(req));
        return;
    }

    // Cache-first for static assets
    event.respondWith(
        caches.match(req).then((cachedResponse) => {
            if (cachedResponse) return cachedResponse;

            return fetch(req)
                .then((networkResponse) => {
                    const clone = networkResponse.clone();
                    caches.open(CACHE_VERSION).then((cache) => cache.put(req, clone));
                    return networkResponse;
                })
                .catch(() => cachedResponse);
        })
    );
});
