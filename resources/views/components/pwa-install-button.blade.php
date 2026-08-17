<div
    x-data="{ deferredPrompt: null, showInstall: false }"
    @beforeinstallprompt.window="
        $event.preventDefault();
        deferredPrompt = $event;
        showInstall = true;
    "
    @appinstalled.window="showInstall = false"
    x-show="showInstall"
    x-cloak
    style="position: fixed; inset: 0; pointer-events: none; z-index: 9998;"
>
    <button
        type="button"
        @click="
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        showInstall = false;
                    }
                    deferredPrompt = null;
                });
            }
        "
        class="absolute right-4 bottom-[calc(8.5rem+env(safe-area-inset-bottom))] md:right-6 md:bottom-[calc(4.5rem+env(safe-area-inset-bottom))] w-10 h-10 lg:w-12 lg:h-12 bg-white dark:bg-zinc-800 border border-emerald-500/30 rounded-full shadow-lg flex items-center justify-center text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 transition-colors pointer-events-auto"
        title="Install Aplikasi (PWA)"
    >
        <flux:icon.arrow-down-tray class="w-4 h-4 lg:w-5 lg:h-5" />
    </button>
</div>
