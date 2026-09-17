/* Dokan Ward admin static asset cache.
   - /build/*  → network-first (deploys must win immediately)
   - brand logos / favicons / apple icons → network-only (never pin a wrong tenant mark)
   - other images/fonts → stale-while-revalidate
   Never caches HTML or this script. */
const CACHE = 'dokannward-admin-static-v11';

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

function isBrandChrome(path) {
  return (
    path.startsWith('/images/brand-logo') ||
    path.startsWith('/images/dokan-ward-logo') ||
    path.startsWith('/branding/') ||
    path === '/apple-icon.png' ||
    path === '/apple-touch-icon.png' ||
    path === '/favicon.ico' ||
    /^\/favicon-\d+x\d+\.png$/.test(path) ||
    /^\/icon-\d+\.png$/.test(path)
  );
}

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

  // Brand chrome must never be served from a stale cross-tenant SW entry.
  if (isBrandChrome(path)) {
    event.respondWith(fetch(req));
    return;
  }

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
