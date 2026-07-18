{{-- Global Minimal Loader for Livewire Navigation (Livewire 4 compatible) --}}
{{-- A thin animated progress bar at the top of the screen --}}
<div id="global-page-loader" class="fixed inset-0 z-[99999] pointer-events-none bg-white/50 dark:bg-zinc-900/50 backdrop-blur-sm" style="opacity:0; transition: opacity 0.2s ease;">

    {{-- Top progress bar --}}
    <div id="global-loader-bar" class="absolute top-0 left-0 h-[3px] bg-indigo-500 rounded-full" style="width: 0%; transition: width 0.4s ease; box-shadow: 0 0 8px rgba(99,102,241,0.8);"></div>

    {{-- Small spinner in center --}}
    <div class="absolute inset-0 flex items-center justify-center">
    <div class="flex items-center gap-2 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-sm px-4 py-2 rounded-full shadow-lg border border-zinc-200 dark:border-zinc-700">
        <svg class="animate-spin w-3.5 h-3.5 text-indigo-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="text-[10px] font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-widest">Memuat...</span>
    </div>
    </div>
</div>

<script>
(function() {
    var loaderEl = null;
    var barEl = null;
    var progressTimer = null;
    var fakeProgress = 0;

    function getEls() {
        if (!loaderEl) loaderEl = document.getElementById('global-page-loader');
        if (!barEl) barEl = document.getElementById('global-loader-bar');
    }

    function startProgress() {
        fakeProgress = 0;
        clearInterval(progressTimer);
        progressTimer = setInterval(function() {
            if (fakeProgress < 85) {
                fakeProgress += Math.random() * 15;
                if (fakeProgress > 85) fakeProgress = 85;
                if (barEl) barEl.style.width = fakeProgress + '%';
            }
        }, 200);
    }

    function showLoader() {
        getEls();
        if (loaderEl) {
            loaderEl.style.opacity = '1';
            loaderEl.style.pointerEvents = 'all';
        }
        startProgress();
    }

    function hideLoader() {
        getEls();
        clearInterval(progressTimer);
        if (barEl) barEl.style.width = '100%';
        // Ditahan 4 detik agar Bapak bisa lihat dulu
        setTimeout(function() {
            if (loaderEl) {
                loaderEl.style.opacity = '0';
                loaderEl.style.pointerEvents = 'none';
            }
            setTimeout(function() {
                if (barEl) barEl.style.width = '0%';
            }, 300);
        }, 4000);
    }

    // Livewire 4 events
    document.addEventListener('livewire:navigate', showLoader);
    document.addEventListener('livewire:navigate-end', hideLoader);

    // Fallback: Livewire 3 events
    document.addEventListener('livewire:navigating', showLoader);
    document.addEventListener('livewire:navigated', hideLoader);
})();
</script>
