<?php

use function Livewire\Volt\{state, on, with, usesPagination};
use Modules\Inventory\Models\Warehouse;

usesPagination(theme: 'tailwind');

state([
    'search' => '',
    'viewMode' => 'grid', // 'table' or 'grid'
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 12,
]);

$loadMore = function () {
    $this->perPage += 12;
};

$sort = function ($column) {
    if ($this->sortBy === $column) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }
};

$getWarehouses = function () {
    return Warehouse::query()
        ->with(['items' => function($q) {
            $q->select('items.id', 'items.category_id');
        }, 'items.category:id,name'])
        ->when($this->search, function ($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('address', 'like', '%' . $this->search . '%');
        })
        ->when($this->sortBy, function ($query) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        })
        ->paginate($this->perPage);
};

on(['warehouse-saved' => function () {}]);
on(['warehouse-deleted' => function () {}]);

$setViewMode = function ($mode) {
    $this->viewMode = $mode;
};

?>

<div>
    {{-- Smart Sticky Header --}}
    <x-sticky-header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden md:block">
            <flux:heading size="lg">Pengelolaan Gudang</flux:heading>
            <flux:subheading>Daftar seluruh gudang (warehouse) untuk penempatan stok barang.</flux:subheading>
        </div>
        <div class="flex items-center gap-3">
            {{-- Search Bar --}}
            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari gudang..." />
            </div>

            {{-- Toggle View --}}
            <x-grid-or-table wire:model="viewMode" :mode="$viewMode" />

            <flux:button x-on:click="$dispatch('open-warehouse-form')" variant="primary" icon="plus">
                <span class="hidden md:inline">
                    Gudang
                </span>
            </flux:button>
        </div>
    </x-sticky-header>

    @if ($viewMode === 'table')
        {{-- Tampilan Tabel --}}
        <div wire:key="view-table" class="pl-2 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden mb-6 shadow-sm">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Informasi Gudang</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'address'" :direction="$sortDirection" wire:click="sort('address')">Alamat</flux:table.column>
                    <flux:table.column>Ringkasan Stok</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getWarehouses() as $warehouse)
                        <flux:table.row :key="$warehouse->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                        @if ($warehouse->image)
                                            <img src="{{ asset('storage/' . $warehouse->image) }}" loading="lazy" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.building-storefront class="w-5 h-5" />
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $warehouse->name }}</span>
                                        <span class="text-xs text-zinc-500 font-mono">{{ $warehouse->code }}</span>
                                    </div>
                                </div>
                            </flux:table.cell>

                            <flux:table.cell class="max-w-xs truncate" title="{{ $warehouse->address }}">
                                {{ $warehouse->address ?? '-' }}
                            </flux:table.cell>

                            <flux:table.cell>
                                @php
                                    $categoryStocks = [];
                                    $totalStock = 0;
                                    foreach($warehouse->items as $item) {
                                        $stock = $item->pivot->stock ?? 0;
                                        if ($stock > 0) {
                                            $catName = $item->category ? $item->category->name : 'Tanpa Kategori';
                                            if (!isset($categoryStocks[$catName])) {
                                                $categoryStocks[$catName] = 0;
                                            }
                                            $categoryStocks[$catName] += $stock;
                                            $totalStock += $stock;
                                        }
                                    }
                                    arsort($categoryStocks);
                                @endphp

                                @if($totalStock > 0)
                                    <div class="flex flex-col gap-1">
                                        <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $totalStock }} Total Stok</div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($categoryStocks, 0, 2) as $cat => $qty)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
                                                    {{ $cat }}: {{ $qty }}
                                                </span>
                                            @endforeach
                                            @if(count($categoryStocks) > 2)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-zinc-50 text-zinc-500 dark:bg-zinc-800/50 dark:text-zinc-400">
                                                    +{{ count($categoryStocks) - 2 }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400 italic">Kosong</span>
                                @endif
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:button size="sm" x-on:click="$dispatch('open-warehouse-form', { id: {{ $warehouse->id }} })" icon="pencil-square" variant="ghost" class="text-zinc-500 hover:text-blue-600"></flux:button>
                                <flux:button size="sm" x-on:click="$dispatch('delete-warehouse', { id: {{ $warehouse->id }} })" icon="trash" variant="ghost" class="text-zinc-500 hover:text-red-600"></flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                <div class="flex flex-col items-center justify-center py-8 text-zinc-500">
                                    <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                                    <p>Belum ada data gudang.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @else
        {{-- Tampilan Grid (Compact Cards) --}}
        <div wire:key="view-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            @forelse ($this->getWarehouses() as $warehouse)
                <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden flex flex-col hover:border-blue-500/50 hover:shadow-lg transition-all group relative">
                    {{-- Banner / Header Image --}}
                    <div class="h-36 w-full bg-zinc-100 dark:bg-zinc-800 relative overflow-hidden border-b border-zinc-200/50 dark:border-zinc-700/50">
                        @if ($warehouse->image)
                            <img src="{{ asset('storage/' . $warehouse->image) }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-80"></div>
                        @else
                            <div class="w-full h-full flex items-center justify-center text-zinc-300 dark:text-zinc-600 bg-gradient-to-br from-zinc-50 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900">
                                <flux:icon.building-storefront class="w-12 h-12 opacity-50" />
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-50"></div>
                        @endif
                        
                        {{-- Code Badge floating on image --}}
                        <div class="absolute bottom-3 left-4">
                            <span class="px-2.5 py-1 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-sm shadow-sm rounded-md text-[10px] font-mono font-bold text-zinc-800 dark:text-zinc-200 border border-white/20 dark:border-zinc-700/50">
                                {{ $warehouse->code }}
                            </span>
                        </div>
                    </div>
                    
                    @php
                        $categoryStocks = [];
                        $totalStock = 0;
                        foreach($warehouse->items as $item) {
                            $stock = $item->pivot->stock ?? 0;
                            if ($stock > 0) {
                                $catName = $item->category ? $item->category->name : 'Tanpa Kategori';
                                if (!isset($categoryStocks[$catName])) {
                                    $categoryStocks[$catName] = 0;
                                }
                                $categoryStocks[$catName] += $stock;
                                $totalStock += $stock;
                            }
                        }
                        arsort($categoryStocks);
                    @endphp

                    {{-- Content --}}
                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-base line-clamp-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ $warehouse->name }}</h3>
                        
                        @if ($warehouse->address)
                            <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 flex items-start gap-1.5">
                                <flux:icon.map-pin class="w-3.5 h-3.5 shrink-0 mt-0.5 text-zinc-400 dark:text-zinc-500" />
                                <span class="leading-relaxed">{{ $warehouse->address }}</span>
                            </div>
                        @else
                            <div class="mt-2 text-xs text-zinc-400 italic">Belum ada alamat...</div>
                        @endif

                        {{-- Ringkasan Kategori --}}
                        <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/80">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-zinc-400 dark:text-zinc-500">Stok Kategori</span>
                                <span class="text-[10px] font-medium text-zinc-500">{{ $totalStock > 0 ? $totalStock . ' Total' : 'Kosong' }}</span>
                            </div>
                            @if(count($categoryStocks) > 0)
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(array_slice($categoryStocks, 0, 3) as $cat => $qty)
                                        <div class="px-2 py-0.5 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded text-[10px] text-zinc-600 dark:text-zinc-300 flex items-center gap-1">
                                            <span class="truncate max-w-[80px]">{{ $cat }}</span>
                                            <span class="font-bold">{{ $qty }}</span>
                                        </div>
                                    @endforeach
                                    @if(count($categoryStocks) > 3)
                                        <div class="px-2 py-0.5 bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700/50 rounded text-[10px] text-zinc-500 flex items-center">
                                            +{{ count($categoryStocks) - 3 }} lain
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-xs text-zinc-400 dark:text-zinc-600 flex items-center gap-1.5">
                                    <flux:icon.inbox class="w-3.5 h-3.5" />
                                    <span>Gudang ini masih kosong.</span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="mt-auto pt-5 flex justify-between items-end">
                            <div class="text-[10px] text-zinc-400 flex items-center gap-1 font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Aktif
                            </div>
                            <div class="flex gap-1 -mr-2 -mb-2">
                                <button x-on:click="$dispatch('open-warehouse-form', { id: {{ $warehouse->id }} })" class="p-2 text-zinc-400 hover:text-blue-600 transition-colors rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20" title="Edit">
                                    <flux:icon.pencil-square class="w-4 h-4" />
                                </button>
                                <button x-on:click="$dispatch('delete-warehouse', { id: {{ $warehouse->id }} })" class="p-2 text-zinc-400 hover:text-red-600 transition-colors rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20" title="Hapus">
                                    <flux:icon.trash class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 flex flex-col items-center justify-center text-zinc-500 bg-white dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                    <flux:icon.inbox class="w-12 h-12 mb-3 text-zinc-300" />
                    <p>Belum ada data gudang.</p>
                </div>
            @endforelse
        </div>
    @endif

    {{-- Load More --}}
    <x-load-more :paginator="$this->getWarehouses()" item-name="gudang" />

    {{-- Form Modal --}}
    <livewire:warehouse.warehouse-form />
</div>
