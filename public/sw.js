// Sirkelta Service Worker v2.1.0 (Full Offline Pre-Caching & Inertia Support)
const CACHE_PREFIX = 'sirkelta';
const CACHE_VERSION = 'v2';

const CACHE_STATIC = `${CACHE_PREFIX}-static-${CACHE_VERSION}`;
const CACHE_PAGES = `${CACHE_PREFIX}-pages-${CACHE_VERSION}`;
const CACHE_INERTIA = `${CACHE_PREFIX}-inertia-${CACHE_VERSION}`;
const ALL_CACHES = [CACHE_STATIC, CACHE_PAGES, CACHE_INERTIA];

const OFFLINE_URL = '/offline.html';

const CORE_STATIC_ASSETS = [
    '/',
    '/login',
    '/register',
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.png',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/icons/badge-72x72.png',
    '/apple-touch-icon.png'
];

// Helper: Check if request is an Inertia.js client-side navigation
function isInertiaRequest(request) {
    const inertiaHeader = request.headers.get('x-inertia') || request.headers.get('X-Inertia');
    return inertiaHeader === 'true' || inertiaHeader === true;
}

// Install Event: Pre-cache core shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_STATIC).then((cache) => {
            return Promise.allSettled(
                CORE_STATIC_ASSETS.map((asset) => cache.add(asset).catch(() => {}))
            );
        }).then(() => self.skipWaiting())
    );
});

// Activate Event: Clean up old caches and claim clients immediately
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => !ALL_CACHES.includes(key)).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Multi-tier caching for Pages, Inertia JSON, and Static Assets
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests and http/https schemes
    if (request.method !== 'GET' || !url.protocol.startsWith('http')) {
        return;
    }

    // Skip API mutations, extension auth, and logout routes
    if (
        url.pathname.startsWith('/api/') || 
        url.pathname.includes('/logout') ||
        url.pathname.includes('/@vite/') ||
        url.pathname.includes('/__vite')
    ) {
        return;
    }

    // 1. Inertia.js SPA Requests (JSON data containing page props & component)
    if (isInertiaRequest(request)) {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone1 = networkResponse.clone();
                        const clone2 = networkResponse.clone();
                        caches.open(CACHE_INERTIA).then((cache) => {
                            cache.put(request.url, clone1);
                            cache.put(url.pathname, clone2);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Offline: return cached Inertia JSON with latest saved page data
                    return caches.open(CACHE_INERTIA).then((cache) => {
                        return cache.match(request.url).then((cachedResponse) => {
                            if (cachedResponse) {
                                return cachedResponse;
                            }
                            return cache.match(url.pathname).then((byPath) => {
                                if (byPath) {
                                    return byPath;
                                }
                                // Fallback if this specific page was not visited yet
                                return new Response(JSON.stringify({
                                    component: 'Error',
                                    props: {
                                        status: 503,
                                        message: 'Halaman ini belum tersimpan di memori offline Anda. Silakan sambungkan internet Anda untuk membukanya pertama kali.'
                                    },
                                    url: url.pathname,
                                    version: ''
                                }), {
                                    status: 200,
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-Inertia': 'true'
                                    }
                                });
                            });
                        });
                    });
                })
        );
        return;
    }

    // 2. Full Browser Navigation Requests (HTML document reload or direct address URL)
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const clone1 = networkResponse.clone();
                        const clone2 = networkResponse.clone();
                        caches.open(CACHE_PAGES).then((cache) => {
                            cache.put(request.url, clone1);
                            cache.put(url.pathname, clone2);
                        });
                    }
                    return networkResponse;
                })
                .catch(() => {
                    // Offline: return cached HTML page
                    return caches.open(CACHE_PAGES).then((cache) => {
                        return cache.match(request.url).then((cachedPage) => {
                            if (cachedPage) {
                                return cachedPage;
                            }
                            return cache.match(url.pathname).then((byPath) => {
                                if (byPath) {
                                    return byPath;
                                }
                                // Fallback cascade: try cached portal, dashboard, root '/', or offline.html
                                return cache.match('/portal').then((portal) => {
                                    return portal || cache.match('/dashboard').then((dash) => {
                                        return dash || cache.match('/').then((rootPage) => {
                                            return rootPage || caches.open(CACHE_STATIC).then((sc) => sc.match(OFFLINE_URL));
                                        });
                                    });
                                });
                            });
                        });
                    });
                })
        );
        return;
    }

    // 3. Static Assets (CSS, JS, Fonts, Images, Icons, Vite Chunks)
    const isAsset = 
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'image' ||
        request.destination === 'font' ||
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/images/') ||
        url.pathname.endsWith('.woff') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.ttf') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.webp') ||
        url.hostname.includes('fonts.bunny.net');

    if (isAsset) {
        event.respondWith(
            caches.open(CACHE_STATIC).then((cache) => {
                return cache.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        // Stale-While-Revalidate: fetch in background to update cache
                        fetch(request).then((networkResponse) => {
                            if (networkResponse && networkResponse.status === 200) {
                                cache.put(request, networkResponse);
                            }
                        }).catch(() => {});
                        return cachedResponse;
                    }

                    // If not in cache, fetch and store
                    return fetch(request).then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            const responseClone = networkResponse.clone();
                            cache.put(request, responseClone);
                        }
                        return networkResponse;
                    });
                });
            })
        );
        return;
    }
});

// Push Notification Event: Native smartphone notification style
self.addEventListener('push', (event) => {
    let payload = {};
    if (event.data) {
        try {
            payload = event.data.json();
        } catch (e) {
            payload = { body: event.data.text() };
        }
    }

    const title = payload.title || 'Sirkelta';
    const options = {
        body: payload.body || 'Anda memiliki notifikasi baru dari Sirkelta.',
        icon: payload.icon || '/icons/icon-192x192.png',
        badge: payload.badge || '/icons/badge-72x72.png',
        image: payload.image || undefined,
        tag: payload.tag || 'sirkelta-notif',
        renotify: true,
        vibrate: [200, 100, 200],
        requireInteraction: false,
        data: {
            url: payload.url || '/dashboard',
            ...payload.data
        },
        actions: payload.actions || [
            { action: 'open', title: 'Buka Sekarang' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification Click Event: Focus or Open Window
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : '/dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    if (client.url.includes(targetUrl)) {
                        return client.focus();
                    }
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// Background Sync Event
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-transactions') {
        event.waitUntil(
            self.clients.matchAll({ includeUncontrolled: true, type: 'window' }).then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'TRIGGER_OFFLINE_SYNC' });
                });
            })
        );
    }
});

// Message Event: e.g. cache cleanup on logout
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'PURGE_AUTH_CACHE') {
        caches.delete(CACHE_PAGES);
        caches.delete(CACHE_INERTIA);
    }
});
