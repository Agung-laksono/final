<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
    @include('layouts.app.global-loader')
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-300 ease-in-out !z-[999]">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="#" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>
            <livewire:layout.notification-bell class="hidden md:block" />

            @php
                $navGroups = \App\Models\NavigationItem::active()->orderBy('sort_order')->get()->groupBy('section');
                $sectionOrder = ['INVENTORY','PEMBELIAN','PRODUKSI','PENJUALAN','KOMUNIKASI','KEUANGAN'];
                $sectionPermissions = [
                    'INVENTORY'   => 'inventory.view',
                    'PEMBELIAN'   => 'purchase.dashboard.view',
                    'PRODUKSI'    => 'production.dashboard.view',
                    'PENJUALAN'   => 'sales.dashboard.view',
                    'KOMUNIKASI'  => null,
                    'KEUANGAN'    => null,
                ];
                // Badge items (need special icon with badge)
                $badgeItems = [
                    'inventory.transfers'    => 'inventory_transfer',
                    'inventory.requests'     => 'inventory_request',
                    'purchase.queues.kanban' => 'purchase_queue',
                    'purchase.orders.kanban' => 'purchase_order',
                    'production.orders'      => 'production_order',
                    'sales.orders.index'     => 'sales_order',
                ];
            @endphp

            @foreach($sectionOrder as $section)
                @if(isset($navGroups[$section]) && $navGroups[$section]->count() > 0)
                @php
                    $items = $navGroups[$section];
                    $sectionPerm = $sectionPermissions[$section] ?? null;
                @endphp
                @if($sectionPerm)
                @can($sectionPerm)
                @endif

                <flux:sidebar.nav>
                    <flux:navlist.group heading="{{ __($section) }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                        @foreach($items as $item)
                            @php
                                $routeExists = \Illuminate\Support\Facades\Route::has($item->route_name);
                                $hasBadge = isset($badgeItems[$item->route_name]);
                            @endphp
                            @if($routeExists)
                                @if($item->permission)
                                    @can($item->permission)
                                @endif

                                <flux:sidebar.item
                                    :href="route($item->route_name)"
                                    :current="request()->routeIs($item->route_name . '*')"
                                    wire:navigate
                                    class="transition-transform duration-300 hover:translate-x-2"
                                >
                                    <x-slot:icon>
                                        <div class="relative">
                                            @if(($item->icon_type ?? 'flux') === 'image' && $item->image_path)
                                                <img src="{{ Storage::url($item->image_path) }}" class="w-4 h-4 object-contain [[data-flux-sidebar-item]:hover_&]:scale-110 transition-transform" />
                                            @else
                                                <flux:icon :icon="$item->icon" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                            @endif
                                            @if($hasBadge)
                                                <livewire:layout.sidebar-badge :type="$badgeItems[$item->route_name]" />
                                            @endif
                                        </div>
                                    </x-slot:icon>
                                    {{ __($item->label) }}
                                </flux:sidebar.item>

                                @if($item->permission)
                                    @endcan
                                @endif
                            @endif
                        @endforeach
                    </flux:navlist.group>
                </flux:sidebar.nav>

                @if($sectionPerm)
                @endcan
                @endif
                @endif
            @endforeach

            {{-- LAINNYA (Utama, Artikel, Pengaturan) --}}
            @if(isset($navGroups['LAINNYA']))
            <flux:sidebar.nav class="mt-4">
                @foreach($navGroups['LAINNYA'] as $item)
                    @php $routeExists = \Illuminate\Support\Facades\Route::has($item->route_name); @endphp
                    @if($routeExists)
                        <flux:sidebar.item
                            :href="route($item->route_name)"
                            :current="request()->routeIs($item->route_name . '*')"
                            wire:navigate
                            class="transition-transform duration-300 hover:translate-x-2"
                        >
                            <x-slot:icon>
                                @if(($item->icon_type ?? 'flux') === 'image' && $item->image_path)
                                    <img src="{{ Storage::url($item->image_path) }}" class="w-4 h-4 object-contain [[data-flux-sidebar-item]:hover_&]:scale-110 transition-transform" />
                                @else
                                    <flux:icon :icon="$item->icon" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                @endif
                            </x-slot:icon>
                            {{ __($item->label) }}
                        </flux:sidebar.item>
                    @endif
                @endforeach
            </flux:sidebar.nav>
            @endif

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item
                    class="cursor-pointer text-indigo-600 dark:text-indigo-400 font-medium"
                    tooltip="Install Aplikasi"
                    x-data="{ deferredPrompt: null, showInstall: false }"
                    @beforeinstallprompt.window="
                        $event.preventDefault();
                        deferredPrompt = $event;
                        showInstall = true;
                    "
                    @appinstalled.window="showInstall = false"
                    x-show="showInstall"
                    x-cloak
                    x-on:click="
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
                >
                    <x-slot:icon>
                        <flux:icon.arrow-down-tray class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                    </x-slot:icon>
                    <span>{{ __('Install Aplikasi') }}</span>
                </flux:sidebar.item>

                <flux:sidebar.item
                    class="cursor-pointer"
                    tooltip="Layar Penuh"
                    x-data="{ isFullscreen: false }"
                    x-on:fullscreenchange.document="isFullscreen = !!document.fullscreenElement"
                    x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                >
                    <x-slot:icon>
                        <flux:icon.arrows-pointing-out x-show="!isFullscreen" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                        <flux:icon.arrows-pointing-in x-cloak x-show="isFullscreen" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                    </x-slot:icon>
                    <span x-show="!isFullscreen">{{ __('Layar Penuh') }}</span>
                    <span x-cloak x-show="isFullscreen">{{ __('Keluar Layar Penuh') }}</span>
                </flux:sidebar.item>

                <flux:sidebar.item
                    class="cursor-pointer"
                    tooltip="Ganti Tema"
                    x-on:click="
                        let newTheme = $flux.dark ? 'light' : 'dark';
                        if (document.startViewTransition) {
                            document.startViewTransition(() => $flux.appearance = newTheme);
                        } else {
                            $flux.appearance = newTheme;
                        }
                    "
                >
                    <x-slot:icon>
                        <flux:icon.moon x-show="!$flux.dark" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                        <flux:icon.sun x-cloak x-show="$flux.dark" variant="outline" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                    </x-slot:icon>
                    <span x-show="!$flux.dark">{{ __('Mode Gelap') }}</span>
                    <span x-cloak x-show="$flux.dark">{{ __('Mode Terang') }}</span>
                </flux:sidebar.item>
            </flux:sidebar.nav>
            <div class="hidden md:block">
                <x-desktop-user-menu :name="auth()->user()->name" />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="md:hidden">
            <flux:sidebar.toggle class="md:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <livewire:layout.notification-bell />
            <flux:spacer />
            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    :avatar="auth()->user()->avatarUrl()"
                    icon-trailing="chevron-down"
                />
                <flux:menu>
                  <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    :src="auth()->user()->avatarUrl()"
                                />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.index')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @stack('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ options }) => {
                    options.headers['X-Tab-Focused'] = document.hasFocus() ? '1' : '0';
                    options.headers['X-Current-Path'] = window.location.pathname;
                });
            });
        </script>
        <x-loading></x-loading>
        @if(!request()->is('chat*') && \Illuminate\Support\Facades\Cache::get('setting_enable_ai_chat', \App\Models\Setting::where('key', 'enable_ai_chat')->value('value')) == '1')
        <livewire:ai-chat-widget />
        @endif
    </body>
</html>
