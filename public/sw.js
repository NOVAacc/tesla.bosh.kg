const CACHE_NAME = 'ev-map-pwa-v1';
const OFFLINE = '/offline.html';
const FAQ_API = '/api/faq.php';
const STATIC = [OFFLINE, '/faq.html', '/faq.css', '/faq.js', '/mobile-nav.css', '/mobile-nav.js',
  '/icons/icon-180.png', '/icons/icon-192.png', '/icons/icon-512.png', '/pwa.js', '/pwa.css'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache =>
    cache.addAll(STATIC.map(path => new Request(path, { cache: 'reload', credentials: 'omit' })))
  ));
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const names = await caches.keys();
    await Promise.all(names
      .filter(name => name.startsWith('ev-map-pwa-') && name !== CACHE_NAME)
      .map(name => caches.delete(name)));
    await self.clients.claim();
  })());
});

async function fetchPublic(request, fallback) {
  try {
    const response = await fetch(request);
    if (response.status < 500) {
      if (response.ok && !response.redirected) {
        try {
          const cache = await caches.open(CACHE_NAME);
          await cache.put(fallback, response.clone());
        } catch {
          // Serve the live response even if storage is unavailable.
        }
      }
      return response;
    }
    return (await caches.match(fallback, { cacheName: CACHE_NAME })) ?? response;
  } catch {
    return (await caches.match(fallback, { cacheName: CACHE_NAME })) ?? Response.error();
  }
}

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin || request.headers.has('Range')) return;

  if (request.mode === 'navigate') {
    if (['/faq', '/faq/', '/faq.html'].includes(url.pathname) && !url.search) {
      event.respondWith(fetchPublic(new Request(request.url, { credentials: 'omit' }), '/faq.html'));
    } else if (['/', '/index.html', '/account.html'].includes(url.pathname)) {
      event.respondWith(fetch(request).catch(async () =>
        (await caches.match(OFFLINE, { cacheName: CACHE_NAME })) ?? Response.error()));
    }
    return;
  }

  if (url.pathname === FAQ_API && !url.search) {
    event.respondWith(fetchPublic(new Request(FAQ_API, { credentials: 'omit', cache: 'no-cache' }), FAQ_API));
  } else if (STATIC.includes(url.pathname) && !url.search) {
    event.respondWith(fetch(request).catch(async () =>
      (await caches.match(request, { cacheName: CACHE_NAME })) ?? Response.error()));
  }
});
