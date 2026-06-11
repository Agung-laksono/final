import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;
Pusher.logToConsole = true;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_KEY,
    cluster: import.meta.env.VITE_PUSHER_CLUSTER,
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
window.Echo.channel('purchase').listen('VendorUpdated', (event) => { let audio = new Audio('/notification.mp3'); audio.volume = 0.6; audio.play().catch(e => console.log('Audio autoplay prevented by browser:', e)); });
