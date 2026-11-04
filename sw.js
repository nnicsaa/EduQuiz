const CACHE_NAME = 'eduquiz-v1';
const urlsToCache = [
    './register.php',
    './manifest.json'
];

// Install the Service Worker and cache essential files
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(urlsToCache);
        })
    );
});

// Network-first strategy for dynamic PHP files
self.addEventListener('fetch', event => {
    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});