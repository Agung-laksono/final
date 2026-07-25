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
            let audio = new Audio('/notification.mp3');
            // Volume disesuaikan agar tidak terlalu bising
            audio.volume = 0.6;
            audio.play().catch(e => console.log('Audio autoplay prevented by browser:', e));
        });
    window.Echo.channel('purchase').listen('VendorUpdated', (event) => { });
} else {
    console.warn("VITE_PUSHER_KEY is missing in .env. Real-time updates are disabled.");
    window.Echo = {
        channel: () => ({ listen: () => ({}) }),
        private: () => ({ listen: () => ({}) })
    };
}
