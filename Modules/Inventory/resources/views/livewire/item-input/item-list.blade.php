<?php

use function Livewire\Volt\{state, on, with, usesPagination, layout};
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;
use Modules\Inventory\Models\Type;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Warehouse;
use App\Models\User;
use Livewire\WithPagination;

layout('layouts.app');

usesPagination(theme: 'tailwind');

state([
    'search' => '',
    'viewMode' => 'grid', // 'table' or 'grid'
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 24,
    'filterType' => '',
    'filterCategory' => '',
    'filterSubCategory' => '',
    'filterStock' => 'all', // all, available, empty
    'filterStatus' => 'all', // active, inactive, all
    'filterWarehouse' => '',
    'filterCreator' => '',
    'filterOrdered' => false,
    'quickFilter' => 'all', // all, critical, wip, booked
]);

with(fn () => [
    'types' => Type::all(),
    'categories' => Category::all(),
    'warehouses' => Warehouse::all(),
    'users' => User::all(),
    'subCategories' => SubCategory::when($this->filterCategory, fn($q) => $q->where('category_id', $this->filterCategory))->get(),
]);

$loadMore = function () {
    $this->perPage += 24;
};

$updatedSearch = function () {
    $this->perPage = 24;
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
        'customVariants' => fn($q) => $q->with('salesOrder.customer')
            ->whereNotNull('custom_attachments')
            ->where('custom_attachments', '!=', '[]')
            ->where('custom_attachments', '!=', '')
            ->latest('id')
            ->limit(5)
    ])
        ->withCount('customVariants')
        ->when($this->search, function ($query) {
            $searchString = trim($this->search);
            if (empty($searchString)) return;

            $terms = array_filter(explode(' ', $searchString));
            
            foreach ($terms as $term) {
                $query->where(function($q) use ($term) {
                    $q->where('name', 'like', '%' . $term . '%')
                      ->orWhere('code', 'like', '%' . $term . '%')
                      ->orWhere('alias', 'like', '%' . $term . '%')
                      ->orWhere('tags', 'like', '%' . $term . '%')
                      ->orWhereHas('category', function($q2) use ($term) {
                          $q2->where('name', 'like', '%' . $term . '%');
                      })->orWhereHas('warehouses', function($q2) use ($term) {
                          $q2->where('name', 'like', '%' . $term . '%');
                      })->orWhereHas('type', function($q2) use ($term) {
                          $q2->where('name', 'like', '%' . $term . '%');
                      })->orWhereHas('unit', function($q2) use ($term) {
                          $q2->where('name', 'like', '%' . $term . '%');
                      })->orWhereHas('subCategory', function($q2) use ($term) {
                          $q2->where('name', 'like', '%' . $term . '%');
                      });
                });
            }
        })
        ->when($this->sortBy, function ($query) {
            $query->orderBy($this->sortBy, $this->sortDirection);
        })
        ->when($this->filterType, function ($query) {
            $query->where('type_id', $this->filterType);
        })
        ->when($this->filterCategory, function ($query) {
            $query->where('category_id', $this->filterCategory);
        })
        ->when($this->filterSubCategory, function ($query) {
            $query->where('sub_category_id', $this->filterSubCategory);
        })
        ->when($this->filterStock !== 'all', function ($query) {
            if ($this->filterStock === 'available') {
                $query->whereRaw('(SELECT COALESCE(SUM(stock), 0) FROM item_warehouse WHERE item_id = items.id) > 0');
            } else {
                $query->whereRaw('(SELECT COALESCE(SUM(stock), 0) FROM item_warehouse WHERE item_id = items.id) <= 0');
            }
        })
        ->when($this->filterStatus !== 'all', function ($query) {
            $query->where('is_active', $this->filterStatus === 'active');
        })
        ->when($this->filterWarehouse, function ($query) {
            $query->whereHas('warehouses', function ($q) {
                $q->where('warehouses.id', $this->filterWarehouse);
            });
        })
        ->when($this->filterCreator, function ($query) {
            $query->where('user_id', $this->filterCreator);
        })
        ->when($this->filterOrdered, function ($query) {
            $query->has('customVariants');
        })
        ->when($this->quickFilter === 'critical', function ($query) {
            $query->where('min_stock', '>', 0)
                  ->whereRaw('(SELECT COALESCE(SUM(stock), 0) FROM item_warehouse WHERE item_id = items.id) <= items.min_stock');
        })
        ->when($this->quickFilter === 'wip', function ($query) {
            $query->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('production_orders')
                  ->whereColumn('production_orders.item_id', 'items.id')
                  ->whereNotIn('production_orders.status', ['completed', 'archived', 'rejected']);
            });
        })
        ->when($this->quickFilter === 'booked', function ($query) {
            $query->whereExists(function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw(1))
                  ->from('sales_order_items')
                  ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
                  ->whereColumn('sales_order_items.item_id', 'items.id')
                  ->whereIn('sales_orders.status', ['approved', 'processing']);
            });
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
    <x-sticky-header class="flex flex-col mb-6 gap-4">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 w-full">
            <div class="hidden lg:block w-max">
                <flux:heading size="lg">Pengelolaan Barang</flux:heading>
                <flux:subheading>Daftar seluruh inventaris barang yang tersedia.</flux:subheading>
            </div>
            
            <div x-data="{ searchFocused: false }" class="flex items-center w-full lg:w-auto gap-2 sm:gap-3 flex-1 lg:flex-none">
                {{-- Search Bar --}}
                <div class="flex-1 w-full lg:w-64 transition-all duration-300 ease-out">
                    <flux:input 
                        x-on:focus="searchFocused = window.innerWidth < 1024" 
                        x-on:blur="searchFocused = false"
                        wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barang..." />
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center transition-all duration-300 ease-out origin-right overflow-hidden"
                     :class="searchFocused ? 'max-w-0 opacity-0 scale-95 !gap-0' : 'max-w-[400px] opacity-100 scale-100 gap-2 sm:gap-3'">
                    <flux:dropdown position="bottom" align="end">
                        <flux:button icon="adjustments-horizontal" class="px-2 shrink-0" />
                        <flux:menu class="!p-0 !border-0 !bg-transparent !shadow-none !w-[max-content] !min-w-0">
                            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 shadow-2xl shadow-zinc-900/20 dark:shadow-black/50 rounded-2xl w-[90vw] sm:w-[450px] md:w-[580px] flex flex-col overflow-hidden">
                                <div class="p-5 flex flex-col gap-4 max-h-[85vh] overflow-y-auto">
                                    {{-- Manager's Highlights (Smart Quick Filters) --}}
                                    <div class="bg-zinc-50 dark:bg-zinc-800/30 rounded-xl p-3 border border-zinc-200 dark:border-zinc-700">
                                        <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-2">Sorotan Pintar</div>
                                        <div class="flex flex-wrap gap-2">
                                            <button wire:click="$set('quickFilter', 'all')" class="px-3 py-1.5 text-xs font-semibold rounded-full transition-all border shadow-sm" :class="$wire.quickFilter === 'all' ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-800 dark:border-zinc-200' : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700 dark:hover:bg-zinc-700'">Semua</button>
                                            
                                            <button wire:click="$set('quickFilter', 'critical')" class="px-3 py-1.5 text-xs font-semibold rounded-full transition-all border shadow-sm" :class="$wire.quickFilter === 'critical' ? 'bg-rose-500 text-white border-rose-500' : 'bg-white text-rose-600 border-rose-100 hover:bg-rose-50 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/30 dark:hover:bg-rose-500/20'">🚨 Stok Kritis</button>
                                            
                                            <button wire:click="$set('quickFilter', 'wip')" class="px-3 py-1.5 text-xs font-semibold rounded-full transition-all border shadow-sm" :class="$wire.quickFilter === 'wip' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-amber-600 border-amber-100 hover:bg-amber-50 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30 dark:hover:bg-amber-500/20'">🔨 Sedang Diproduksi</button>
                                            
                                            <button wire:click="$set('quickFilter', 'booked')" class="px-3 py-1.5 text-xs font-semibold rounded-full transition-all border shadow-sm" :class="$wire.quickFilter === 'booked' ? 'bg-blue-500 text-white border-blue-500' : 'bg-white text-blue-600 border-blue-100 hover:bg-blue-50 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30 dark:hover:bg-blue-500/20'">🛒 Sedang Dipesan</button>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        {{-- Kategori & Tipe --}}
                                        <div class="space-y-3">
                                            <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-1 border-b border-zinc-100 dark:border-zinc-800 pb-1.5">Kategorisasi</div>
                                            <flux:select wire:model.live="filterType" placeholder="Semua Tipe">
                                                <flux:select.option value="">Semua Tipe</flux:select.option>
                                                @foreach($types as $type)
                                                    <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                            
                                            <flux:select wire:model.live="filterCategory" placeholder="Semua Kategori">
                                                <flux:select.option value="">Semua Kategori</flux:select.option>
                                                @foreach($categories as $category)
                                                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                            
                                            <flux:select wire:model.live="filterSubCategory" placeholder="Semua Sub Kategori" :disabled="!$filterCategory">
                                                <flux:select.option value="">Semua Sub Kategori</flux:select.option>
                                                @foreach($subCategories as $subCategory)
                                                    <flux:select.option value="{{ $subCategory->id }}">{{ $subCategory->name }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        
                                        <div class="flex flex-col gap-4">
                                            {{-- Lokasi & Penginput --}}
                                            <div class="space-y-3">
                                                <div class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider mb-1 border-b border-zinc-100 dark:border-zinc-800 pb-1.5">Penyimpanan & Sistem</div>
                                                
                                                <flux:select wire:model.live="filterWarehouse" placeholder="Semua Gudang">
                                                    <flux:select.option value="">Semua Gudang</flux:select.option>
                                                    @foreach($warehouses as $warehouse)
                                                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                                
                                                <flux:select wire:model.live="filterCreator" placeholder="Semua Penginput">
                                                    <flux:select.option value="">Semua Penginput</flux:select.option>
                                                    @foreach($users as $user)
                                                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </div>
                                            
                                            {{-- Radio Groups --}}
                                            <div class="grid grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800">
                                                <div>
                                                    <flux:radio.group wire:model.live="filterStock" label="Ketersediaan">
                                                        <div class="flex flex-col gap-2 mt-2">
                                                            <flux:radio value="all" label="Semua" />
                                                            <flux:radio value="available" label="Tersedia" />
                                                            <flux:radio value="empty" label="Habis" />
                                                        </div>
                                                    </flux:radio.group>
                                                </div>
                                                <div>
                                                    <flux:radio.group wire:model.live="filterStatus" label="Status Barang">
                                                        <div class="flex flex-col gap-2 mt-2">
                                                            <flux:radio value="active" label="Aktif" />
                                                            <flux:radio value="inactive" label="Non-Aktif" />
                                                            <flux:radio value="all" label="Semua" />
                                                        </div>
                                                    </flux:radio.group>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Footer Switch --}}
                                <div class="flex items-center justify-between border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30 p-4">
                                    <div>
                                        <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Pernah Dipesan/Dibeli</div>
                                    </div>
                                    <flux:switch wire:model.live="filterOrdered" />
                                </div>
                            </div>
                        </flux:menu>
                    </flux:dropdown>
                    
                    {{-- Switcher --}}
                    <x-grid-or-table wire:model="viewMode" :mode="$viewMode" />
                    
                    @can('inventory.item.create')
                        <flux:button x-on:click="$wire.dispatch('open-item-modal')" variant="primary" icon="plus" class="shrink-0">
                            <span class="hidden sm:inline">Barang</span>
                        </flux:button>
                    @endcan
                </div>
            </div>
        </div>
    </x-sticky-header>

    @php
        $galleryItems = [];
        foreach($this->getItems() as $item) {
            $specs = collect([
                $item->category?->name,
                $item->type?->name,
                $item->unit?->name
            ])->filter()->implode(' • ');
            
            $totalStock = $item->warehouses->sum('pivot.stock');
            $warehousesInfo = $item->warehouses->map(fn($w) => $w->name . ' (' . $w->pivot->stock . ')')->implode(' | ');
            
            $stats = $item->getInventoryStats();
            
            if ($item->image) {
                $galleryItems[] = [
                    'type' => 'main',
                    'id' => $item->id,
                    'image' => asset('storage/' . $item->image),
                    'alias' => $item->alias,
                    'name' => $item->name,
                    'code' => $item->code,
                    'specs' => $specs,
                    'description' => $item->description,
                    'price' => number_format($item->selling_price, 0, ',', '.'),
                    'purchase_price' => number_format($item->purchase_price, 0, ',', '.'),
                    'stock' => $totalStock . ' ' . ($item->unit?->name ?? ''),
                    'po' => $stats['purchase_order'] + $stats['purchase_queue'],
                    'wip' => $stats['production'],
                    'book' => $stats['sales_committed'],
                    'atp' => $item->getATP(),
                    'warehouses' => $warehousesInfo,
                    'is_active' => $item->is_active,
                ];
            }
            
            if (!empty($item->customVariants)) {
                $groupedVariants = collect($item->customVariants)
                    ->filter(fn($v) => !empty($v->custom_attachments))
                    ->groupBy(fn($v) => $v->custom_attachments[0]);
                
                foreach($groupedVariants as $imagePath => $group) {
                    $variant = $group->first();
                    $attrs = !empty($variant->custom_attributes) ? collect($variant->custom_attributes)->map(function($v, $k) {
                        $valStr = is_array($v) ? implode(': ', $v) : $v;
                        return is_numeric($k) ? $valStr : $k . ': ' . $valStr;
                    })->implode(' | ') : '';
                    
                    $galleryItems[] = [
                        'type' => 'variant',
                        'id' => $item->id,
                        'image' => asset('storage/' . $imagePath),
                        'alias' => $item->alias ? $item->alias . ' (Varian)' : 'Varian ' . $item->name,
                        'name' => $variant->salesOrder?->customer?->name ? 'Pesanan: ' . $variant->salesOrder->customer->name : 'Varian',
                        'code' => $item->code,
                        'specs' => $attrs ? $attrs : 'Varian Custom',
                        'description' => 'Tgl Pesanan: ' . ($variant->created_at ? $variant->created_at->format('d M Y') : '-'),
                        'price' => null,
                        'purchase_price' => null,
                        'stock' => null,
                        'po' => null,
                        'wip' => null,
                        'book' => null,
                        'atp' => null,
                        'warehouses' => null,
                        'is_active' => null,
                    ];
                }
            }
        }
    @endphp

    @if ($viewMode === 'table')
        {{-- Tampilan Tabel --}}
        <x-table.wrapper>
    <flux:table class="table-mobile-cards">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Info Barang</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'purchase_price'" :direction="$sortDirection" wire:click="sort('purchase_price')">Harga</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'min_stock'" :direction="$sortDirection" wire:click="sort('min_stock')">Stok</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'is_active'" :direction="$sortDirection" wire:click="sort('is_active')">Status</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->getItems() as $item)
                        <flux:table.row :key="$item->id" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-800/50 transition-colors" x-on:dblclick="$wire.dispatch('open-item-detail', { id: {{ $item->id }} })">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div class="relative w-10 h-10 rounded-md bg-zinc-100 border border-zinc-200 shrink-0">
                                        @if ($item->image)
                                            <img src="{{ asset('storage/' . $item->image) }}" loading="lazy" class="w-full h-full object-cover rounded-md">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400 rounded-md">
                                                <flux:icon.photo class="w-5 h-5" />
                                            </div>
                                        @endif
                                        
                                        @php
                                            $uniqueVariants = collect();
                                            if ($item->custom_variants_count > 0) {
                                                $uniqueVariants = collect($item->customVariants)->unique(function($v) {
                                                    return json_encode($v->custom_attributes) . json_encode($v->custom_attachments);
                                                });
                                            }
                                        @endphp
                                        
                                        @if ($uniqueVariants->count() > 0)
                                            <div x-data="{ showTooltip: false }" 
                                                 class="absolute -top-2.5 -left-2.5 z-10 flex items-center cursor-pointer hover:scale-105 transition-transform"
                                                 @mouseenter="showTooltip = true"
                                                 @mouseleave="showTooltip = false"
                                                 @click.stop="showTooltip = !showTooltip">
                                                
                                                <div class="flex -space-x-1.5 overflow-visible items-center drop-shadow-sm">
                                                    @foreach($uniqueVariants->take(2) as $variant)
                                                        @if(!empty($variant->custom_attachments))
                                                            <img class="inline-block h-5 w-5 rounded-full ring-2 ring-white object-cover bg-white" 
                                                                 src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" 
                                                                 alt="Varian">
                                                        @endif
                                                    @endforeach
                                                    <span class="inline-flex items-center justify-center h-4.5 px-1.5 text-[9px] font-bold text-white bg-indigo-600 rounded-full ring-2 ring-white z-10 whitespace-nowrap">
                                                        {{ $uniqueVariants->count() }} Varian
                                                    </span>
                                                </div>
                                                
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
                                                            Varian Spesifikasi
                                                        </div>
                                                        <div class="text-[10px] bg-indigo-800/50 px-1.5 py-0.5 rounded">{{ $uniqueVariants->count() }} Unik</div>
                                                    </div>
                                                    
                                                    <div class="p-2 flex flex-col gap-2 max-h-48 overflow-y-auto custom-scrollbar">
                                                        @foreach($uniqueVariants as $variant)
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
                                                                    <div class="text-[9px] text-zinc-600 dark:text-zinc-400 leading-tight">
                                                                        @if(!empty($variant->custom_attributes))
                                                                            @foreach($variant->custom_attributes as $key => $val)
                                                                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ is_numeric($key) ? '' : $key.':' }}</span> {{ is_array($val) ? implode(', ', $val) : $val }}@if(!$loop->last), @endif
                                                                            @endforeach
                                                                        @else
                                                                            Varian custom
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
        </x-table.wrapper>
    @else
        {{-- Tampilan Grid (Vertical Cards dengan Gambar Mencolok) --}}
        <div class="@container w-full">
            <div class="grid grid-cols-2 @md:grid-cols-3 @2xl:grid-cols-4 @4xl:grid-cols-5 @6xl:grid-cols-7 gap-4 mb-6">
            @forelse ($this->getItems() as $item)
                <div wire:key="item-{{ $item->id }}" 
                     x-data="{ loading: false, activeVariant: null }"
                     x-on:dblclick="loading = true; $wire.dispatch('open-item-detail', { id: {{ $item->id }} })"
                     x-on:item-detail-modal-opened.window="loading = false"
                     @click.outside="activeVariant = null"
                     class="group relative isolate z-0 flex flex-col bg-white dark:bg-zinc-800 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 border border-zinc-200/80 dark:border-zinc-700 hover:border-blue-500/30 dark:hover:border-blue-400/30 cursor-pointer hover:scale-[1.02] {{ !$item->is_active ? 'opacity-80 grayscale-[0.4]' : '' }}">
                    
                    {{-- Loading Overlay --}}
                    <div x-show="loading" x-cloak class="absolute inset-0 bg-white/50 dark:bg-zinc-900/50 z-50 flex flex-col items-center justify-center backdrop-blur-sm rounded-xl">
                        <flux:icon.arrow-path class="w-8 h-8 animate-spin text-blue-500 mb-2" />
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">Membuka Detail...</span>
                    </div>

                    @if (!$item->is_approved)
                    <div class="absolute z-40 top-0 w-full h-full bg-[#000000ba] flex flex-col items-center justify-center">
                        <span class="text-bold text-amber-500 font-bold tracking-widest uppercase">MENUNGGU</span>
                        <span class="text-white text-xs mt-1">PERSETUJUAN</span>
                    </div>
                    @endif
                    
                    @php
                        // Group by the first image attachment to avoid identical avatars
                        $groupedVariants = collect($item->customVariants)
                            ->filter(fn($v) => !empty($v->custom_attachments))
                            ->groupBy(fn($v) => $v->custom_attachments[0])
                            ->take(4);
                    @endphp
                    
                    {{-- Gambar Atas (Mencolok) --}}
                    <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-900/50 overflow-hidden border-b border-zinc-100 dark:border-zinc-700">
                        @if (!$item->is_active)
                            <div class="absolute inset-0 z-20 bg-zinc-900/20 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
                                <div class="bg-rose-600/90 text-white px-3 py-1 rounded shadow-md font-bold text-xs tracking-widest border border-rose-500/50 backdrop-blur-md">NON-AKTIF</div>
                            </div>
                        @endif
                        @if ($item->image)
                            <img x-data="{ loaded: false }" 
                                 x-init="if ($el.complete) loaded = true"
                                 @load="loaded = true" 
                                 :class="loaded ? 'opacity-100' : 'opacity-0 scale-95'"
                                 :src="activeVariant && activeVariant.image ? activeVariant.image : '{{ asset('storage/' . $item->image) }}'" 
                                 @click.stop="$dispatch('open-item-gallery', { image: activeVariant && activeVariant.image ? activeVariant.image : '{{ asset('storage/' . $item->image) }}' })"
                                 loading="lazy" 
                                 decoding="async"
                                 class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110 cursor-zoom-in">
                        @else
                            <img x-show="activeVariant && activeVariant.image" x-cloak
                                 :src="activeVariant ? activeVariant.image : ''"
                                 @click.stop="$dispatch('open-item-gallery', { image: activeVariant ? activeVariant.image : '' })"
                                 class="absolute inset-0 w-full h-full object-cover z-10 transition-all duration-700 cursor-zoom-in">
                                 
                            <div class="w-full h-full flex flex-col items-center justify-center text-zinc-300">
                                <flux:icon.photo class="w-10 h-10 mb-1 opacity-50" />
                            </div>
                            <div class="absolute inset-0" style="background-image: radial-gradient(circle, #e4e4e7 1px, transparent 1px); background-size: 10px 10px; opacity: 0.5;"></div>
                        @endif
                        
                        {{-- Watermark Label (Kiri Atas) --}}
                        @if ($item->requires_label)
                            <div class="absolute top-2 left-2 z-10 pointer-events-none opacity-70 border border-white/50 dark:border-zinc-700/50 rounded p-1 backdrop-blur-sm shadow-sm" title="Berlabel SN">
                                <flux:icon.qr-code class="w-4 h-4 text-white drop-shadow-md" />
                            </div>
                        @endif
                        
                        {{-- Thumbnail Slider (Bertumpuk Vertikal di Kanan Atas Gambar) --}}
                        @php
                            $groupedVariantsForSlider = $groupedVariants->take(5);
                        @endphp
                        @if($item->image || $groupedVariants->isNotEmpty())
                            <div class="absolute right-1.5 md:right-2 top-1.5 md:top-2 bottom-1.5 md:bottom-2 flex flex-col gap-1 md:gap-2 overflow-y-auto custom-scrollbar snap-y z-10 items-end" style="scrollbar-width: none;">
                                {{-- Thumbnail Variasi --}}
                                @foreach($groupedVariantsForSlider as $imagePath => $group)
                                    @php
                                        $variant = $group->first();
                                        $customer = $variant->salesOrder?->customer?->name;
                                        
                                        // Format attributes nicely, ignoring numeric keys
                                        $attrs = !empty($variant->custom_attributes) ? collect($variant->custom_attributes)->map(function($v, $k) {
                                            $valStr = is_array($v) ? implode(': ', $v) : $v;
                                            return is_numeric($k) ? $valStr : $k . ': ' . $valStr;
                                        })->implode(' | ') : '';
                                        
                                        $desc = [];
                                        if ($customer) $desc[] = 'Pesanan: ' . $customer;
                                        if ($attrs) $desc[] = $attrs;
                                        
                                        $variantData = [
                                            'image' => asset('storage/' . $imagePath),
                                            'description' => empty($desc) ? 'Varian tanpa detail' : implode(' • ', $desc)
                                        ];
                                    @endphp
                                    <div @click="activeVariant = {{ json_encode($variantData) }}; $el.parentElement.scrollTo({ top: $el.offsetTop - $el.parentElement.clientHeight / 2 + $el.clientHeight / 2, behavior: 'smooth' })"
                                         class="relative w-7 h-7 xl:w-8 xl:h-8 rounded md:rounded-lg border-2 shrink-0 overflow-hidden cursor-pointer snap-start transition-all bg-white/40 dark:bg-zinc-900/40 shadow-md backdrop-blur-md opacity-60 hover:opacity-100"
                                         :class="activeVariant && activeVariant.image === '{{ $variantData['image'] }}' ? 'border-indigo-500' : 'border-white/80 dark:border-zinc-600/80 hover:border-white dark:hover:border-zinc-500'">
                                        <img src="{{ $variantData['image'] }}" class="w-full h-full object-cover">
                                    </div>
                                @endforeach
                                
                                    {{-- Info Lebih Banyak dihapus sesuai permintaan --}}
                                </div>
                            @endif
                    </div>
                    
                    {{-- Informasi Bawah (Jelas & Padat) --}}
                    <div class="p-3 flex flex-col flex-1 relative">
                        {{-- Badge Tipe Barang (Straddling Kiri Bawah) --}}
                        <div class="absolute left-0 top-0 -translate-y-1/2 flex flex-row gap-1.5 z-10 pointer-events-none">
                            @if ($item->type)
                                @php
                                    $colors = [
                                        'bahan baku utama' => 'bg-amber-600',
                                        'bahan baku penolong' => 'bg-amber-500',
                                        'produk jadi' => 'bg-emerald-600',
                                        'barang setengah jadi' => 'bg-sky-500',
                                        'jasa' => 'bg-purple-500',
                                        'aset' => 'bg-slate-600',
                                        'custom' => 'bg-rose-500',
                                    ];
                                    $typeName = strtolower($item->type->name);
                                    $defaultColors = ['bg-indigo-500', 'bg-rose-500', 'bg-cyan-500', 'bg-teal-500', 'bg-fuchsia-500'];
                                    $color = $colors[$typeName] ?? $defaultColors[$item->type->id % count($defaultColors)];
                                @endphp
                                <div class="w-max {{ $color }} text-white rounded-r-md px-2 py-0.5 text-[8px] sm:text-[9px] font-black tracking-wider uppercase shadow-sm">
                                    {{ $item->type->name }}
                                </div>
                            @endif
                        </div>
                        
                        {{-- Judul, Meta & Deskripsi --}}
                        <div class="mb-2 flex-1 overflow-hidden flex flex-col pt-0.5">
                            {{-- Nama Alias --}}
                            <h2 class="font-semibold text-zinc-800 dark:text-zinc-200 text-base lg:text-lg leading-tight truncate transition-colors"
                            title="{{ $item->name }}">
                            @if($item->alias)
                                <span class="font-bold text-lg lg:text-xl">{{ $item->alias }}</span>
                            @endif
                        </h2>
                            {{-- Nama Barang --}}
                            <h5 class="font-semibold text-zinc-800 dark:text-zinc-200 text-[8px] md:text-[10px] lg:text-[12px] leading-tight line-clamp-2 md:truncate transition-colors" 
                                title="{{ $item->name }}">
                                {{ $item->name }}
                            </h5>
                            
                            {{-- Kode & Kategori (Baris Super Rapat) --}}
                            <div class="flex items-center gap-1 mt-0.5 overflow-hidden">
                                <span class="text-[6px] sm:text-[9px] font-mono font-medium text-zinc-400 dark:text-zinc-500 shrink-0">
                                    {{ $item->code }}
                                </span>
                                      
                                <span class="text-zinc-300 dark:text-zinc-600 text-[6px] sm:text-[7px] shrink-0">&bull;</span>
                                
                                <div class="flex items-center gap-1 overflow-hidden">
                                    <span class="text-[5px] sm:text-[6px] font-bold uppercase tracking-widest truncate text-zinc-400 dark:text-zinc-500">
                                        {{ $item->category?->name ?? 'Tanpa Kategori' }}
                                    </span>
                                    
                                    @if($item->subCategory)
                                        <span class="text-zinc-300 dark:text-zinc-600 text-[6px] sm:text-[7px] shrink-0">&bull;</span>
                                        <span class="text-[5px] sm:text-[6px] font-semibold text-zinc-400/80 uppercase tracking-wider truncate">
                                            {{ $item->subCategory->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        

                        
                        {{-- Harga & Stok --}}
                        <div class="mt-auto flex items-end justify-between pt-2 border-t border-zinc-100/80 dark:border-zinc-700">
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
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[8px] font-medium text-zinc-400 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-900/50 px-1.5 py-0.5 rounded border border-zinc-100 dark:border-zinc-700">
                            {!! implode(' <span class="text-zinc-300 dark:text-zinc-600">&bull;</span> ', $badges) !!}
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
    {{-- Modals dipindahkan ke index.blade.php untuk mencegah masalah render saat list di-filter --}}
    <!-- Gallery Lightbox Modal -->
    <div x-data="itemGallery( {{ json_encode($galleryItems ?? []) }} )" 
         x-show="isOpen" 
         @open-item-gallery.window="open($event.detail.image)"
         @keydown.escape.window="close()"
         @keydown.arrow-left.window="prev()"
         @keydown.arrow-right.window="next()"
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 backdrop-blur-md"
         style="display: none;"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <!-- Close Button -->
        <button @click="close()" class="absolute top-4 right-4 z-[110] text-white/70 hover:text-white p-2 rounded-full bg-black/20 hover:bg-black/40 transition">
            <flux:icon.x-mark class="w-6 h-6" />
        </button>

        <!-- Navigation Arrows -->
        <button x-show="gallery.length > 1" @click.stop="prev()" class="absolute left-2 sm:left-6 top-1/2 -translate-y-1/2 z-[110] text-white/70 hover:text-white p-3 rounded-full bg-black/20 hover:bg-black/50 transition">
            <flux:icon.chevron-left class="w-8 h-8" />
        </button>
        <button x-show="gallery.length > 1" @click.stop="next()" class="absolute right-2 sm:right-6 top-1/2 -translate-y-1/2 z-[110] text-white/70 hover:text-white p-3 rounded-full bg-black/20 hover:bg-black/50 transition">
            <flux:icon.chevron-right class="w-8 h-8" />
        </button>

        <!-- Main Content -->
        <div class="relative w-full h-full flex flex-col items-center justify-center overflow-hidden"
             @touchstart="touchStart($event)"
             @touchmove.passive="touchMove($event)"
             @touchend="touchEnd($event)"
             @mousedown="touchStart($event)"
             @mousemove="touchMove($event)"
             @mouseup="touchEnd($event)"
             @mouseleave="touchEnd($event)"
             @click="if($event.target === $el) close()">
             
            <!-- Slider Container -->
            <div class="flex w-full h-full"
                 :class="isSwiping ? 'transition-none' : 'transition-transform duration-300 ease-out'"
                 :style="`transform: translateX(calc(-${currentIndex * 100}% + ${touchOffset}px))`">
                 
                <template x-for="(item, index) in gallery" :key="index">
                    <div class="w-full h-full shrink-0 flex flex-col md:flex-row max-w-7xl mx-auto items-center p-2 sm:p-4 md:p-8 gap-2 sm:gap-4 md:gap-8 pointer-events-none pb-4 md:pb-8">
                        <!-- Image Container -->
                        <div class="flex-1 w-full h-[55vh] md:h-full flex items-center justify-center relative pointer-events-auto"
                             style="touch-action: pan-y pinch-zoom;">
                            <img :src="item.image" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl pointer-events-none select-none" draggable="false">
                            <div x-show="item.type === 'variant'" class="absolute top-4 left-4 bg-indigo-600/90 text-white px-3 py-1 rounded-full text-xs font-bold shadow-lg backdrop-blur-sm">
                                VARIAN
                            </div>
                        </div>

                        <!-- Info Panel -->
                        <div class="w-full md:w-96 shrink-0 bg-zinc-900/95 backdrop-blur-xl border-t md:border border-zinc-700/50 md:rounded-2xl p-3 md:p-6 shadow-2xl text-left flex flex-col pointer-events-auto max-h-[45vh] md:max-h-full z-10"
                             @touchstart.stop
                             @touchmove.stop
                             @mousedown.stop
                             @mousemove.stop>
                            <div class="overflow-y-auto custom-scrollbar flex-1 pr-1 md:pr-2 pb-16 md:pb-4">
                                <!-- Header: Code & Status -->
                                <div class="flex items-center justify-between mb-0.5 md:mb-2">
                                    <div class="text-[9px] md:text-xs font-mono text-zinc-400" x-text="item.code"></div>
                                    <template x-if="item.type === 'main'">
                                        <div class="text-[7px] md:text-[9px] font-bold px-1 py-0.5 rounded uppercase" 
                                             :class="item.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'" 
                                             x-text="item.is_active ? 'Aktif' : 'Non-Aktif'"></div>
                                    </template>
                                </div>
                                
                                <!-- Title -->
                                <h2 class="text-base md:text-2xl font-bold text-white leading-tight truncate" x-text="item.alias || item.name"></h2>
                                <h3 x-show="item.alias" class="text-[9px] md:text-sm font-medium text-zinc-300 mb-2 truncate" x-text="item.name"></h3>
                                <h3 x-show="!item.alias" class="text-[9px] md:text-sm font-medium text-zinc-300 mb-2 truncate">&nbsp;</h3>
                                
                                <!-- Tabular Data Grid List -->
                                <div class="flex flex-col gap-1 md:gap-3 mb-2 md:mb-4">
                                    
                                    <!-- Harga (Beli & Jual Sejajar) -->
                                    <template x-if="item.type === 'main'">
                                        <div class="flex items-center justify-between text-[10px] md:text-sm border-b border-white/5 pb-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase">Beli</span>
                                                <span class="font-bold text-zinc-300">Rp<span x-text="item.purchase_price"></span></span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase">Jual</span>
                                                <span class="font-bold text-emerald-400">Rp<span x-text="item.price"></span></span>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- Stok & Pipeline (Sejajar) -->
                                    <template x-if="item.type === 'main'">
                                        <div class="flex flex-wrap items-center justify-between text-[10px] md:text-sm border-b border-white/5 pb-0.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase">Stok</span>
                                                <span class="font-bold text-sky-400" x-text="item.stock"></span>
                                            </div>
                                            <div class="flex items-center gap-1 md:gap-2 text-[9px] md:text-xs">
                                                <template x-if="item.po > 0"><span class="text-zinc-400 bg-zinc-800 px-1 rounded">PO:<span x-text="item.po"></span></span></template>
                                                <template x-if="item.wip > 0"><span class="text-zinc-400 bg-zinc-800 px-1 rounded">WIP:<span x-text="item.wip"></span></span></template>
                                                <template x-if="item.book > 0"><span class="text-zinc-400 bg-zinc-800 px-1 rounded">Book:<span x-text="item.book"></span></span></template>
                                                <span class="font-bold" :class="item.atp > 0 ? 'text-emerald-400' : 'text-rose-400'">ATP: <span x-text="item.atp"></span></span>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- Spesifikasi -->
                                    <div class="flex text-[9px] md:text-sm text-zinc-200 leading-tight">
                                        <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase w-16 md:w-24 shrink-0" x-text="item.type === 'variant' ? 'Pesanan' : 'Spek'"></span>
                                        <span class="flex-1" x-text="item.specs || '-'"></span>
                                    </div>
                                    
                                    <!-- Gudang -->
                                    <template x-if="item.warehouses">
                                        <div class="flex text-[9px] md:text-sm text-zinc-400 leading-tight">
                                            <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase w-16 md:w-24 shrink-0">Gudang</span>
                                            <span class="flex-1 text-[8.5px] md:text-xs" x-text="item.warehouses"></span>
                                        </div>
                                    </template>

                                    <!-- Deskripsi -->
                                    <template x-if="item.description">
                                        <div class="flex flex-col text-[9px] md:text-xs text-zinc-300 leading-tight">
                                            <span class="text-[8px] md:text-[10px] text-zinc-500 font-bold uppercase mb-0.5">Deskripsi</span>
                                            <span class="whitespace-pre-line" x-text="item.description"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Footer navigasi -->
                            <div class="mt-1 md:mt-4 pt-1 md:pt-4 border-t border-white/10 flex items-center justify-between text-[8px] md:text-xs text-zinc-500 shrink-0">
                                <span x-text="(index + 1) + ' dari ' + gallery.length"></span>
                                <span>Geser foto untuk navigasi</span>
                            </div>
                        </div>
                    </div>
                </template>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('itemGallery', (items) => ({
            isOpen: false,
            gallery: items || [],
            currentIndex: 0,
            touchStartX: 0,
            touchCurrentX: 0,
            isSwiping: false,
            
            get touchOffset() {
                return this.isSwiping ? (this.touchCurrentX - this.touchStartX) : 0;
            },
            
            get currentItem() {
                return this.gallery[this.currentIndex] || null;
            },

            open(imageUrl) {
                if (this.gallery.length === 0) return;
                const index = this.gallery.findIndex(item => item.image === imageUrl);
                this.currentIndex = index !== -1 ? index : 0;
                this.touchStartX = 0;
                this.touchCurrentX = 0;
                this.isSwiping = false;
                this.isOpen = true;
                document.body.style.overflow = 'hidden';
            },

            close() {
                this.isOpen = false;
                document.body.style.overflow = '';
            },

            next() {
                if (this.currentIndex < this.gallery.length - 1) {
                    this.currentIndex++;
                } else {
                    this.currentIndex = 0;
                }
            },

            prev() {
                if (this.currentIndex > 0) {
                    this.currentIndex--;
                } else {
                    this.currentIndex = this.gallery.length - 1;
                }
            },

            touchStart(e) {
                // Ignore multiple touches
                if (e.touches && e.touches.length > 1) return;
                this.touchStartX = e.touches ? e.touches[0].clientX : e.clientX;
                this.touchCurrentX = this.touchStartX;
                this.isSwiping = true;
            },
            
            touchMove(e) {
                if (!this.isSwiping) return;
                this.touchCurrentX = e.touches ? e.touches[0].clientX : e.clientX;
            },

            touchEnd(e) {
                if (!this.isSwiping) return;
                this.isSwiping = false;
                let diff = this.touchCurrentX - this.touchStartX;
                
                if (diff < -50) {
                    this.next();
                } else if (diff > 50) {
                    this.prev();
                }
                
                this.touchStartX = 0;
                this.touchCurrentX = 0;
            }
        }));
    });
    </script>
</div>
</div>
