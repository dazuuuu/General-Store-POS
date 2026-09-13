/* Modern Shop POS service worker — makes the app installable + fast.
   Pages are always network-first (so live data + auth stay correct);
   only static assets and the login shell are cached. */
const CACHE = 'shop-pos-v4';
// Derived from this script's own URL, not hard-coded — works whatever
// folder the app is deployed under.
const BASE  = new URL('.', self.location).pathname.replace(/\/$/, '');
const SHELL = [
  BASE + '/',
  BASE + '/manifest.webmanifest',
  BASE + '/assets/icons/icon-192.png',
  BASE + '/assets/icons/icon-512.png',
  BASE + '/assets/icons/icon-512-maskable.png',
  BASE + '/assets/icons/apple-touch-icon.png',
  BASE + '/assets/icons/favicon-32.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Let redirects pass through normally; the browser rejects a redirected response
  // when the service worker tries to handle it with a non-follow mode.
  if (req.redirect === 'manual' || req.redirect === 'error') {
    return;
  }

  const fallbackResponse = () =>
    new Response('<!doctype html><html><body>Offline</body></html>', {
      status: 503,
      headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });

  const networkThenCache = (request) =>
    fetch(request, { redirect: 'follow' })
      .then((response) => {
        if (!response || !response.ok) {
          throw new Error('Bad response');
        }
        const copy = response.clone();
        caches.open(CACHE).then((cache) => cache.put(request, copy));
        return response;
      })
      .catch(() =>
        caches.match(request).then((hit) => {
          if (hit) return hit;
          return caches.match(BASE + '/').then((shellHit) => shellHit || fallbackResponse());
        })
      );

  // Page navigations: network-first, fall back to the cached login shell offline.
  if (req.mode === 'navigate') {
    event.respondWith(networkThenCache(req));
    return;
  }

  // Static assets: cache-first, then fill the cache.
  if (/\.(png|jpe?g|svg|gif|webp|ico|css|js|woff2?|ttf)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((hit) => {
        if (hit) return hit;
        return networkThenCache(req);
      })
    );
    return;
  }

  // Everything else: network, fall back to cache if offline.
  event.respondWith(networkThenCache(req));
});
