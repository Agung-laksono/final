<?php

use function Livewire\Volt\{state, on, with, usesPagination};
use Modules\Inventory\Models\Item;
use Livewire\WithPagination;

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
    return Item::with(['subCategory','category', 'unit', 'type', 'warehouses'])
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
                        <flux:table.row :key="$item->id" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:click="$dispatch('open-item-detail', { id: {{ $item->id }} })">
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
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</div>
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
                                <flux:badge color="{{ $item->is_active ? 'green' : 'zinc' }}" size="sm">
                                    {{ $item->is_active ? 'Aktif' : 'Non-aktif' }}
                                </flux:badge>
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
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
            @forelse ($this->getItems() as $item)
                <div x-on:click="$dispatch('open-item-detail', { id: {{ $item->id }} })" class="relative bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden hover:scale-102 hover:border-blue-500/50 hover:shadow-lg transition-all cursor-pointer group flex flex-col">
                @if (!$item->is_active)
                <div class="absolute z-2 top-0 w-full h-full bg-[#000000ba] flex items-center justify-center">
                    <span class="text-bold text-white">NON ACTIVE</span>
                </div>
                @endif
                    {{-- Gambar Atas (Mencolok) --}}
                    <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 overflow-hidden border-b border-zinc-100 dark:border-zinc-800/50">
                        @if ($item->image)
                            <img src="{{ asset('storage/' . $item->image) }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-zinc-300">
                                <flux:icon.photo class="w-10 h-10" />
                            </div>
                        @endif
                        
                        {{-- Overlay Status --}}
                        <div class="absolute top-2 right-2 flex gap-1.5 shadow-sm">
                            @if ($item->requires_label)
                                <div class="bg-blue-500/90 backdrop-blur-sm text-white rounded-full p-1" title="Berlabel SN">
                                    <flux:icon.qr-code class="w-3 h-3" />
                                </div>
                            @endif
                            <!-- <div class="w-5 h-5 rounded-full border-[2.5px] border-white dark:border-zinc-800 {{ $item->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}" title="{{ $item->is_active ? 'Aktif' : 'Non-aktif' }}"></div> -->
                        </div>
                    </div>
                    
                    {{-- Informasi Bawah (Jelas & Padat) --}}
                    <div class="p-3 flex flex-col flex-1">
                        {{-- Kategori & Kode --}}
                        <div class="flex justify-between items-center mb-1.5 gap-2">
                            <span class="text-[6px] hover:text-[9px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest truncate">{{ $item->category?->name ?? 'Tanpa Kategori' }} / {{ $item->subCategory?->name ?? '-' }}</span>
                            <span class="text-[9px] font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-zinc-600 dark:text-zinc-400 shrink-0 border border-zinc-200 dark:border-zinc-700/50">{{ $item->code }}</span>
                        </div>
                        
                        {{-- Nama Barang --}}
                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-[13px] leading-snug line-clamp-2 mb-3 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            {{ $item->name }}
                        </h3>
                        
                        {{-- Harga & Stok --}}
                        <div class="mt-auto pt-2.5 border-t border-zinc-100 dark:border-zinc-800/50 flex justify-between items-end">
                            <div class="flex flex-col">
                                <span class=" text-[11px] font-medium text-zinc-400 mb-0.5">Harga
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400leading-none"> jual</span> /
                                     <span class="font-bold text-gray-600 dark:text-gray-400leading-none"> beli</span>
                                </span>
                                <span class="grid grid-cols-1 md:flex">
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 text-xs md:text-sm leading-none">Rp{{ number_format($item->selling_price, 0, ',', '.') }} /</span>
                                    <span class="font-bold text-gray-600 dark:text-gray-400 text-[9px] leading-none mt-1">Rp{{ number_format($item->purchase_price, 0, ',', '.') }}</span>
                                </span>
                            </div>
                            
                            <div class="flex flex-col items-end">
                                <span class="text-[9px] font-medium text-zinc-400 mb-0.5">Stok</span>
                                <div class="flex items-baseline gap-0.5">
                                    <span class="font-bold text-zinc-800 dark:text-zinc-200 text-[16px] leading-none">{{ $item->warehouses->sum('pivot.stock') }}</span>
                                    <span class="text-[9px] text-zinc-500">{{ $item->unit?->name ?? '-' }}</span>
                                </div>
                            </div>
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
    @endif

    {{-- Progress Bar & Load More --}}
    <x-load-more :paginator="$this->getItems()" item-name="barang" />

    {{-- Sisipkan Modal Form dan Detail --}}
    <livewire:item-input.item-detail />
</div>
