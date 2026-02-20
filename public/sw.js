/**
 * AG-Vote Service Worker
 *
 * Strategies:
 * - Cache-first for static assets (CSS, JS, images)
 * - Network-first for API calls with cache fallback
 * - Stale-while-revalidate for HTML pages
 */

const CACHE_VERSION = 'agvote-v1';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;

// Assets to precache on install
const PRECACHE_ASSETS = [
  '/',
  '/operator.htmx.html',
  '/vote.php',
  '/assets/css/design-system.css',
  '/assets/js/components/index.js',
  '/assets/js/components/ag-badge.js',
  '/assets/js/components/ag-kpi.js',
  '/assets/js/components/ag-quorum-bar.js',
  '/assets/js/components/ag-spinner.js',
  '/assets/js/components/ag-toast.js',
  '/assets/js/components/ag-vote-button.js',
];

// API endpoints that can work offline (read-only)
const CACHEABLE_API_PATTERNS = [
  /\/api\/v1\/meetings\.php\?/,
  /\/api\/v1\/motions\.php\?meeting_id=/,
  /\/api\/v1\/members\.php\?/,
  /\/api\/v1\/attendances\.php\?/,
];

// ============================================================================
// Install Event
// ============================================================================

self.addEventListener('install', (event) => {
  console.log('[SW] Installing Service Worker v' + CACHE_VERSION);

  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('[SW] Precaching static assets');
        return cache.addAll(PRECACHE_ASSETS.map(url => {
          return new Request(url, { cache: 'reload' });
        })).catch(err => {
          console.warn('[SW] Some precache assets failed:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// ============================================================================
// Activate Event
// ============================================================================

self.addEventListener('activate', (event) => {
  console.log('[SW] Activating Service Worker');

  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith('agvote-') && name !== STATIC_CACHE && name !== DYNAMIC_CACHE && name !== API_CACHE)
            .map((name) => {
              console.log('[SW] Deleting old cache:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

// ============================================================================
// Fetch Event
// ============================================================================

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests (no offline queue)
  if (request.method !== 'GET') {
    return;
  }

  // Route to appropriate strategy
  if (isStaticAsset(url.pathname)) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
  } else if (isCacheableApiRequest(url.pathname)) {
    event.respondWith(networkFirstWithCache(request, API_CACHE));
  } else if (isHtmlRequest(request)) {
    event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE));
  } else {
    event.respondWith(networkFirst(request));
  }
});

// ============================================================================
// Request Classification
// ============================================================================

function isStaticAsset(pathname) {
  return pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff2?|ttf|eot|ico)$/i);
}

function isCacheableApiRequest(pathname) {
  return CACHEABLE_API_PATTERNS.some(pattern => pattern.test(pathname));
}

function isHtmlRequest(request) {
  return request.headers.get('Accept')?.includes('text/html');
}

// ============================================================================
// Caching Strategies
// ============================================================================

/**
 * Cache-first strategy: Try cache, fallback to network
 */
async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) {
    return cached;
  }

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.warn('[SW] Network request failed for:', request.url);
    return new Response('Offline - Asset not available', { status: 503 });
  }
}

/**
 * Network-first strategy: Try network, fallback to cache
 */
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    return new Response('Offline', { status: 503 });
  }
}

/**
 * Network-first with cache: Try network, update cache, fallback to cache
 */
async function networkFirstWithCache(request, cacheName) {
  const cache = await caches.open(cacheName);

  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    console.log('[SW] Network failed, trying cache for:', request.url);
    const cached = await cache.match(request);
    if (cached) {
      // Add offline indicator header
      const headers = new Headers(cached.headers);
      headers.set('X-Served-From', 'cache');
      return new Response(cached.body, {
        status: cached.status,
        statusText: cached.statusText,
        headers
      });
    }
    return offlineResponse('API data not available offline');
  }
}

