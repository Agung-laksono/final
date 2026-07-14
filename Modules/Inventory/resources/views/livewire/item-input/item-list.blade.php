<?php

use function Livewire\Volt\{state, on, with, usesPagination, layout};
use Modules\Inventory\Models\Item;
use Livewire\WithPagination;

layout('layouts.app');

usesPagination(theme: 'tailwind');

state([
    'search' => '',
    'viewMode' => 'grid', // 'table' or 'grid'
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 24,
]);

$loadMore = function () {
    $this->perPage += 24;
};

$sort = function ($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
};

// Fetch items dynamically whenever rendered (to support pagination and search)
$getItems = function () {
    return Item::with([
        'subCategory','category', 'unit', 'type', 'warehouses',
        'customVariants' => fn($q) => $q->with('salesOrder.customer')->latest('id')->limit(5)
    ])
        ->withCount('customVariants')
        ->when($this->search, function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhereHas('category', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  })->orWhereHas('warehouses', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  })->orWhereHas('type', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  })->orWhereHas('unit', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  })->orWhereHas('subCategory', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  });
        })
        ->when($this->sortBy, function ($query) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        })
        ->paginate($this->perPage);
};

// Listeners to trigger a re-render when an item is saved/deleted or inventory changes via Reverb
on([
    'item-saved' => function () {},
    'item-updated' => function () {},
    'item-deleted' => function () {}
]);

$handlePusherUpdate = function ($message) {
    // Dipanggil dari AlpineJS ketika mendapat event Pusher
    // Komponen akan re-render otomatis dan memunculkan toast
    \Flux\Flux::toast('Data inventaris diperbarui: ' . ($message ?? ''));
};

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
};

$delete = function (Item $item) {
    // Bersihkan file gambar dari storage sebelum datanya dihapus dari database
    if ($item->image) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
    }
    
    $item->delete();
    $this->dispatch('item-deleted');
};
?>

