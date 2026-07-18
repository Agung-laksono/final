<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-900">
    @include('layouts.app.global-loader')
        {{ $slot }}
        {{-- Multi-level Speed Dial Global Navigation --}}
        <div x-data="{
            open: false,
            isNotifOpen: false,
            activeMenu: 'main', // 'main', 'inventory', 'purchase', 'production', 'sales', 'finance'
            ignoreBackdrop: false,
            setActiveMenu(menu) {
                this.ignoreBackdrop = true;
                this.activeMenu = menu;
                setTimeout(() => { this.ignoreBackdrop = false; }, 500);
            },
            close() {
                if (this.ignoreBackdrop) return;
                this.open = false;
                setTimeout(() => { this.activeMenu = 'main'; }, 300);
            }
        }" 
        @notifs-toggled.window="isNotifOpen = $event.detail; if(isNotifOpen) open = false;"
        @ai-chat-opened.window="open = false"
        class="relative z-[900]" 
        x-cloak>
            {{-- Backdrop (to close on click outside) --}}
            <div x-show="open" 
                 x-transition.opacity.duration.300ms
                 class="fixed inset-0 bg-gradient-to-t from-white/90 via-white/60 to-white/20 dark:from-zinc-950/90 dark:via-zinc-950/60 dark:to-zinc-950/20 backdrop-blur-xl z-[890]"
                 @click="close()"></div>
                 
            <div class="fixed bottom-1 lg:bottom-6 left-3 lg:left-8 z-[900] pointer-events-none">
                <div class="pointer-events-auto">
                    <style>
                        .speed-dial-menu span {
                            white-space: nowrap !important;
                        }
                    </style>
                    {{-- Speed Dial Container --}}
            <div class="speed-dial-menu absolute bottom-full left-1 mb-4 origin-bottom-left flex flex-row gap-6 items-end w-max">
                {{-- MAIN MENU --}}
                <div x-show="open"
                     x-transition:enter="transition ease-out duration-300 transform"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6 shrink-0"
                     x-bind:class="activeMenu === 'main' || activeMenu === null || activeMenu === 'dashboard' ? 'flex' : 'hidden md:flex'">
                    {{-- User Profile / Settings --}}
                    <div class="flex items-center gap-3 cursor-pointer group" @click="setActiveMenu('settings')" @mouseenter="setActiveMenu('settings')" x-bind:class="activeMenu !== null && activeMenu !== 'settings' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="flex items-center justify-center w-12 h-12 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors" x-bind:class="activeMenu === 'dashboard' ? 'bg-indigo-200 dark:bg-indigo-800' : ''">
                            <flux:avatar :src="auth()->user()->avatarUrl()" :initials="auth()->user()->initials()" class="w-8 h-8" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'settings' ? 'scale-105' : ''">
                            {{ auth()->user()->name }}
                        </span>
                    </div>
                    {{-- Keuangan --}}
                    @canany(['finance.dashboard.view', 'finance.inbox.view'])
                    <div class="relative flex items-center gap-3 cursor-pointer group w-max" @click="setActiveMenu('finance')" @mouseenter="setActiveMenu('finance')" x-bind:class="activeMenu !== null && activeMenu !== 'finance' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="flex items-center justify-center w-12 h-12 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors text-zinc-600 dark:text-zinc-400" x-bind:class="activeMenu === 'finance' ? 'bg-blue-200 dark:bg-blue-800' : ''">
                            <flux:icon.banknotes class="w-5 h-5" />
                            <livewire:layout.sidebar-badge type="module_finance" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'finance' ? 'scale-105' : ''">Keuangan</span>
                        <div class="absolute -right-8 transition-all duration-300 transform flex items-center h-full z-[-1]"
                             x-bind:class="activeMenu === 'finance' ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'">
                            <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                    @endcanany
                    {{-- Penjualan --}}
                    @can('sales.dashboard.view')
                    <div class="relative flex items-center gap-3 cursor-pointer group w-max" @click="setActiveMenu('sales')" @mouseenter="setActiveMenu('sales')" x-bind:class="activeMenu !== null && activeMenu !== 'sales' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="relative flex items-center justify-center w-12 h-12 bg-emerald-100 dark:bg-emerald-900/40 border border-emerald-200 dark:border-emerald-800 rounded-full shadow-lg group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/60 transition-colors text-emerald-600 dark:text-emerald-400" x-bind:class="activeMenu === 'sales' ? 'bg-emerald-200 dark:bg-emerald-800' : ''">
                            <flux:icon.calculator class="w-5 h-5" />
                            <livewire:layout.sidebar-badge type="module_sales" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'sales' ? 'scale-105' : ''">Penjualan</span>
                        <div class="absolute -right-8 transition-all duration-300 transform flex items-center h-full z-[-1]"
                             x-bind:class="activeMenu === 'sales' ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'">
                            <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                    @endcan
                    {{-- Produksi --}}
                    @can('production.dashboard.view')
                    <div class="relative flex items-center gap-3 cursor-pointer group w-max" @click="setActiveMenu('production')" @mouseenter="setActiveMenu('production')" x-bind:class="activeMenu !== null && activeMenu !== 'production' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="relative flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900/40 border border-purple-200 dark:border-purple-800 rounded-full shadow-lg group-hover:bg-purple-200 dark:group-hover:bg-purple-900/60 transition-colors text-purple-600 dark:text-purple-400" x-bind:class="activeMenu === 'production' ? 'bg-purple-200 dark:bg-purple-800' : ''">
                            <flux:icon.wrench-screwdriver class="w-5 h-5" />
                            <livewire:layout.sidebar-badge type="module_production" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'production' ? 'scale-105' : ''">Produksi</span>
                        <div class="absolute -right-8 transition-all duration-300 transform flex items-center h-full z-[-1]"
                             x-bind:class="activeMenu === 'production' ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'">
                            <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                    @endcan
                    {{-- Pembelian --}}
                    @can('purchase.dashboard.view')
                    <div class="relative flex items-center gap-3 cursor-pointer group w-max" @click="setActiveMenu('purchase')" @mouseenter="setActiveMenu('purchase')" x-bind:class="activeMenu !== null && activeMenu !== 'purchase' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="relative flex items-center justify-center w-12 h-12 bg-sky-100 dark:bg-sky-900/40 border border-sky-200 dark:border-sky-800 rounded-full shadow-lg group-hover:bg-sky-200 dark:group-hover:bg-sky-900/60 transition-colors text-sky-600 dark:text-sky-400" x-bind:class="activeMenu === 'purchase' ? 'bg-sky-200 dark:bg-sky-800' : ''">
                            <flux:icon.shopping-cart class="w-5 h-5" />
                            <livewire:layout.sidebar-badge type="module_purchase" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'purchase' ? 'scale-105' : ''">Pembelian</span>
                        <div class="absolute -right-8 transition-all duration-300 transform flex items-center h-full z-[-1]"
                             x-bind:class="activeMenu === 'purchase' ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'">
                            <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                    @endcan
                    {{-- Inventory --}}
                    @can('inventory.view')
                    <div class="relative flex items-center gap-3 cursor-pointer group w-max" @click="setActiveMenu('inventory')" @mouseenter="setActiveMenu('inventory')" x-bind:class="activeMenu !== null && activeMenu !== 'inventory' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="relative flex items-center justify-center w-12 h-12 bg-amber-100 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-800 rounded-full shadow-lg group-hover:bg-amber-200 dark:group-hover:bg-amber-900/60 transition-colors text-amber-600 dark:text-amber-400" x-bind:class="activeMenu === 'inventory' ? 'bg-amber-200 dark:bg-amber-800' : ''">
                            <flux:icon.cube class="w-5 h-5" />
                            <livewire:layout.sidebar-badge type="module_inventory" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'inventory' ? 'scale-105' : ''">Inventory Gudang</span>
                        <div class="absolute -right-8 transition-all duration-300 transform flex items-center h-full z-[-1]"
                             x-bind:class="activeMenu === 'inventory' ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-4 pointer-events-none'">
                            <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 dark:text-zinc-500" />
                        </div>
                    </div>
                    @endcan
                    {{-- Artikel & Dokumen (CMS) --}}
                    <a href="{{ route('cms.posts.index') }}" wire:navigate @mouseenter="setActiveMenu('cms')" class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2" x-bind:class="activeMenu !== null && activeMenu !== 'cms' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 rounded-full shadow-lg group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/60 transition-colors text-indigo-600 dark:text-indigo-400" x-bind:class="activeMenu === 'cms' ? 'bg-indigo-200 dark:bg-indigo-800' : ''">
                            <flux:icon.newspaper class="w-5 h-5" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'cms' ? 'scale-105' : ''">Artikel & Dokumen</span>
                    </a>
                    
                    {{-- Dashboard --}}
                    @can('dashboard.main.view')
                    <a href="{{ route('dashboard') }}" wire:navigate @mouseenter="setActiveMenu('dashboard')" class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2" x-bind:class="activeMenu !== null && activeMenu !== 'dashboard' ? 'opacity-40 scale-95 grayscale' : 'opacity-100 scale-100'">
                        <div class="flex items-center justify-center w-12 h-12 bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 rounded-full shadow-lg group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/60 transition-colors text-indigo-600 dark:text-indigo-400" x-bind:class="activeMenu === 'dashboard' ? 'bg-indigo-200 dark:bg-indigo-800' : ''">
                            <flux:icon.home class="w-5 h-5" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 backdrop-blur-sm border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200 px-4 py-2 rounded-xl shadow-lg text-sm font-semibold group-hover:scale-105 transition-transform origin-left" x-bind:class="activeMenu === 'dashboard' ? 'scale-105' : ''">Dashboard Utama</span>
                    </a>
                    @endcan
                </div>
                                {{-- INVENTORY SUBMENU (MULTI-COLUMN) --}}
                <div x-show="open && activeMenu === 'inventory'"
                       x-transition:enter="transition ease-out duration-300 transform delay-75"
                       x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                       x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                       x-transition:leave="transition-none"
                       class="grid gap-8 max-h-[85vh] overflow-y-auto px-4 pb-8 w-full max-w-[calc(100vw-5rem)] md:max-w-[calc(100vw-20rem)]"
                       style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                     
                    {{-- Tombol Kembali Mobile --}}
                    <div class="md:hidden col-span-full mb-4">
                        <button @click="activeMenu = null" class="flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700">
                            <flux:icon.arrow-left class="w-4 h-4" />
                            <span class="text-sm font-semibold">Kembali</span>
                        </button>
                    </div>

                    {{-- COLUMN 1: Master Data --}}
                    <div class="flex flex-col gap-3 items-start min-w-[220px]">
                        
                        @canany(['inventory.warehouse.view', 'inventory.item.view', 'inventory.dashboard.view'])
                        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Master Data</span>
                        </div>
                        @endcanany

                        @can('inventory.warehouse.view')
                        <a href="{{ route('inventory.warehouses') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.building-storefront class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Gudang & Lokasi</span>
                        </a>
                        @endcan

                        @can('inventory.item.view')
                        <a href="{{ route('inventory.items') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.cube class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_item" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Data Barang</span>
                        </a>
                        @endcan

                        @can('inventory.dashboard.view')
                        <a href="{{ route('inventory') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.chart-pie class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Dashboard Inventory</span>
                        </a>
                        @endcan
                        
                    </div>

                    {{-- COLUMN 2: Inbound & Transfer --}}
                    <div class="flex flex-col gap-3 items-start min-w-[220px]">
                        
                        @canany(['inventory.dispatch.view', 'inventory.receipt.view'])
                        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Inbound (Masuk)</span>
                        </div>
                        @endcanany

                        @can('inventory.dispatch.view')
                        <a href="{{ route('inventory.dispatch') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.arrow-right-end-on-rectangle class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_inbound" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Alokasi Kedatangan</span>
                        </a>
                        @endcan

                        @can('inventory.receipt.view')
                        <a href="{{ route('inventory.production-receipts') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.arrow-down-tray class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_physical_receipt" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Penerimaan Fisik (QC)</span>
                        </a>
                        @endcan

                        @canany(['inventory.request.view', 'inventory.transfer.view'])
                        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Transfer & Permintaan</span>
                        </div>
                        @endcanany

                        @can('inventory.request.view')
                        <a href="{{ route('inventory.requests') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.inbox-arrow-down class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_request" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Permintaan Barang</span>
                        </a>
                        @endcan

                        @can('inventory.transfer.view')
                        <a href="{{ route('inventory.transfers') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.arrows-right-left class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_transfer" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Transfer Barang</span>
                        </a>
                        @endcan

                    </div>

                    {{-- COLUMN 3: Outbound & Laporan --}}
                    <div class="flex flex-col gap-3 items-start min-w-[220px]">
                        
                        @canany(['inventory.production.fulfillment', 'inventory.sales.delivery'])
                        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Outbound (Keluar)</span>
                        </div>
                        @endcanany

                        @can('inventory.production.fulfillment')
                        <a href="{{ route('inventory.fulfillments') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.clipboard-document-check class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_production_fulfillment" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Pemenuhan Produksi</span>
                        </a>
                        @endcan

                        @can('inventory.sales.delivery')
                        <a href="{{ route('inventory.sales-deliveries') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.truck class="w-4 h-4" />
                                <livewire:layout.sidebar-badge type="inventory_sales_delivery" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Pengiriman Penjualan</span>
                        </a>
                        @endcan

                        @canany(['inventory.movement.view', 'inventory.opname.view'])
                        <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                            <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Laporan & Stok</span>
                        </div>
                        @endcanany

                        @can('inventory.movement.view')
                        <a href="{{ route('inventory.movements') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.clock class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Riwayat Mutasi</span>
                        </a>
                        @endcan

                        @can('inventory.opname.view')
                        <a href="{{ route('inventory.stock-opname') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                            <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-amber-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                                <flux:icon.adjustments-horizontal class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Opname</span>
                        </a>
                        @endcan
                        
                    </div>
                </div>{{-- PURCHASE SUBMENU --}}
                <div x-show="open && activeMenu === 'purchase'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
