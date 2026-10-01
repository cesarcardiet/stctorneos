const CACHE = 'stc-pwa-v1';
const PRECACHE = [
    '/offline.html',
    '/icons/stc-icon.svg',
    '/icons/stc-192.png',
    '/icons/stc-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline.html')))
        );
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const fetched = fetch(request).then((response) => {
                    if (response && response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
                    }
                    return response;
                }).catch(() => cached);
                return cached || fetched;
            })
        );
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response && response.ok && url.pathname.startsWith('/build/')) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
                }
                return response;
            })
            .catch(() => caches.match(request))
    );
});
