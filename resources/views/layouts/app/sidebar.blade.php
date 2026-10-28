<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
    @include('layouts.app.global-loader')

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
            $badgeItems = [
                'inventory.transfers'    => 'inventory_transfer',
                'inventory.requests'     => 'inventory_request',
                'purchase.queues.kanban' => 'purchase_queue',
                'purchase.orders.kanban' => 'purchase_order',
                'production.orders'      => 'production_order',
                'sales.orders.index'     => 'sales_order',
                'finance.receivables'    => 'finance_receivables',
                'finance.payables'       => 'finance_payables',
                'finance.inbox'          => 'finance_inbox',
                'finance.transfers'      => 'finance_transfer',
            ];

            $mainModules = [
                'INVENTORY' => [
                    'label' => 'Inventory',
                    'icon' => 'archive-box',
                    'permission' => 'inventory.view',
                    'badge' => 'module_inventory',
                    'fallback' => 'inventory',
                ],
                'PEMBELIAN' => [
                    'label' => 'Pembelian',
                    'icon' => 'shopping-cart',
                    'permission' => 'purchase.dashboard.view',
                    'badge' => 'module_purchase',
                    'fallback' => 'purchase.index',
                ],
                'PRODUKSI' => [
                    'label' => 'Produksi',
                    'icon' => 'wrench-screwdriver',
                    'permission' => 'production.dashboard.view',
                    'badge' => 'module_production',
                    'fallback' => 'production.orders',
                ],
                'PENJUALAN' => [
                    'label' => 'Penjualan',
                    'icon' => 'calculator',
                    'permission' => 'sales.dashboard.view',
                    'badge' => 'module_sales',
                    'fallback' => 'sales.orders.index',
                ],
                'KOMUNIKASI' => [
                    'label' => 'Komunikasi',
                    'icon' => 'chat-bubble-left-right',
                    'permission' => null,
                    'badge' => null,
                    'fallback' => 'chat.index',
                ],
                'KEUANGAN' => [
                    'label' => 'Keuangan',
                    'icon' => 'banknotes',
                    'permission' => null,
                    'badge' => 'module_finance',
                    'fallback' => 'finance.dashboard',
                ],
                'LAINNYA' => [
                    'label' => 'Pengaturan',
                    'icon' => 'cog-6-tooth',
                    'permission' => null,
                    'badge' => null,
                    'fallback' => 'settings.index',
                ],
            ];

            // Resolve dynamic URL for each main module to first accessible route
            foreach ($mainModules as $mKey => &$mod) {
                $targetUrl = '#';
                if (isset($navGroups[$mKey])) {
                    foreach ($navGroups[$mKey] as $nItem) {
                        if (\Illuminate\Support\Facades\Route::has($nItem->route_name)) {
                            if (!$nItem->permission || auth()->user()?->can($nItem->permission)) {
                                $targetUrl = route($nItem->route_name);
                                break;
                            }
                        }
                    }
                }
                if ($targetUrl === '#' && \Illuminate\Support\Facades\Route::has($mod['fallback'])) {
                    $targetUrl = route($mod['fallback']);
                }
                $mod['url'] = $targetUrl;
            }
            unset($mod);

            // Determine currently active section
            $activeSection = null;
            if (request()->routeIs('dashboard')) {
                $activeSection = 'DASHBOARD';
            } elseif (request()->routeIs('workspaces.*')) {
                $activeSection = 'WORKSPACES';
            } elseif (request()->routeIs('inventory.*') || request()->routeIs('inventory')) {
                $activeSection = 'INVENTORY';
            } elseif (request()->routeIs('purchase.*')) {
                $activeSection = 'PEMBELIAN';
            } elseif (request()->routeIs('production.*')) {
                $activeSection = 'PRODUKSI';
            } elseif (request()->routeIs('sales.*')) {
                $activeSection = 'PENJUALAN';
            } elseif (request()->routeIs('chat.*')) {
                $activeSection = 'KOMUNIKASI';
            } elseif (request()->routeIs('finance.*')) {
                $activeSection = 'KEUANGAN';
            } elseif (request()->routeIs('settings.*') || request()->routeIs('profile.*') || request()->routeIs('security.*') || request()->routeIs('appearance.*') || request()->routeIs('cms.*')) {
                $activeSection = 'LAINNYA';
            } else {
                foreach ($navGroups as $sec => $items) {
                    foreach ($items as $item) {
                        if (request()->routeIs($item->route_name . '*') || request()->routeIs($item->route_name)) {
                            $activeSection = $sec;
                            break 2;
                        }
                    }
                }
            }
            $activeSection = $activeSection ?? 'DASHBOARD';

            // Build Sub-menu items for Header
            $headerSubmenus = [];
            if ($activeSection === 'WORKSPACES') {
                if (\Illuminate\Support\Facades\Route::has('workspaces.index')) {
                    $headerSubmenus[] = [
                        'label' => 'Boards',
                        'url' => route('workspaces.index'),
                        'icon' => 'rectangle-group',
                        'icon_type' => 'flux',
                        'image_path' => null,
                        'badge' => null,
                        'is_current' => request()->routeIs('workspaces.*'),
                    ];
                }
            } elseif ($activeSection === 'DASHBOARD') {
                if (\Illuminate\Support\Facades\Route::has('dashboard')) {
                    $headerSubmenus[] = [
                        'label' => 'Ringkasan Utama',
                        'url' => route('dashboard'),
                        'icon' => 'home',
                        'icon_type' => 'flux',
                        'image_path' => null,
                        'badge' => null,
                        'is_current' => request()->routeIs('dashboard'),
                    ];
                }
            } elseif ($activeSection === 'LAINNYA' && (request()->routeIs('settings.*') || request()->routeIs('profile.*') || request()->routeIs('security.*') || request()->routeIs('appearance.*'))) {
                // Settings subtabs
                $settingsTabs = [
                    ['label' => 'Profil', 'route' => 'profile.edit', 'icon' => 'user', 'perm' => 'profile.view'],
                    ['label' => 'Tampilan', 'route' => 'appearance.edit', 'icon' => 'swatch', 'perm' => null],
                    ['label' => 'Keamanan', 'route' => 'security.edit', 'icon' => 'shield-check', 'perm' => null],
                    ['label' => 'Pengguna', 'route' => 'settings.users', 'icon' => 'users', 'perm' => 'users.view'],
                    ['label' => 'Integrasi', 'route' => 'settings.integrations', 'icon' => 'cpu-chip', 'role' => 'Super Admin'],
                    ['label' => 'Navigasi', 'route' => 'settings.navigation', 'icon' => 'bars-3', 'role' => 'Super Admin'],
                    ['label' => 'PWA', 'route' => 'settings.pwa', 'icon' => 'device-phone-mobile', 'role' => 'Super Admin'],
                    ['label' => 'Sistem', 'route' => 'settings.system', 'icon' => 'server-stack', 'role' => 'Super Admin'],
                ];
                foreach ($settingsTabs as $st) {
                    if (\Illuminate\Support\Facades\Route::has($st['route'])) {
                        $canAccess = true;
                        if (!empty($st['perm']) && !auth()->user()?->can($st['perm'])) $canAccess = false;
                        if (!empty($st['role']) && !auth()->user()?->hasRole($st['role'])) $canAccess = false;
                        if ($canAccess) {
                            $headerSubmenus[] = [
                                'label' => $st['label'],
                                'url' => route($st['route']),
                                'icon' => $st['icon'],
                                'icon_type' => 'flux',
                                'image_path' => null,
                                'badge' => null,
                                'is_current' => request()->routeIs($st['route']),
                            ];
                        }
                    }
                }
            } elseif (isset($navGroups[$activeSection])) {
                foreach ($navGroups[$activeSection] as $item) {
                    if (\Illuminate\Support\Facades\Route::has($item->route_name)) {
                        if (!$item->permission || auth()->user()?->can($item->permission)) {
                            $headerSubmenus[] = [
                                'label' => $item->label,
                                'url' => route($item->route_name),
                                'icon' => $item->icon,
                                'icon_type' => $item->icon_type ?? 'flux',
                                'image_path' => $item->image_path,
                                'badge' => $badgeItems[$item->route_name] ?? null,
                                'is_current' => request()->routeIs($item->route_name . '*') || request()->routeIs($item->route_name),
                            ];
                        }
                    }
                }
            }
        @endphp

        {{-- SIDEBAR: MENU UTAMA --}}
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-300 ease-in-out !z-[999]">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="#" wire:navigate />
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            {{-- Daftar Menu Utama --}}
            <flux:sidebar.nav>
                {{-- Dashboard --}}
                <flux:sidebar.item
                    :href="route('dashboard')"
                    :current="$activeSection === 'DASHBOARD'"
                    icon="home"
                    wire:navigate
                    class="transition-transform duration-200 hover:translate-x-1"
                >
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                {{-- Workspaces --}}
                @if(config('modules_statuses.Workspace', true))
                <flux:sidebar.item
                    :href="route('workspaces.index')"
                    :current="$activeSection === 'WORKSPACES'"
                    icon="rectangle-group"
                    wire:navigate
                    class="transition-transform duration-200 hover:translate-x-1"
                >
                    Boards
                </flux:sidebar.item>
                @endif

                {{-- Modul Utama ERP --}}
                @foreach($mainModules as $modKey => $mod)
                    @if(!$mod['permission'] || auth()->user()?->can($mod['permission']))
                    <flux:sidebar.item
                        :href="$mod['url']"
                        :current="$activeSection === $modKey"
                        wire:navigate
                        class="transition-transform duration-200 hover:translate-x-1"
                    >
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon :icon="$mod['icon']" class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                @if(!empty($mod['badge']))
                                    <livewire:layout.sidebar-badge :type="$mod['badge']" />
                                @endif
                            </div>
                        </x-slot:icon>
                        {{ __($mod['label']) }}
                    </flux:sidebar.item>
                    @endif
                @endforeach
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Bottom Utilities --}}
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

        {{-- HEADER: SUB-MENUS & TOP UTILITIES --}}
        <flux:header sticky class="border-b border-zinc-200/80 dark:border-zinc-700/80 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md !z-[999] px-3 sm:px-6 h-14">
            {{-- Mobile Sidebar Toggle --}}
            <flux:sidebar.toggle class="md:hidden" icon="bars-2" inset="left" />

            {{-- Active Module Indicator Badge (Desktop) --}}
            <div class="hidden sm:flex items-center gap-2 pr-3 border-r border-zinc-200 dark:border-zinc-700/80 shrink-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200/80 dark:border-zinc-700/80">
                    @if(isset($mainModules[$activeSection]))
                        <flux:icon :icon="$mainModules[$activeSection]['icon']" class="w-3.5 h-3.5 text-indigo-500" />
                        <span>{{ __($mainModules[$activeSection]['label']) }}</span>
                    @elseif($activeSection === 'WORKSPACES')
                        <flux:icon icon="rectangle-group" class="w-3.5 h-3.5 text-pink-500" />
                        <span>Workspaces</span>
                    @else
                        <flux:icon icon="home" class="w-3.5 h-3.5 text-indigo-500" />
                        <span>Dashboard</span>
                    @endif
                </span>
            </div>

            {{-- SUB-MENU HORIZONTAL NAVBAR --}}
            @if(count($headerSubmenus) > 0)
            <div class="flex-1 overflow-x-auto no-scrollbar py-1 px-1 sm:px-2">
                <flux:navbar class="gap-1 flex-nowrap min-w-max py-0">
                    @foreach($headerSubmenus as $subItem)
                        <flux:navbar.item
                            :href="$subItem['url']"
                            :current="$subItem['is_current']"
                            wire:navigate
                            class="shrink-0 text-xs font-medium"
                        >
                            @if(($subItem['icon_type'] ?? 'flux') === 'image' && !empty($subItem['image_path']))
                                <img src="{{ Storage::url($subItem['image_path']) }}" class="w-3.5 h-3.5 object-contain inline-block mr-1.5" />
                            @elseif(!empty($subItem['icon']))
                                <flux:icon :icon="$subItem['icon']" class="size-3.5 inline-block mr-1.5" />
                            @endif
                            {{ __($subItem['label']) }}
                            @if(!empty($subItem['badge']))
                                <livewire:layout.sidebar-badge :type="$subItem['badge']" />
                            @endif
                        </flux:navbar.item>
                    @endforeach
                </flux:navbar>
            </div>
            @else
            <div class="flex-1"></div>
            @endif

            {{-- Header Right Actions --}}
            <div class="flex items-center gap-2 ml-auto shrink-0">
                <livewire:layout.notification-bell />

                {{-- Mobile User Profile Dropdown --}}
                <div class="md:hidden">
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
                </div>
            </div>
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
        
        @endif
    </body>
</html>