<div x-data="{
    init() {
        // Cek parameter URL untuk membuka detail otomatis
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('show_item')) {
            setTimeout(() => {
                $dispatch('open-item-detail', { id: urlParams.get('show_item') });
            }, 300); // Sedikit jeda agar modal siap
            // Bersihkan URL agar modal tidak terbuka ulang jika halaman direfresh
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        if (window.Echo) {
            window.Echo.channel('inventory')
                .listen('InventoryUpdated', (event) => {
                    // Trigger Livewire to refresh the list and show toast
                    $wire.handlePusherUpdate(event.message);
                });
        }
    }
}">
    {{-- Smart Sticky Header --}}
    <x-sticky-header class="flex flex-col sm:flex-row justify-end tab-y:justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden sm:block w-max">
            <flux:heading size="lg">Pengelolaan Barang</flux:heading>
            <flux:subheading>Daftar seluruh inventaris barang yang tersedia.</flux:subheading>
        </div>
        <div class="flex items-center tab-y:justify-between gap-3">
            {{-- Search Bar --}}
            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barang..." />
            </div>

            <x-grid-or-table wire:model="viewMode" :mode="$viewMode" />
            @can('inventory.item.create')
                <flux:button wire:click="$dispatch('open-item-modal')" variant="primary" icon="plus">
                    <span class="hidden md:inline">Barang</span>
                </flux:button>
            @endcan
        </div>
    </x-sticky-header>

    @if ($viewMode === 'table')
        {{-- Tampilan Tabel --}}
        <div class="pl-2 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-6 shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Info Barang</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'purchase_price'" :direction="$sortDirection" wire:click="sort('purchase_price')">Harga</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'min_stock'" :direction="$sortDirection" wire:click="sort('min_stock')">Stok</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortDirection" wire:click="sort('is_active')">Status</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getItems() as $item)
                        <flux:table.row :key="$item->id" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:dblclick="$dispatch('open-item-detail', { id: {{ $item->id }} })">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                        @if ($item->image)
                                            <img src="{{ asset('storage/' . $item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.photo class="w-5 h-5" />
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
                                            @if ($item->custom_variants_count > 0)
                                                <div x-data="{ showTooltip: false }" 
                                                     class="relative flex items-center gap-1.5 ml-2 cursor-pointer hover:opacity-80 transition-opacity"
                                                     @mouseenter="showTooltip = true"
                                                     @mouseleave="showTooltip = false"
                                                     @click.stop="showTooltip = !showTooltip">
                                                    
                                                    <div class="flex -space-x-1 overflow-hidden">
                                                        @foreach($item->customVariants as $variant)
                                                            @if(!empty($variant->custom_attachments))
                                                                <img class="inline-block h-5 w-5 rounded-full ring-1 ring-white object-cover bg-white shadow-sm" 
                                                                     src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" 
                                                                     alt="Varian">
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                    <span class="text-[10px] font-medium text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">
                                                        {{ $item->custom_variants_count }} Varian
                                                    </span>
                                                    
                                                    {{-- Popover Overlay --}}
                                                    <div x-show="showTooltip" 
                                                         x-transition:enter="transition ease-out duration-200"
                                                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave="transition ease-in duration-150"
                                                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                         x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                                         class="absolute top-6 left-0 w-64 bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden pointer-events-none z-50"
                                                         style="display: none; transform-origin: top left;">
                                                         
                                                        <div class="bg-indigo-600 px-3 py-2 text-white flex justify-between items-center">
                                                            <div class="font-bold text-xs flex items-center gap-1.5">
                                                                <flux:icon.swatch class="w-3.5 h-3.5" /> 
                                                                Riwayat Varian
                                                            </div>
                                                            <div class="text-[10px] bg-indigo-800/50 px-1.5 py-0.5 rounded">{{ $item->custom_variants_count }} Total</div>
                                                        </div>
                                                        
                                                        <div class="p-2 flex flex-col gap-2 max-h-48 overflow-y-auto custom-scrollbar">
                                                            @foreach($item->customVariants as $variant)
                                                                <div class="flex gap-2 p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-700/50 shadow-sm">
                                                                    @if(!empty($variant->custom_attachments))
                                                                        <img src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" 
                                                                             class="w-10 h-10 rounded-md object-cover border border-zinc-200 dark:border-zinc-700 shadow-sm shrink-0 bg-white">
                                                                    @else
                                                                        <div class="w-10 h-10 rounded-md bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700">
                                                                            <flux:icon.photo class="w-4 h-4 text-zinc-400" />
                                                                        </div>
                                                                    @endif
                                                                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                                                                        <div class="text-[10px] font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                                                            {{ $variant->salesOrder?->customer?->name ?? 'Pelanggan Umum' }}
                                                                        </div>
                                                                        <div class="text-[9px] text-zinc-500 mb-0.5">
                                                                            {{ $variant->created_at?->format('d M Y') ?? 'Tidak diketahui' }}
                                                                        </div>
                                                                        @if(!empty($variant->custom_attributes))
                                                                            <div class="text-[9px] text-indigo-600 dark:text-indigo-400 truncate font-medium">
                                                                                {{ collect($variant->custom_attributes)->map(fn($v, $k) => $k . ': ' . (is_array($v) ? implode(', ', $v) : $v))->implode(' | ') }}
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">{{ $item->code }} &bull; {{ $item->category?->name ?? 'Tanpa Kategori' }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="text-sm">Beli: Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</div>
                                <div class="text-sm font-medium text-emerald-600">Jual: Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                            </flux:table.cell>

                            <flux:table.cell>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-base">{{ $item->warehouses->sum('pivot.stock') }}</span>
                                    <span class="text-sm font-medium">{{ $item->unit?->name ?? '-' }}</span>
                                    @if ($item->min_stock > 0)
                                        <span class="text-xs px-1.5 py-0.5 rounded-full bg-zinc-100 text-zinc-600">Min: {{ $item->min_stock }}</span>
                                    @endif
                                </div>
                            </flux:table.cell>

                            <flux:table.cell>
                                @if (!$item->is_approved)
                                    <flux:badge color="amber" size="sm">Menunggu Persetujuan</flux:badge>
                                @else
                                    <flux:badge color="{{ $item->is_active ? 'green' : 'zinc' }}" size="sm">
                                        {{ $item->is_active ? 'Aktif' : 'Non-aktif' }}
                                    </flux:badge>
                                @endif
                                
                                @if ($item->requires_label)
                                    <flux:badge color="blue" size="sm" class="ml-1">Berlabel</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                <div class="flex flex-col items-center justify-center py-8 text-zinc-500">
                                    <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                                    <p>Belum ada data barang.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @else
        {{-- Tampilan Grid (Vertical Cards dengan Gambar Mencolok) --}}
        <div class="@container w-full">
            <div class="grid grid-cols-2 @md:grid-cols-3 @2xl:grid-cols-4 @4xl:grid-cols-5 @6xl:grid-cols-7 gap-4 mb-6">
            @forelse ($this->getItems() as $item)
                <div wire:key="item-{{ $item->id }}" 
                     x-data="{ loading: false, activeVariant: null }"
                     x-on:dblclick="loading = true; $wire.dispatch('open-item-detail', { id: {{ $item->id }} })"
                     x-on:item-detail-modal-opened.window="loading = false"
                     class="group relative flex flex-col bg-white dark:bg-zinc-900 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 border border-zinc-200/80 dark:border-zinc-800 hover:border-blue-500/30 dark:hover:border-blue-400/30 cursor-pointer hover:scale-[1.02]">
                    
                    {{-- Loading Overlay --}}
                    <div x-show="loading" x-cloak class="absolute inset-0 bg-white/50 dark:bg-zinc-900/50 z-50 flex flex-col items-center justify-center backdrop-blur-sm rounded-xl">
                        <flux:icon.arrow-path class="w-8 h-8 animate-spin text-blue-500 mb-2" />
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">Membuka Detail...</span>
                    </div>

                    @if (!$item->is_approved)
                    <div class="absolute z-2 top-0 w-full h-full bg-[#000000ba] flex flex-col items-center justify-center">
                        <span class="text-bold text-amber-500 font-bold tracking-widest uppercase">MENUNGGU</span>
                        <span class="text-white text-xs mt-1">PERSETUJUAN</span>
                    </div>
                    @elseif (!$item->is_active)
                    <div class="absolute z-2 top-0 w-full h-full bg-black/60 backdrop-blur-[2px] flex items-center justify-center">
                        <div class="bg-rose-500 text-white px-3 py-1 rounded shadow-lg transform -rotate-12 font-black tracking-widest border-2 border-white">NON ACTIVE</div>
                    </div>
                    @endif
                    
                    {{-- Gambar Atas (Mencolok) --}}
                    <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 overflow-hidden border-b border-zinc-100 dark:border-zinc-800/50">
                        {{-- Image Swap System --}}
                        <template x-if="activeVariant && activeVariant.image">
                            <img :src="activeVariant.image" class="absolute inset-0 w-full h-full object-cover z-10 transition-all duration-300">
                        </template>
                        
                        @if ($item->image)
                            <img x-data="{ loaded: false }" 
                                 x-init="if ($el.complete) loaded = true"
                                 @load="loaded = true" 
                                 :class="loaded ? 'opacity-100' : 'opacity-0 scale-95'"
                                 src="{{ asset('storage/' . $item->image) }}" 
                                 loading="lazy" 
                                 decoding="async"
                                 class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110">
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center text-zinc-300">
                                <flux:icon.photo class="w-10 h-10 mb-1 opacity-50" />
                            </div>
                            <div class="absolute inset-0" style="background-image: radial-gradient(circle, #e4e4e7 1px, transparent 1px); background-size: 10px 10px; opacity: 0.5;"></div>
                        @endif
                        
                        {{-- Mini Variants Carousel (Bawah Kiri) --}}
                        @if ($item->custom_variants_count > 0)
                            <div class="absolute bottom-2 left-2 z-50 right-2 flex items-center justify-between pointer-events-auto">
                                <div class="flex -space-x-1.5 overflow-hidden p-1">
                                    @foreach($item->customVariants as $variant)
                                        @php
                                            $variantData = [
                                                'image' => !empty($variant->custom_attachments) ? asset('storage/' . $variant->custom_attachments[0]) : null,
                                                'customer' => $variant->salesOrder?->customer?->name ?? 'Pelanggan Umum',
                                                'date' => $variant->created_at?->format('d M Y') ?? '',
                                                'attributes' => !empty($variant->custom_attributes) ? collect($variant->custom_attributes)->map(fn($v, $k) => $k . ': ' . (is_array($v) ? implode(', ', $v) : $v))->implode(' | ') : ''
                                            ];
                                        @endphp
                                        @if($variantData['image'])
                                            <img @mouseenter="activeVariant = {{ json_encode($variantData) }}"
                                                 @mouseleave="activeVariant = null"
                                                 @click.stop="activeVariant = activeVariant ? null : {{ json_encode($variantData) }}"
                                                 class="inline-block h-6 w-6 rounded-full ring-2 ring-white object-cover bg-white shadow-sm cursor-pointer transition-transform hover:scale-110 hover:z-10" 
                                                 src="{{ $variantData['image'] }}" 
                                                 alt="Varian">
                                        @endif
                                    @endforeach
                                </div>
                                @if($item->custom_variants_count > count($item->customVariants))
                                    <button @click.stop="loading = true; $wire.dispatch('open-item-detail', { id: {{ $item->id }}, tab: 'variants' })"
                                            class="bg-black/50 hover:bg-indigo-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded backdrop-blur-sm shadow-sm transition-all hover:scale-105 pointer-events-auto cursor-pointer">
                                        +{{ $item->custom_variants_count - count($item->customVariants) }}
                                    </button>
                                @endif
                            </div>
                        @endif
                        
                        {{-- Badge Tipe Barang (Kiri Atas) --}}
                        <div class="absolute top-2 left-2 flex z-0">
                            @if ($item->type)
                                @php
                                    $colors = [
                                        'bahan baku utama' => 'bg-amber-600/90',
                                        'bahan baku penolong' => 'bg-amber-500/90',
                                        'produk jadi' => 'bg-emerald-600/90',
                                        'barang setengah jadi' => 'bg-sky-500/90',
                                        'jasa' => 'bg-purple-500/90',
                                        'aset' => 'bg-slate-600/90',
                                        'custom' => 'bg-rose-500/90',
                                    ];
                                    $typeName = strtolower($item->type->name);
                                    $defaultColors = ['bg-indigo-500/90', 'bg-rose-500/90', 'bg-cyan-500/90', 'bg-teal-500/90', 'bg-fuchsia-500/90'];
                                    $color = $colors[$typeName] ?? $defaultColors[$item->type->id % count($defaultColors)];
                                @endphp
                                <div class="{{ $color }} backdrop-blur-sm text-white rounded-md px-1.5 py-0.5 text-[8px] font-bold tracking-wider uppercase shadow-sm border border-white/20">
                                    {{ $item->type->name }}
                                </div>
                            @endif
                        </div>
                        
                        {{-- Overlay Status Kanan --}}
                        <div class="absolute top-2 right-2 flex gap-1.5 shadow-sm z-0">
                            @if ($item->requires_label)
                                <div class="bg-blue-500/90 backdrop-blur-sm text-white rounded-md px-1.5 py-0.5" title="Berlabel SN">
                                    <flux:icon.qr-code class="w-3 h-3" />
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Informasi Bawah (Jelas & Padat) --}}
                    <div class="p-3 flex flex-col flex-1">
                        {{-- Kategori & Kode --}}
                        <div class="flex justify-between items-start mb-1.5 gap-2">
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-[8px] font-bold uppercase tracking-widest truncate transition-colors"
                                      :class="activeVariant ? 'text-indigo-500' : 'text-zinc-400 dark:text-zinc-500'"
                                      x-text="activeVariant ? 'Riwayat Varian' : '{{ addslashes($item->category?->name ?? 'Tanpa Kategori') }}'"></span>
                                @if($item->subCategory)
                                    <span class="text-[7px] font-semibold text-zinc-400/80 uppercase tracking-wider truncate"
                                          x-show="!activeVariant">{{ $item->subCategory->name }}</span>
                                @endif
                            </div>
                            <span class="text-[9px] font-mono font-medium text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5"
                                  x-text="activeVariant ? activeVariant.date : '{{ $item->code }}'"></span>
                        </div>
                        
                        {{-- Nama Barang & Deskripsi --}}
                        <div class="mb-2 flex-1 overflow-hidden">
                            <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 text-[11px] leading-tight truncate transition-colors" 
                                :title="activeVariant ? activeVariant.customer : '{{ addslashes($item->name) }}'"
                                x-text="activeVariant ? activeVariant.customer : '{{ addslashes($item->name) }}'">
                            </h3>
                            <p class="text-[9px] mt-0.5 leading-tight truncate transition-colors" 
                               :class="activeVariant ? 'text-indigo-600 dark:text-indigo-400 font-medium' : 'text-zinc-500 dark:text-zinc-400'"
                               :title="activeVariant ? activeVariant.attributes : '{{ addslashes($item->description) }}'"
                               x-text="activeVariant ? activeVariant.attributes : '{{ addslashes($item->description) }}'"></p>
                        </div>
                        
                        {{-- Harga & Stok --}}
                        <div class="mt-auto flex items-end justify-between pt-2 border-t border-zinc-100/80 dark:border-zinc-800/50">
                            <div class="flex flex-col gap-0.5">
                                <div class="flex items-center" title="Harga Beli">
                                    <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 leading-none">Rp{{ number_format($item->purchase_price, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center" title="Harga Jual">
                                    <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 leading-none">Rp{{ number_format($item->selling_price, 0, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-0.5 text-right pb-0.5">
                                <span class="font-bold text-zinc-700 dark:text-zinc-300 text-[13px] leading-none">{{ $item->warehouses->sum('pivot.stock') }}</span>
                                <span class="text-[9px] font-medium text-zinc-400 dark:text-zinc-500">{{ $item->unit?->name ?? '-' }}</span>
                            </div>
                        </div>

                        {{-- Micro-Row: Pipeline Stats --}}
                        @php
                            $stats = $item->getInventoryStats();
                            $po = $stats['purchase_order'] + $stats['purchase_queue'];
                            $wip = $stats['production'];
                            $book = $stats['sales_committed'];
                            $atp = $item->getATP();
                            
                            $badges = [];
                            if($po > 0) $badges[] = '<span title="Dalam Pembelian">PO: '.$po.'</span>';
                            if($wip > 0) $badges[] = '<span title="Dalam Produksi">WIP: '.$wip.'</span>';
                            if($book > 0) $badges[] = '<span title="Pesanan Keluar">Book: '.$book.'</span>';
                            
                            $atpColor = $atp > 0 ? 'text-emerald-500/90' : 'text-rose-500/90';
                            $badges[] = '<span title="Siap Jual (Available to Promise)" class="'.$atpColor.' font-bold">ATP: '.$atp.'</span>';
                        @endphp
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[8px] font-medium text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-800/30 px-1.5 py-0.5 rounded border border-zinc-100 dark:border-zinc-800/80">
                            {!! implode(' <span class="text-zinc-300 dark:text-zinc-700">&bull;</span> ', $badges) !!}
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full flex flex-col items-center justify-center py-12 text-zinc-500 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
                    <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                    <p>Belum ada data barang.</p>
                </div>
            @endforelse
        </div>
        </div>
    @endif

    {{-- Progress Bar & Load More --}}
    <x-load-more :paginator="$this->getItems()" item-name="barang" />

    {{-- Sisipkan Modal Form dan Detail --}}
    <livewire:item-input.item-detail />
    <livewire:global.item-variants-modal />
</div>
