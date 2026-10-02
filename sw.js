const CACHE_NAME = 'election-app-v2';
const ASSETS_TO_CACHE = [
  './',
  './index.html',
  './manifest.json',
  './icon.svg'
];

// Install Event
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Event
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event (Network First Strategy)
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || event.request.url.includes('api.php')) {
    return;
  }

  event.respondWith(
    fetch(event.request).then((response) => {
      // If network fetch is successful, cache the latest version
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch(() => {
      // If offline or network fails, fallback to cache
      return caches.match(event.request);
    })
  );
});
