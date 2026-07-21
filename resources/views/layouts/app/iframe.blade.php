<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @livewireStyles
    <style>
        /* Custom scrollbar for iframe content */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #52525b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #71717a; }
    </style>
</head>
<body class="font-sans antialiased bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 min-h-screen flex flex-col">
    <flux:main class="flex-1 w-full h-full pb-28">
        {{ $slot }}
    </flux:main>
    
    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
    @stack('scripts')
    <script>
        // 1. Intercept Livewire requests
        document.addEventListener('livewire:init', () => {
            Livewire.hook('request', ({ options }) => {
                options.headers['X-Is-Iframe'] = '1';
            });
        });

        // 2. Intercept native Fetch requests (untuk wire:navigate)
        const originalFetch = window.fetch;
        window.fetch = async function () {
            let [resource, config] = arguments;
            if (!config) config = {};
            if (!config.headers) config.headers = {};
            
            if (config.headers instanceof Headers) {
                config.headers.set('X-Is-Iframe', '1');
            } else {
                config.headers['X-Is-Iframe'] = '1';
            }
            
            return originalFetch(resource, config);
        };
    </script>
    @livewireScripts
</body>
</html>