/**
 * Stale-while-revalidate: Return cache immediately, update in background
 */
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);

  const fetchPromise = fetch(request)
    .then((response) => {
      if (response.ok) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  if (cached) {
    fetchPromise.catch(() => {}); // Revalidate in background
    return cached;
  }

  const response = await fetchPromise;
  if (response) {
    return response;
  }

  return offlinePage();
}

// ============================================================================
// Push Notifications (for future use)
// ============================================================================

self.addEventListener('push', (event) => {
  if (!event.data) return;

  const data = event.data.json();

  const options = {
    body: data.body || 'Nouvelle notification AG-Vote',
    icon: '/assets/images/icon-192.png',
    badge: '/assets/images/badge-72.png',
    tag: data.tag || 'agvote-notification',
    data: data.data || {},
    actions: data.actions || [],
    requireInteraction: data.requireInteraction || false,
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'AG-Vote', options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const urlToOpen = event.notification.data?.url || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        // Focus existing window if available
        for (const client of clientList) {
          if (client.url.includes(urlToOpen) && 'focus' in client) {
            return client.focus();
          }
        }
        // Open new window
        if (self.clients.openWindow) {
          return self.clients.openWindow(urlToOpen);
        }
      })
  );
});

// ============================================================================
// Message Handler
// ============================================================================

self.addEventListener('message', (event) => {
  const { type, payload } = event.data || {};

  switch (type) {
    case 'SKIP_WAITING':
      self.skipWaiting();
      break;

    case 'CACHE_API_RESPONSE':
      // Cache a specific API response
      if (payload?.url && payload?.response) {
        caches.open(API_CACHE).then(cache => {
          cache.put(payload.url, new Response(JSON.stringify(payload.response), {
            headers: { 'Content-Type': 'application/json' }
          }));
        });
      }
      break;

    case 'CLEAR_API_CACHE':
      caches.delete(API_CACHE);
      break;

    case 'GET_CACHE_STATUS':
      getCacheStatus().then(status => {
        event.source.postMessage({
          type: 'CACHE_STATUS',
          payload: status
        });
      });
      break;
  }
});

async function getCacheStatus() {
  const cacheNames = await caches.keys();
  const status = {};

  for (const name of cacheNames) {
    if (name.startsWith('agvote-')) {
      const cache = await caches.open(name);
      const keys = await cache.keys();
      status[name] = keys.length;
    }
  }

  return status;
}

// ============================================================================
// Helper Functions
// ============================================================================

function offlineResponse(message) {
  return new Response(JSON.stringify({
    ok: false,
    error: 'offline',
    message: message
  }), {
    status: 503,
    headers: { 'Content-Type': 'application/json' }
  });
}

function offlinePage() {
  return new Response(`
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Hors ligne - AG-Vote</title>
      <style>
        body {
          font-family: system-ui, -apple-system, sans-serif;
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          margin: 0;
          background: #f5f5f5;
        }
        .offline-container {
          text-align: center;
          padding: 2rem;
          background: white;
          border-radius: 8px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.1);
          max-width: 400px;
        }
        .offline-icon {
          font-size: 4rem;
          margin-bottom: 1rem;
        }
        h1 { color: #333; margin: 0 0 0.5rem; }
        p { color: #666; margin: 0 0 1rem; }
        button {
          background: #0066cc;
          color: white;
          border: none;
          padding: 0.75rem 1.5rem;
          border-radius: 4px;
          cursor: pointer;
          font-size: 1rem;
        }
        button:hover { background: #0055aa; }
      </style>
    </head>
    <body>
      <div class="offline-container">
        <div class="offline-icon">📡</div>
        <h1>Vous êtes hors ligne</h1>
        <p>Vérifiez votre connexion internet et réessayez.</p>
        <button onclick="window.location.reload()">Réessayer</button>
      </div>
    </body>
    </html>
  `, {
    status: 503,
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}

console.log('[SW] Service Worker loaded');
