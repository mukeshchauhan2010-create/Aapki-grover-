/* Aapki Grocery — minimal service worker.
 *
 * Purpose: satisfy the PWA installability requirement and provide a tiny
 * offline fallback. We deliberately DO NOT cache pages aggressively — a
 * grocery store needs fresh prices/stock, so we use a network-first strategy
 * and only fall back to cache when the network is unavailable.
 */
const AG_CACHE = 'aapki-v1';

// Pre-cache only lightweight, rarely-changing shell assets.
const AG_PRECACHE = [
  'assets/favicon-192.png',
  'assets/favicon-512.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(AG_CACHE).then((cache) =>
      cache.addAll(AG_PRECACHE.map((p) => new Request(p, { cache: 'reload' }))).catch(() => {})
    )
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== AG_CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  // Only handle GET; let everything else hit the network directly.
  if (req.method !== 'GET') return;

  // Network-first: always try the live site, fall back to cache when offline.
  event.respondWith(
    fetch(req)
      .then((res) => {
        // Cache a copy of static asset responses for offline fallback.
        if (res && res.status === 200 && req.url.includes('/assets/')) {
          const copy = res.clone();
          caches.open(AG_CACHE).then((c) => c.put(req, copy)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(req))
  );
});
