const CACHE_NAME = 'ghadams-pwa-v1';

const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.json',
    '/site.webmanifest',
    '/favicon.ico',
    '/favicon.svg',
    '/favicon-16x16.png',
    '/favicon-32x32.png',
    '/android-chrome-192x192.png',
    '/android-chrome-512x512.png',
    '/apple-touch-icon.png',
    '/applogo.jpg',
    '/applogo.png',
    '/images/ghadams_logo.jpg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(
                STATIC_ASSETS.map((asset) => new Request(asset, { cache: 'reload' }))
            );
        })
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            );
        })
    );

    self.clients.claim();
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

    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );

        return;
    }

    if (
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.webp')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                return (
                    cachedResponse ||
                    fetch(request).then((networkResponse) => {
                        if (!networkResponse || networkResponse.status !== 200) {
                            return networkResponse;
                        }

                        return caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, networkResponse.clone());
                            return networkResponse;
                        });
                    })
                );
            })
        );
    }
});
