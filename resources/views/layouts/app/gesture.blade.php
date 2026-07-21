<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            .hide-scrollbar::-webkit-scrollbar { display: none; }
            .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
            .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
        </style>
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800 pb-20" x-data="gestureApp()" x-on:livewire:navigated.window="recordVisit()">
    @include('layouts.app.global-loader')
        
        {{-- Gesture Detectors --}}
        <div class="fixed inset-0 z-0 pointer-events-none"
             @touchstart.passive="handleTouchStart"
             @touchmove.passive="handleTouchMove"
             @touchend="handleTouchEnd">
        </div>
        
        <div class="relative z-10"
             @touchstart.passive="handleTouchStart"
             @touchmove.passive="handleTouchMove"
             @touchend="handleTouchEnd">
            
            <!-- Mobile User Menu -->
            <flux:header class="md:hidden border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 sticky top-0 z-40">
                <x-app-logo href="#" wire:navigate />

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
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </flux:header>

            {{ $slot }}
        </div>

        {{-- Off-Canvas Menu --}}
        <div class="fixed inset-0 z-[999] pointer-events-none" x-cloak>
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/50 pointer-events-auto" 
                 x-show="isMenuOpen" 
                 @click="isMenuOpen = false"
                 x-transition:enter="transition-opacity ease-linear duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"></div>
            
            {{-- Sliding Panel --}}
            <div class="absolute inset-y-0 left-0 w-[280px] bg-zinc-50 dark:bg-zinc-900 shadow-xl overflow-y-auto pointer-events-auto flex flex-col hide-scrollbar"
                 x-show="isMenuOpen"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="-translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-300 transform"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="-translate-x-full">
                
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center sticky top-0 bg-zinc-50 dark:bg-zinc-900 z-10">
                    <x-app-logo href="#" wire:navigate />
                    <button @click="isMenuOpen = false" class="text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-800 p-2 rounded-full transition-colors"><flux:icon.x-mark class="w-5 h-5" /></button>
                </div>
                
                <div class="p-4 pb-24 flex flex-col gap-2" @click="setTimeout(() => isMenuOpen = false, 300)">
                    <!-- Inventory -->
             @can('inventory.view')
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('INVENTORY') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    <flux:sidebar.item icon="chart-pie" :href="route('inventory')" :current="request()->routeIs('inventory')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('inventory.item.view')
                    <flux:sidebar.item icon="cube" :href="route('inventory.items')" :current="request()->routeIs('inventory.items')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Barang') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.warehouse.view')
                    <flux:sidebar.item icon="building-storefront" :href="route('inventory.warehouses')" :current="request()->routeIs('inventory.warehouses')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Gudang') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.transfer.view')
                    <flux:sidebar.item :href="route('inventory.transfers')" :current="request()->routeIs('inventory.transfers*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.arrows-right-left class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="inventory_transfer" />
                            </div>
                        </x-slot:icon>
                        {{ __('Transfer Barang') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('inventory.requests')" :current="request()->routeIs('inventory.requests*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.inbox-arrow-down class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="inventory_request" />
                            </div>
                        </x-slot:icon>
                        {{ __('Permintaan Barang') }}
                    </flux:sidebar.item>
                    @endcan

                    @can('inventory.stock.create')
                    <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('inventory.dispatch')" :current="request()->routeIs('inventory.dispatch')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Alokasi Kedatangan') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item :href="route('inventory.production-receipts')" :current="request()->routeIs('inventory.production-receipts*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.arrow-down-tray class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                            </div>
                        </x-slot:icon>
                        {{ __('Penerimaan Fisik (QC)') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('production.order.update')
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('inventory.fulfillments')" :current="request()->routeIs('inventory.fulfillments')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Pemenuhan Produksi') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.view')
                    <flux:sidebar.item icon="truck" :href="route('inventory.sales-deliveries')" :current="request()->routeIs('inventory.sales-deliveries')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Pengiriman Penjualan') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.movement.view')
                    <flux:sidebar.item icon="clock" :href="route('inventory.movements')" :current="request()->routeIs('inventory.movements*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Riwayat Mutasi') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('inventory.opname.view')
                    <flux:sidebar.item icon="adjustments-horizontal" :href="route('inventory.stock-opname')" :current="request()->routeIs('inventory.stock-opname')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Opname') }}
                    </flux:sidebar.item>
                    @endcan
                </flux:navlist.group>
            </flux:sidebar.nav>
            @endcan

            <!-- Pembelian -->
            @can('purchase.dashboard.view')
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('PEMBELIAN') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    @can('purchase.dashboard.view')
                    <flux:sidebar.item icon="shopping-cart" :href="route('purchase.index')" :current="request()->routeIs('purchase.index')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Dashboard Pembelian') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('purchase.queue.view')
                    <flux:sidebar.item :href="route('purchase.queues.kanban')" :current="request()->routeIs('purchase.queues.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.queue-list class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="purchase_queue" />
                            </div>
                        </x-slot:icon>
                        {{ __('Kanban Permintaan') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('purchase.order.view')
                    <flux:sidebar.item :href="route('purchase.orders.kanban')" :current="request()->routeIs('purchase.orders.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.clipboard-document-list class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="purchase_order" />
                            </div>
                        </x-slot:icon>
                        {{ __('Kanban PO') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('purchase.vendor.view')
                    <flux:sidebar.item icon="building-office-2" :href="route('purchase.vendors.index')" :current="request()->routeIs('purchase.vendors.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Data Vendor') }}
                    </flux:sidebar.item>
                    @endcan
                </flux:navlist.group>
            </flux:sidebar.nav>
            @endcan

            <!-- Produksi -->
            @can('production.dashboard.view')
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('PRODUKSI') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    @can('production.order.view')
                    <flux:sidebar.item :href="route('production.orders')" :current="request()->routeIs('production.orders*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.wrench-screwdriver class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="production_order" />
                            </div>
                        </x-slot:icon>
                        {{ __('Kanban Produksi') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('production.order.view')
                    <flux:sidebar.item icon="document-text" :href="route('production.recipes')" :current="request()->routeIs('production.recipes*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Resep Produksi') }}
                    </flux:sidebar.item>
                    @endcan
                </flux:navlist.group>
            </flux:sidebar.nav>
            @endcan

            <!-- Penjualan -->
            @can('sales.dashboard.view')
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('PENJUALAN') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    @can('sales.customer.view')
                    <flux:sidebar.item icon="user-group" :href="route('sales.customers.index')" :current="request()->routeIs('sales.customers.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Data Pelanggan') }}
                    </flux:sidebar.item>
                    @endcan
                    @can('sales.order.view')
                    <flux:sidebar.item :href="route('sales.orders.index')" :current="request()->routeIs('sales.orders.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        <x-slot:icon>
                            <div class="relative">
                                <flux:icon.clipboard-document-list class="size-4 [[data-flux-sidebar-item]:hover_&]:text-current!" />
                                <livewire:layout.sidebar-badge type="sales_order" />
                            </div>
                        </x-slot:icon>
                        {{ __('Kanban Sales Order') }}
                    </flux:sidebar.item>
                    @endcan
                </flux:navlist.group>
            </flux:sidebar.nav>
            @endcan

            <!-- Komunikasi -->
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('KOMUNIKASI') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('chat.index')" :current="request()->routeIs('chat.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Chat WhatsApp') }}
                    </flux:sidebar.item>
                </flux:navlist.group>
            </flux:sidebar.nav>

            <!-- Keuangan -->
            @canany(['finance.dashboard.view', 'finance.inbox.view'])
            <flux:sidebar.nav>
                <flux:navlist.group heading="{{ __('KEUANGAN') }}" expandable class="mb-2 text-zinc-900 dark:text-zinc-100">
                    <flux:sidebar.item icon="banknotes" :href="route('finance.dashboard')" :current="request()->routeIs('finance.dashboard')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Buku Kas & Bank') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="inbox-arrow-down" :href="route('finance.inbox')" :current="request()->routeIs('finance.inbox')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Validasi Transaksi') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="credit-card" :href="route('finance.payables')" :current="request()->routeIs('finance.payables')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                        {{ __('Hutang Pembelian') }}
                    </flux:sidebar.item>
                </flux:navlist.group>
            </flux:sidebar.nav>
            @endcanany

            <flux:sidebar.nav class="mt-4">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                    {{ __('Dashboard Utama') }}
                </flux:sidebar.item>
                
                <flux:sidebar.item icon="newspaper" :href="route('cms.posts.index')" :current="request()->routeIs('cms.posts.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                    {{ __('Kelola Artikel & Dokumen') }}
                </flux:sidebar.item>
                
                <flux:sidebar.item icon="cog-6-tooth" :href="route('settings.index')" :current="request()->routeIs('settings.*') || request()->routeIs('profile.*') || request()->routeIs('security.*') || request()->routeIs('appearance.*')" wire:navigate class="transition-transform duration-300 hover:translate-x-2">
                    {{ __('Pengaturan') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
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
            
                </div>
            </div>
        </div>

        {{-- Bottom Tab Bar (Recent Pages) --}}
        <div class="fixed bottom-0 left-0 right-0 h-16 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-md border-t border-zinc-200 dark:border-zinc-800 z-[990] flex items-center justify-around px-2 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] pb-safe">
            <template x-for="tab in recentTabs" :key="tab.url">
                <a :href="tab.url" wire:navigate 
                   class="flex flex-col items-center justify-center w-full h-full gap-1 transition-colors relative"
                   :class="currentUrl === tab.url ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'">
                    
                    {{-- Active Indicator --}}
                    <div x-show="currentUrl === tab.url" class="absolute top-0 w-8 h-1 bg-indigo-600 dark:bg-indigo-400 rounded-b-full"></div>
                    
                    <div x-html="getSvgIcon(tab.icon, currentUrl === tab.url)"></div>
                    <span class="text-[10px] font-medium truncate w-14 text-center" x-text="tab.title"></span>
                </a>
            </template>
        </div>

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

            function gestureApp() {
                return {
                    isMenuOpen: false,
                    touchStartX: 0,
                    touchStartY: 0,
                    recentTabs: [],
                    currentUrl: window.location.pathname,
                    
                    init() {
                        const saved = localStorage.getItem('gesture_recent_tabs');
                        if (saved) {
                            try { this.recentTabs = JSON.parse(saved); } catch(e){}
                        }
                        
                        if (this.recentTabs.length === 0) {
                            this.recentTabs = [{ url: '/', title: 'Home', icon: 'home' }];
                        }
                        
                        this.recordVisit();
                    },

                    handleTouchStart(e) {
                        this.touchStartX = e.touches[0].clientX;
                        this.touchStartY = e.touches[0].clientY;
                    },
                    handleTouchMove(e) {
                        // Tidak perlu prevent default, biarkan scroll alami
                    },
                    handleTouchEnd(e) {
                        const touchEndX = e.changedTouches[0].clientX;
                        const touchEndY = e.changedTouches[0].clientY;
                        
                        const deltaX = touchEndX - this.touchStartX;
                        const deltaY = touchEndY - this.touchStartY;
                        
                        // Swipe Right (Open Menu) - Mulai dari 30px ujung kiri layar
                        if (deltaX > 70 && Math.abs(deltaY) < 50 && this.touchStartX < 30) {
                            this.isMenuOpen = true;
                        }
                        
                        // Swipe Left (Close Menu)
                        if (deltaX < -70 && Math.abs(deltaY) < 50 && this.isMenuOpen) {
                            this.isMenuOpen = false;
                        }
                    },
                    
                    getSvgIcon(iconName, isActive) {
                        const classes = isActive ? 'w-5 h-5 stroke-[2.5]' : 'w-5 h-5 stroke-[1.5]';
                        const icons = {
                            'home': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>`,
                            'cube': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg>`,
                            'shopping-cart': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>`,
                            'banknotes': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V5.946c0-.754-.726-1.294-1.453-1.096A60.07 60.07 0 012.25 5.25v13.5zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>`,
                            'document-text': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>`,
                            'wrench-screwdriver': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.827M15.17 11.42L21 5.583M5.583 21l5.837-5.837m-2.14-7.466l-5.592 5.592a2.65 2.65 0 01-3.75-3.749l5.59-5.59M11.42 15.17l3.75-3.75M5.583 21l3.75-3.75" /></svg>`,
                            'calculator': `<svg class="${classes}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" /></svg>`
                        };
                        return icons[iconName] || icons['document-text'];
                    },

                    determineIcon(url) {
                        if (url.includes('/inventory')) return 'cube';
                        if (url.includes('/finance')) return 'banknotes';
                        if (url.includes('/purchase')) return 'shopping-cart';
                        if (url.includes('/sales')) return 'calculator';
                        if (url.includes('/production')) return 'wrench-screwdriver';
                        if (url === '/' || url === '/dashboard') return 'home';
                        return 'document-text';
                    },

                    determineTitle(url, docTitle) {
                        let shortTitle = docTitle.split('-')[0].trim();
                        if (url.includes('/inventory/items')) return 'Barang';
                        if (url.includes('/inventory/warehouses')) return 'Gudang';
                        if (url.includes('/purchase/orders')) return 'PO';
                        if (url.includes('/sales/orders')) return 'SO';
                        
                        if (shortTitle.length > 12) shortTitle = shortTitle.substring(0, 10) + '..';
                        return shortTitle;
                    },

                    recordVisit() {
                        setTimeout(() => {
                            this.currentUrl = window.location.pathname;
                            let title = this.determineTitle(this.currentUrl, document.title);
                            let icon = this.determineIcon(this.currentUrl);
                            
                            let tabs = [...this.recentTabs];
                            tabs = tabs.filter(t => t.url !== this.currentUrl);
                            tabs.unshift({ url: this.currentUrl, title: title, icon: icon });
                            
                            if (tabs.length > 5) {
                                tabs = tabs.slice(0, 5);
                            }
                            
                            this.recentTabs = tabs;
                            localStorage.setItem('gesture_recent_tabs', JSON.stringify(tabs));
                        }, 50);
                    }
                }
            }
        </script>
        <x-loading></x-loading>
        @if(!request()->is('chat*'))
        <livewire:ai-chat-widget />
        @endif
    </body>
</html>
