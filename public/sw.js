// Pusher Beams - Dibungkus try-catch agar jika diblokir AdBlocker, PWA tetap bisa diinstal
try {
    importScripts('https://js.pusher.com/beams/service-worker.js');
} catch (e) {
    console.warn('[SW] Pusher Beams diblokir atau gagal dimuat:', e);
}

const CACHE_NAME = 'inventory-pwa-cache-v1786955893877';
const urlsToCache = [
    '/',
    '/manifest.json',
];

// Install event - cache minimal URLs
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
    self.skipWaiting(); // Activate worker immediately
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch event - network-first strategy for dynamic content
self.addEventListener('fetch', event => {
    // Only intercept GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request);
        })
    );
});

// ============================================================
// Pusher Beams Custom Push & Click Handler
// Tujuannya agar saat notifikasi diklik, ia membuka/fokus ke 
// PWA (Standalone) alih-alih membuka tab browser Chrome biasa.
// ============================================================

// 1. Override default behavior dari Pusher Beams (jika library berhasil dimuat)
if (typeof PusherPushNotifications !== 'undefined') {
    PusherPushNotifications.onNotificationReceived = ({ pushEvent, payload }) => {
        // Kita tampilkan notifikasi secara manual
        const notification = payload.notification || {};
        const data         = payload.data || {};

    const title   = notification.title || 'Inventory System';
    const options = {
        body:    notification.body  || '',
        icon:    notification.icon  || '/apple-touch-icon.png',
        badge:   '/apple-touch-icon.png',
        data:    { url: data.url || '/' },
        vibrate: [200, 100, 200],
        tag:     'inventory-notification',
        renotify: true,
        actions: [
            { action: 'open', title: 'Buka Aplikasi' },
            { action: 'dismiss', title: 'Tutup' }
        ]
    };

    pushEvent.waitUntil(self.registration.showNotification(title, options));
    };
}

// 2. Handle klik notifikasi secara manual
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'dismiss') return;

    // Ambil URL dari payload Beams
    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            // Cek apakah PWA sudah terbuka (cari tab/jendela dengan URL yang sama atau basis domain sama)
            for (const client of windowClients) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.navigate(targetUrl); // Arahkan ke halaman spesifik
                    return client.focus();      // Fokuskan window PWA-nya
                }
            }
            // Jika PWA belum terbuka sama sekali, buka window baru (biasanya akan launch PWA jika terinstall)
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