@can('purchase.vendor.view')
                    <a href="{{ route('purchase.vendors.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-sky-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.building-office-2 class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Data Vendor</span>
                    </a>
                    @endcan
                    @canany(['purchase.vendor.view'])
<div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Master Data</span>
                    </div>
@endcanany
@can('purchase.order.create')
                    <a href="{{ route('purchase.orders.create') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-sky-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors border border-sky-100 dark:border-sky-900/50">
                            <flux:icon.plus class="w-5 h-5" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left text-sky-600 dark:text-sky-400">Buat PO Baru</span>
                    </a>
@endcan
@can('purchase.order.view')
                    <a href="{{ route('purchase.orders.kanban') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-sky-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.clipboard-document-list class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="purchase_order" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Daftar Purchase Order</span>
                    </a>
                    @endcan
@can('purchase.queue.view')
                    <a href="{{ route('purchase.queues.kanban') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-sky-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.queue-list class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="purchase_queue" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Daftar Permintaan</span>
                    </a>
                    @endcan
                    @canany(['purchase.order.view', 'purchase.order.create', 'purchase.queue.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Transaksi</span>
                    </div>
                    @endcanany
@can('purchase.dashboard.view')
                    <a href="{{ route('purchase.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-sky-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.shopping-cart class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Dashboard Pembelian</span>
                    </a>
                    @endcan
                    @canany(['purchase.dashboard.view'])
<div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Dashboard</span>
                    </div>
@endcanany

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- PRODUCTION SUBMENU --}}
                <div x-show="open && activeMenu === 'production'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
                    
                    @can('production.recipe.view')
                    <a href="{{ route('production.recipes') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-purple-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.document-text class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Resep Produksi</span>
                    </a>
                    @endcan
                    
                    @canany(['production.recipe.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Master Data</span>
                    </div>
                    @endcanany

                    @can('production.order.view')
                    <a href="{{ route('production.orders') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-purple-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.wrench-screwdriver class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="production_order" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Daftar Produksi</span>
                    </a>
                    @endcan

                    @canany(['production.order.view'])
<div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Operasional</span>
                    </div>
@endcanany

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- SALES SUBMENU --}}
                <div x-show="open && activeMenu === 'sales'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
