<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}
    
    <script>
        // Mencegah fungsi tombol back secara agresif (memaksa user menggunakan menu UI)
        history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function(event) {
            // Hentikan event agar tidak ditangkap oleh router SPA (Livewire)
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            
            // Paksa tetap di URL saat ini
            history.pushState(null, null, window.location.href);
        }, true); // Gunakan fase capturing agar dieksekusi lebih dulu
    </script>
</x-layouts::auth.simple>
