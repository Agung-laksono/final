import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;
Pusher.logToConsole = true;

if (window.PUSHER_CONFIG && window.PUSHER_CONFIG.key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: window.PUSHER_CONFIG.key,
        cluster: window.PUSHER_CONFIG.cluster,
        forceTLS: true,
        enabledTransports: ['ws', 'wss'],
    });

    // Putar notifikasi suara secara global untuk setiap update inventaris
    window.Echo.channel('inventory')
        .listen('InventoryUpdated', (event) => {
            if (window.playNotificationSound) window.playNotificationSound();
        });
    window.Echo.channel('purchase').listen('VendorUpdated', (event) => { });

    // Listener khusus untuk tombol Test Koneksi Channels
    window.Echo.channel('debug-test')
        .listen('.test-event', (event) => {
            if (window.playNotificationSound) window.playNotificationSound();
        })
        .listen('test-event', (event) => {
            if (window.playNotificationSound) window.playNotificationSound();
        });
} else {
    console.warn("VITE_PUSHER_KEY is missing in .env. Real-time updates are disabled.");
    window.Echo = {
        channel: () => ({ listen: () => ({}), stopListening: () => ({}) }),
        private: () => ({ listen: () => ({}), stopListening: () => ({}) }),
        leaveChannel: () => ({}),
        leave: () => ({}),
    };
}