@can('sales.customer.view')
                    <a href="{{ route('sales.customers.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-emerald-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.user-group class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Data Pelanggan</span>
                    </a>
                    @endcan
                    @canany(['sales.customer.view'])
<div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Master Data</span>
                    </div>
@endcanany
@can('sales.order.create')
                    <a href="{{ route('sales.orders.create') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-emerald-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors border border-emerald-100 dark:border-emerald-900/50">
                            <flux:icon.plus class="w-5 h-5" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left text-emerald-600 dark:text-emerald-400">Buat SO Baru</span>
                    </a>
                    @endcan
@can('sales.order.view')
                    <a href="{{ route('sales.orders.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-emerald-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.clipboard-document-list class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="sales_order" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Daftar Sales Order</span>
                    </a>
                    @endcan
                    @canany(['sales.order.view', 'sales.order.create'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Transaksi</span>
                    </div>
                    @endcanany

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- FINANCE SUBMENU --}}
                <div x-show="open && activeMenu === 'finance'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
                    
                    @can('finance.inbox.view')
                    <a href="{{ route('finance.inbox') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.inbox-arrow-down class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="finance_inbox" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Validasi Transaksi</span>
                    </a>
                    @endcan
                    
                    @can('finance.inbox.view')
                    <a href="{{ route('finance.payables') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.credit-card class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Hutang Pembelian</span>
                    </a>
                    @endcan
                    
                    @can('finance.transfers.view')
                    <a href="{{ route('finance.transfers') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.arrows-right-left class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="finance_transfer" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Mutasi Internal</span>
                    </a>
                    @endcan

                    @canany(['finance.transfers.view', 'finance.inbox.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Operasional</span>
                    </div>
                    @endcanany

                    @can('finance.dashboard.view')
                    <a href="{{ route('finance.dashboard') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.banknotes class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Dashboard Keuangan</span>
                    </a>
                    @endcan
                    
                    @canany(['finance.dashboard.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Dashboard & Mutasi</span>
                    </div>
                    @endcanany

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- SETTINGS SUBMENU --}}
                <div x-show="open && activeMenu === 'settings'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
{{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="flex items-center gap-3 group w-full text-left">
                            <div class="flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-full shadow-lg text-red-600 group-hover:bg-red-200 dark:group-hover:bg-red-900/60 transition-colors">
                                <flux:icon.arrow-right-start-on-rectangle class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium text-red-600">Logout</span>
                        </button>
                    </form>
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Sesi</span>
                    </div>
                    @can('settings.view')
                    <a href="{{ route('settings.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-600 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.cog-6-tooth class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Pengaturan Aplikasi</span>
                    </a>
                    
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Sistem</span>
                    </div>
                    @endcan

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- Notification Bell (Rides to Top) --}}
                <div class="z-10 transition-all duration-300 transform" x-bind:class="[
                    open ? 'opacity-0 scale-75 pointer-events-none' : 'opacity-100 scale-100',
                    isNotifOpen ? 'translate-y-[58px] -translate-x-1' : 'translate-y-0 translate-x-0'
                ]">
                    <livewire:layout.floating-notification-bell />
                </div>
            </div>
            {{-- Main Toggle Button (Hamburger / Close) --}}
            <div class="flex items-center gap-4 transition-all duration-300 transform origin-left" x-bind:class="isNotifOpen ? 'opacity-0 scale-75 pointer-events-none' : 'opacity-100 scale-100'">
                <button @click="open = !open; if(!open) setTimeout(() => activeMenu = null, 300); else activeMenu = null;" 
                        class="relative w-12 h-12 lg:w-16 lg:h-16 bg-indigo-600 text-white rounded-full shadow-xl hover:shadow-indigo-500/50 flex items-center justify-center transition-all duration-300 focus:outline-none hover:scale-105 z-20">
                    
                    <div x-show="!open" x-transition.opacity.duration.300ms class="absolute inset-0 pointer-events-none">
                        <livewire:layout.sidebar-badge type="total_all" />
                    </div>

                    <flux:icon.bars-3 class="w-5 h-5 lg:w-6 lg:h-6 absolute transition-all duration-300" 
                                      x-bind:class="open ? 'opacity-0 rotate-90 scale-50' : 'opacity-100 rotate-0 scale-100'" />
                    <flux:icon.x-mark class="w-5 h-5 lg:w-6 lg:h-6 absolute transition-all duration-300" 
                                      x-bind:class="open ? 'opacity-100 rotate-0 scale-100' : 'opacity-0 -rotate-90 scale-50'" />
                </button>
                {{-- Global Utility Buttons (Theme & Fullscreen) --}}
                <div class="flex items-center gap-3 transition-all duration-300 transform origin-left" 
                     x-bind:class="open ? 'opacity-100 translate-x-0 scale-100' : 'opacity-0 -translate-x-8 scale-75 pointer-events-none'">
                    {{-- Chat Shortcut --}}
                    <a href="{{ route('chat.index') }}" wire:navigate
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-[#00a884]/10 hover:bg-[#00a884]/20 dark:bg-[#00a884]/20 dark:hover:bg-[#00a884]/30 backdrop-blur-md border border-[#00a884]/30 rounded-full shadow-lg flex items-center justify-center text-[#00a884] transition-colors"
                            title="Buka WhatsApp">
                        <flux:icon.chat-bubble-left-right class="w-4 h-4 lg:w-5 lg:h-5" />
                    </a>
                    
                    {{-- AI Chat Shortcut dipindah keluar speed dial --}}
                    {{-- <livewire:ai-chat-widget /> --}}
                    
                    {{-- Theme Toggle --}}
                    <button type="button" 
                            x-on:click="
                                let newTheme = $flux.dark ? 'light' : 'dark';
                                if (document.startViewTransition) {
                                    document.startViewTransition(() => $flux.appearance = newTheme);
                                } else {
                                    $flux.appearance = newTheme;
                                }
                            "
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg flex items-center justify-center text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                            title="Ganti Tema">
                        <flux:icon.moon x-show="!$flux.dark" class="w-4 h-4 lg:w-5 lg:h-5" />
                        <flux:icon.sun x-cloak x-show="$flux.dark" class="w-4 h-4 lg:w-5 lg:h-5" />
                    </button>
                    {{-- Fullscreen Toggle --}}
                    <button type="button"
                            x-data="{ isFullscreen: false }"
                            x-on:fullscreenchange.document="isFullscreen = !!document.fullscreenElement"
                            x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg flex items-center justify-center text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                            title="Layar Penuh">
                        <flux:icon.arrows-pointing-out x-show="!isFullscreen" class="w-4 h-4 lg:w-5 lg:h-5" />
                        <flux:icon.arrows-pointing-in x-cloak x-show="isFullscreen" class="w-4 h-4 lg:w-5 lg:h-5" />
                    </button>
                </div>
            </div>
        </div>
            </div>
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

            // Mencegah fungsi tombol back secara agresif (memaksa user menggunakan menu UI)
            history.pushState(null, null, window.location.href);
            window.addEventListener('popstate', function(event) {
                // Hentikan event agar tidak ditangkap oleh router SPA (Livewire)
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                
                // Paksa tetap di URL saat ini
                history.pushState(null, null, window.location.href);
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
                    
                    @can('finance.inbox.view')
                    <a href="{{ route('finance.inbox') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.inbox-arrow-down class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="finance_inbox" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Validasi Transaksi</span>
                    </a>
                    @endcan
                    
                    @can('finance.inbox.view')
                    <a href="{{ route('finance.payables') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.credit-card class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Hutang Pembelian</span>
                    </a>
                    @endcan
                    
                    @can('finance.transfers.view')
                    <a href="{{ route('finance.transfers') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 relative group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.arrows-right-left class="w-4 h-4" />
                            <livewire:layout.sidebar-badge type="finance_transfer" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Mutasi Internal</span>
                    </a>
                    @endcan

                    @canany(['finance.transfers.view', 'finance.inbox.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Operasional</span>
                    </div>
                    @endcanany

                    @can('finance.dashboard.view')
                    <a href="{{ route('finance.dashboard') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-500 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.banknotes class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Dashboard Keuangan</span>
                    </a>
                    @endcan
                    
                    @canany(['finance.dashboard.view'])
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Dashboard & Mutasi</span>
                    </div>
                    @endcanany

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- SETTINGS SUBMENU --}}
                <div x-show="open && activeMenu === 'settings'"
                     x-transition:enter="transition ease-out duration-300 transform delay-75"
                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-none"
                     class="flex flex-col-reverse gap-3 items-start max-h-[calc(100vh-10rem)]  custom-scrollbar px-6 -mx-6">
{{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <button type="submit" class="flex items-center gap-3 group w-full text-left">
                            <div class="flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-full shadow-lg text-red-600 group-hover:bg-red-200 dark:group-hover:bg-red-900/60 transition-colors">
                                <flux:icon.arrow-right-start-on-rectangle class="w-4 h-4" />
                            </div>
                            <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium text-red-600">Logout</span>
                        </button>
                    </form>
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Sesi</span>
                    </div>
                    @can('settings.view')
                    <a href="{{ route('settings.index') }}" wire:navigate class="flex items-center gap-3 group transition-transform duration-300 hover:translate-x-2">
                        <div class="flex items-center justify-center w-10 h-10 bg-white dark:bg-zinc-800 rounded-full shadow-lg text-zinc-600 group-hover:bg-zinc-100 dark:group-hover:bg-zinc-700 transition-colors">
                            <flux:icon.cog-6-tooth class="w-4 h-4" />
                        </div>
                        <span class="bg-white/95 dark:bg-zinc-800/95 px-3 py-1.5 rounded-lg shadow-md text-sm font-medium group-hover:scale-105 transition-transform origin-left">Pengaturan Aplikasi</span>
                    </a>
                    
                    <div class="flex items-center gap-3 w-full opacity-70 mb-1 mt-2 pointer-events-none">
                        <div class="w-10 flex justify-center"><div class="w-1.5 h-1.5 rounded-full bg-zinc-400 dark:bg-zinc-600"></div></div>
                        <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Sistem</span>
                    </div>
                    @endcan

                    {{-- Tombol Kembali Mobile --}}
                    <button @click="activeMenu = null" class="md:hidden flex items-center gap-2 text-zinc-500 bg-white dark:bg-zinc-800 px-4 py-2 rounded-full shadow-sm border border-zinc-200 dark:border-zinc-700 mb-2 mt-4">
                        <flux:icon.arrow-left class="w-4 h-4" />
                        <span class="text-sm font-semibold">Kembali</span>
                    </button>
                </div>
                {{-- Notification Bell (Rides to Top) --}}
                <div class="z-10 transition-all duration-300 transform" x-bind:class="[
                    open ? 'opacity-0 scale-75 pointer-events-none' : 'opacity-100 scale-100',
                    isNotifOpen ? 'translate-y-[58px] -translate-x-1' : 'translate-y-0 translate-x-0'
                ]">
                    <livewire:layout.floating-notification-bell />
                </div>
            </div>
            {{-- Main Toggle Button (Hamburger / Close) --}}
            <div class="flex items-center gap-4 transition-all duration-300 transform origin-left" x-bind:class="isNotifOpen ? 'opacity-0 scale-75 pointer-events-none' : 'opacity-100 scale-100'">
                <button @click="open = !open; if(!open) setTimeout(() => activeMenu = null, 300); else activeMenu = null;" 
                        class="relative w-12 h-12 lg:w-16 lg:h-16 bg-indigo-600 text-white rounded-full shadow-xl hover:shadow-indigo-500/50 flex items-center justify-center transition-all duration-300 focus:outline-none hover:scale-105 z-20">
                    
                    <div x-show="!open" x-transition.opacity.duration.300ms class="absolute inset-0 pointer-events-none">
                        <livewire:layout.sidebar-badge type="total_all" />
                    </div>

                    <flux:icon.bars-3 class="w-5 h-5 lg:w-6 lg:h-6 absolute transition-all duration-300" 
                                      x-bind:class="open ? 'opacity-0 rotate-90 scale-50' : 'opacity-100 rotate-0 scale-100'" />
                    <flux:icon.x-mark class="w-5 h-5 lg:w-6 lg:h-6 absolute transition-all duration-300" 
                                      x-bind:class="open ? 'opacity-100 rotate-0 scale-100' : 'opacity-0 -rotate-90 scale-50'" />
                </button>
                {{-- Global Utility Buttons (Theme & Fullscreen) --}}
                <div class="flex items-center gap-3 transition-all duration-300 transform origin-left" 
                     x-bind:class="open ? 'opacity-100 translate-x-0 scale-100' : 'opacity-0 -translate-x-8 scale-75 pointer-events-none'">
                    {{-- Chat Shortcut --}}
                    <a href="{{ route('chat.index') }}" wire:navigate
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-[#00a884]/10 hover:bg-[#00a884]/20 dark:bg-[#00a884]/20 dark:hover:bg-[#00a884]/30 backdrop-blur-md border border-[#00a884]/30 rounded-full shadow-lg flex items-center justify-center text-[#00a884] transition-colors"
                            title="Buka WhatsApp">
                        <flux:icon.chat-bubble-left-right class="w-4 h-4 lg:w-5 lg:h-5" />
                    </a>
                    
                    {{-- AI Chat Shortcut dipindah keluar speed dial --}}
                    {{-- <livewire:ai-chat-widget /> --}}
                    
                    {{-- Theme Toggle --}}
                    <button type="button" 
                            x-on:click="
                                let newTheme = $flux.dark ? 'light' : 'dark';
                                if (document.startViewTransition) {
                                    document.startViewTransition(() => $flux.appearance = newTheme);
                                } else {
                                    $flux.appearance = newTheme;
                                }
                            "
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg flex items-center justify-center text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                            title="Ganti Tema">
                        <flux:icon.moon x-show="!$flux.dark" class="w-4 h-4 lg:w-5 lg:h-5" />
                        <flux:icon.sun x-cloak x-show="$flux.dark" class="w-4 h-4 lg:w-5 lg:h-5" />
                    </button>
                    {{-- Fullscreen Toggle --}}
                    <button type="button"
                            x-data="{ isFullscreen: false }"
                            x-on:fullscreenchange.document="isFullscreen = !!document.fullscreenElement"
                            x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()"
                            class="w-10 h-10 lg:w-12 lg:h-12 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border border-zinc-200 dark:border-zinc-700 rounded-full shadow-lg flex items-center justify-center text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                            title="Layar Penuh">
                        <flux:icon.arrows-pointing-out x-show="!isFullscreen" class="w-4 h-4 lg:w-5 lg:h-5" />
                        <flux:icon.arrows-pointing-in x-cloak x-show="isFullscreen" class="w-4 h-4 lg:w-5 lg:h-5" />
                    </button>
                </div>
            </div>
        </div>
            </div>
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
        <x-loading></x-loading>
        <div x-data @barcode-scanned.window="if (window.Livewire) { window.Livewire.dispatch('barcode-scanned', { code: $event.detail.code }); }"></div>
        <x-camera-scanner />
        {{-- AI Chat Widget (standalone, di luar speed dial) --}}
        @if(!request()->is('chat*'))
        <div class="fixed bottom-3 lg:bottom-8 right-4 lg:right-8 z-[950]">
            <livewire:ai-chat-widget />
        </div>
        @endif
    </body>
</html>
