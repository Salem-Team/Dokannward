/* Dokan Ward admin static asset cache.
   - /build/*  → network-first (deploys must win immediately)
   - images/fonts → stale-while-revalidate
   Never caches HTML or this script. */
const CACHE = 'dokannward-admin-static-v10';

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
    ).then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  let url;
  try {
    url = new URL(req.url);
  } catch {
    return;
  }

  if (url.origin !== self.location.origin) return;

  const path = url.pathname;
  if (path === '/sw-admin.js') return;
  if (req.mode === 'navigate') return;
  if ((req.headers.get('accept') || '').includes('text/html')) return;

  if (path.startsWith('/build/')) {
    event.respondWith(networkFirst(req));
    return;
  }

  const warm =
    path.startsWith('/images/') ||
    path.startsWith('/fonts/') ||
    /\.(woff2|svg|png|jpg|jpeg|webp|ico)$/i.test(path);

  if (warm) {
    event.respondWith(staleWhileRevalidate(event, req));
  }
});

async function networkFirst(req) {
  const cache = await caches.open(CACHE);
  try {
    const res = await fetch(req);
    if (res && res.ok) {
      cache.put(req, res.clone());
    }
    return res;
  } catch {
    const cached = await cache.match(req);
    if (cached) return cached;
    throw new Error('Network failed and no cache for ' + req.url);
  }
}

async function staleWhileRevalidate(event, req) {
  const cache = await caches.open(CACHE);
  const cached = await cache.match(req);
  const networkPromise = fetch(req)
    .then((res) => {
      if (res && res.ok) {
        cache.put(req, res.clone());
      }
      return res;
    })
    .catch(() => cached);

  if (cached) {
    event.waitUntil(networkPromise);
    return cached;
  }

  return networkPromise;
}
