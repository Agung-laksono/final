<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\SubCategory;
use Modules\Inventory\Models\Type;
use Livewire\Attributes\On;

new class extends Component {
    use WithPagination;

    public $searchQuery = '';
    public $categoryId = '';
    public $subCategoryId = '';
    public $typeId = '';
    public $stockFilter = ''; // '' (semua), 'tersedia', 'habis'
    public $statusFilter = 'aktif'; // 'aktif', 'non-aktif', '' (semua)
    public $historyFilter = false;

    public $perPage = 12;
    public $context = 'inventory';

    public function updated($property)
    {
        if (in_array($property, ['searchQuery', 'categoryId', 'subCategoryId', 'typeId', 'stockFilter', 'statusFilter', 'historyFilter'])) {
            $this->resetPage();
        }
        
        if ($property === 'categoryId') {
            $this->subCategoryId = '';
        }
    }
    
    public function loadMore()
    {
        $this->perPage += 12;
    }
    
    #[On('item-saved')]
    #[On('echo:inventory,InventoryUpdated')]
    public function refreshGallery()
    {
        $this->resetPage();
    }

    #[On('open-gallery')]
    public function handleOpenGallery($context = 'inventory')
    {
        $this->context = $context;
        
        $type = null;
        if ($context === 'purchase') {
            $type = Type::where('name', 'like', '%bahan baku utama%')->first();
        } elseif ($context === 'sales') {
            $type = Type::where('name', 'like', '%produk jadi%')->first();
        }
        
        $this->typeId = $type ? $type->id : '';
        $this->resetPage();
    }

    public function with()
    {
        $query = Item::with(['category', 'subCategory', 'type', 'unit', 'warehouses']);

        if (strlen($this->searchQuery) >= 2) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('code', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if ($this->subCategoryId) {
            $query->where('sub_category_id', $this->subCategoryId);
        }
        if ($this->typeId) {
            $query->where('type_id', $this->typeId);
        }

        if ($this->statusFilter === 'aktif') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'non-aktif') {
            $query->where('is_active', false);
        }

        if ($this->stockFilter === 'tersedia') {
            $query->whereHas('warehouses', function($q) {
                $q->where('stock', '>', 0);
            });
        } elseif ($this->stockFilter === 'habis') {
            $query->whereDoesntHave('warehouses', function($q) {
                $q->where('stock', '>', 0);
            });
        }

        if ($this->historyFilter) {
            $query->whereIn('id', function($q) {
                $q->select('item_id')
                  ->from('purchase_order_items')
                  ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                  ->where('purchase_orders.status', '!=', 'draft');
            });
        }

        $galleryItems = $query->latest()->paginate($this->perPage);
        
        $itemIds = $galleryItems->pluck('id')->toArray();
        $itemsWithHistory = \Modules\Purchase\Models\PurchaseOrderItem::whereIn('item_id', $itemIds)
            ->whereHas('purchaseOrder', function($q) {
                $q->where('status', '!=', 'draft');
            })->pluck('item_id')->unique()->toArray();

        $subCategories = $this->categoryId ? SubCategory::where('category_id', $this->categoryId)->orderBy('name')->get() : [];

        return [
            'galleryItems' => $galleryItems,
            'itemsWithHistory' => $itemsWithHistory,
            'categories' => Category::orderBy('name')->get(),
            'subCategories' => $subCategories,
            'types' => Type::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <flux:modal name="gallery-modal" class="md:max-w-4xl">
        <div class="space-y-4">
            <div class="hidden sm:flex items-start">
                <div>
                    <flux:heading size="lg">Galeri Barang</flux:heading>
                    <flux:subheading>Cari dan pilih barang untuk dimasukkan ke Purchase Order.</flux:subheading>
                </div>
            </div>
            
            {{-- Search & Filter Bar (Sticky) --}}
            <div x-data="{ searchActive: false, showHeader: true, lastScrollY: 0 }"
                 x-init="
                     document.addEventListener('scroll', (e) => {
                         if (e.target.contains && e.target.contains($el)) {
                             let currentScroll = e.target.scrollTop;
                             if (currentScroll !== undefined) {
                                 if (Math.abs(currentScroll - lastScrollY) > 10) {
                                     showHeader = currentScroll < lastScrollY || currentScroll < 50;
                                     lastScrollY = currentScroll;
                                 }
                             }
                         }
                     }, true);
                 "
                 class="sticky -top-6 z-50 bg-white dark:bg-zinc-900 pb-2 pt-1 -mx-2 px-2 transition-all duration-300"
                 :class="showHeader ? 'translate-y-0 opacity-100' : '-translate-y-[120%] opacity-0 pointer-events-none'">
                <div class="flex flex-row items-center gap-2 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700 transition-all duration-300">
                    <div class="flex-1 min-w-0" @focusin="searchActive = true" @focusout="setTimeout(() => searchActive = false, 200)">
                        <flux:input 
                            wire:model.live.debounce.300ms="searchQuery" 
                            icon="magnifying-glass" 
                            placeholder="Cari nama atau kode barang..." 
                            class="w-full transition-all duration-300" />
                    </div>
                        
                    <div class="flex items-center shrink-0 overflow-hidden transition-all duration-300 origin-right"
                         :class="searchActive ? 'max-w-0 opacity-0 gap-0' : 'max-w-[300px] opacity-100 gap-1'">
                        <flux:dropdown>
                            <flux:button variant="subtle" icon="adjustments-horizontal" class="shrink-0 px-2 md:px-3">
                                <span class="hidden md:inline ml-1">Filter</span>
                            </flux:button>
                            <flux:menu class="w-72 space-y-4 p-4">
                                <div>
                                    <flux:heading size="sm" class="mb-2">Filter Lanjutan</flux:heading>
                                </div>
                                
                                <flux:select wire:model.live="typeId" placeholder="Semua Tipe Barang">
                                    <flux:select.option value="">Semua Tipe Barang</flux:select.option>
                                    @foreach($types as $type)
                                        <flux:select.option value="{{ $type->id }}">{{ $type->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.live="categoryId" placeholder="Semua Kategori">
                                    <flux:select.option value="">Semua Kategori</flux:select.option>
                                    @foreach($categories as $category)
                                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>

                                <flux:select wire:model.live="subCategoryId" placeholder="Semua Sub Kategori" :disabled="!$categoryId">
                                    <flux:select.option value="">Semua Sub Kategori</flux:select.option>
                                    @foreach($subCategories as $subCat)
                                        <flux:select.option value="{{ $subCat->id }}">{{ $subCat->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                
                                <div class="space-y-1">
                                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Ketersediaan Stok</div>
                                    <flux:radio.group wire:model.live="stockFilter" class="flex gap-4">
                                        <flux:radio value="" label="Semua" />
                                        <flux:radio value="tersedia" label="Tersedia" />
                                        <flux:radio value="habis" label="Habis" />
                                    </flux:radio.group>
                                </div>

                                <div class="space-y-1">
                                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Status Barang</div>
                                    <flux:radio.group wire:model.live="statusFilter" class="flex gap-4">
                                        <flux:radio value="aktif" label="Aktif" />
                                        <flux:radio value="non-aktif" label="Non-Aktif" />
                                        <flux:radio value="" label="Semua" />
                                    </flux:radio.group>
                                </div>

                                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                    <flux:switch wire:model.live="historyFilter" label="Pernah Dipesan/Dibeli" />
                                </div>
                            </flux:menu>
                        </flux:dropdown>

                        @can('inventory.item.create')
                        <flux:button x-on:click="$dispatch('open-item-modal')" variant="primary" icon="plus" class="shrink-0 px-2 md:px-4">
                            <span class="hidden md:inline ml-1">Barang Baru</span>
                        </flux:button>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Loading State Indicator --}}
            <div wire:loading wire:target="searchQuery" class="w-full text-center py-4">
                <span class="text-sm text-zinc-500 flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 animate-spin" /> Mencari barang...
                </span>
            </div>

            <div wire:loading.remove wire:target="searchQuery" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-2">
                @forelse($galleryItems as $item)
                    <div @if($item->is_active) @click="playSelectSound(); $dispatch('item-selected', { item: { item_id: {{ $item->id }}, name: '{{ addslashes($item->name) }}', code: '{{ $item->code ?? '0001' }}', unit_price: {{ $context === 'sales' ? ($item->selling_price ?? 0) : ($item->purchase_price ?? 0) }}, image: '{{ $item->image }}', has_history: {{ in_array($item->id, $itemsWithHistory) ? 'true' : 'false' }} } })" @endif
                         :class="$data.items?.find(i => i.item_id == {{ $item->id }}) ? 'border-cyan-600 ring-2 ring-cyan-600 shadow-lg scale-[1.02]' : 'border-zinc-200 dark:border-zinc-800 {{ $item->is_active ? 'hover:border-cyan-500/50 hover:shadow-lg hover:scale-[1.02]' : '' }}'"
                         class="relative bg-white dark:bg-zinc-900 rounded-xl overflow-hidden transition-all duration-300 {{ $item->is_active ? 'cursor-pointer group' : 'cursor-not-allowed' }} flex flex-col h-full border">
                        
                        {{-- NON ACTIVE Overlay --}}
                        @if (!$item->is_active)
                        <div class="absolute z-20 top-0 w-full h-full bg-black/60 backdrop-blur-[2px] flex items-center justify-center pointer-events-none">
                            <div class="bg-rose-500 text-white px-3 py-1 rounded shadow-lg transform -rotate-12 font-black tracking-widest border-2 border-white text-xs">NON ACTIVE</div>
                        </div>
                        @endif

                        {{-- Selection Badge --}}
                        <template x-if="$data.items?.find(i => i.item_id == {{ $item->id }})">
                            <div class="absolute top-2 right-2 bg-cyan-600 text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-md flex items-center gap-1 shadow-md z-30 pointer-events-none border border-cyan-400/50">
                                <flux:icon.check-circle class="w-3 h-3" />
                                <span x-text="$data.items.find(i => i.item_id == {{ $item->id }}).qty + 'x'"></span>
                            </div>
                        </template>

                        {{-- Gambar Atas (Mencolok) --}}
                        <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 overflow-hidden border-b border-zinc-100 dark:border-zinc-800/50">
                            @if ($item->image)
                                <img x-data="{ loaded: false }" 
                                     x-on:load="loaded = true" 
                                     :class="loaded ? 'opacity-100' : 'opacity-0 scale-95'"
                                     src="{{ Storage::url($item->image) }}" 
                                     loading="lazy" 
                                     decoding="async"
                                     class="w-full h-full object-cover transition-all duration-700 group-hover:scale-110">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-zinc-300">
                                    <flux:icon.photo class="w-10 h-10 mb-1 opacity-50" />
                                </div>
                                <div class="absolute inset-0" style="background-image: radial-gradient(circle, #e4e4e7 1px, transparent 1px); background-size: 10px 10px; opacity: 0.5;"></div>
                            @endif
                            
                            {{-- Badge Tipe Barang (Kiri Atas) --}}
                            <div class="absolute top-2 left-2 flex z-10 pointer-events-none">
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
                                    <div class="{{ $color }} backdrop-blur-sm text-white rounded-md px-1.5 py-0.5 text-[7px] font-bold tracking-wider uppercase shadow-sm border border-white/20">
                                        {{ $item->type->name }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Informasi Bawah (Jelas & Padat) --}}
                        <div class="p-2.5 flex flex-col flex-1">
                            {{-- Kategori & Kode --}}
                            <div class="flex justify-between items-start mb-1.5 gap-2">
                                <div class="flex flex-col overflow-hidden">
                                    <span class="text-[7px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest truncate">{{ $item->category?->name ?? 'Tanpa Kategori' }}</span>
                                    @if($item->subCategory)
                                        <span class="text-[6px] font-semibold text-zinc-400/80 uppercase tracking-wider truncate">{{ $item->subCategory->name }}</span>
                                    @endif
                                </div>
                                <span class="text-[8px] font-mono font-medium text-zinc-400 dark:text-zinc-500 shrink-0 mt-0.5">{{ $item->code }}</span>
                            </div>
                            
                            {{-- Nama Barang & Deskripsi --}}
                            <div class="mb-2 flex-1 overflow-hidden">
                                <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 text-[11px] leading-tight line-clamp-2 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors" title="{{ $item->name }}">
                                    {{ $item->name }}
                                </h3>
                                @if($item->description)
                                    <p class="text-[9px] text-zinc-500 dark:text-zinc-400 mt-0.5 leading-tight truncate" title="{{ $item->description }}">{{ $item->description }}</p>
                                @endif
                            </div>
                            
                            {{-- Harga & Stok --}}
                            <div class="mt-auto flex items-end justify-between pt-1.5 border-t border-zinc-100/80 dark:border-zinc-800/50">
                                <div class="flex flex-col gap-0.5">
                                    @if($context === 'sales')
                                        <div class="flex items-center" title="Harga Jual">
                                            <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 leading-none">Rp{{ number_format($item->selling_price ?? 0, 0, ',', '.') }}</span>
                                        </div>
                                    @elseif($context === 'purchase')
                                        <div class="flex items-center" title="Harga Beli">
                                            <span class="text-[11px] font-bold text-zinc-600 dark:text-zinc-300 leading-none">Rp{{ number_format($item->purchase_price ?? 0, 0, ',', '.') }}</span>
                                        </div>
                                    @else
                                        <div class="flex items-center" title="Harga Beli">
                                            <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 leading-none">Rp{{ number_format($item->purchase_price ?? 0, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex items-center" title="Harga Jual">
                                            <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 leading-none">Rp{{ number_format($item->selling_price ?? 0, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-baseline gap-0.5 text-right pb-0.5">
                                    <span class="font-bold text-zinc-700 dark:text-zinc-300 text-[13px] leading-none">{{ $item->warehouses->sum('pivot.stock') }}</span>
                                    <span class="text-[8px] font-medium text-zinc-400 dark:text-zinc-500">{{ $item->unit?->name ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center flex flex-col items-center justify-center">
                        <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                        <span class="text-zinc-500">Tidak ada barang yang cocok dengan pencarian Anda.</span>
                    </div>
                @endforelse
            </div>

            {{-- Pagination (Load More) --}}
            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <x-load-more :paginator="$galleryItems" item-name="barang" />
            </div>
        </div>
    </flux:modal>

    @script
    <script>
        if (typeof window.playSelectSound === 'undefined') {
            // Gunakan satu AudioContext secara global agar tidak kena limit browser (max 6 context)
            let sharedAudioCtx = null;

            window.playSelectSound = function(type = 'ting') {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return;
                    
                    if (!sharedAudioCtx) {
                        sharedAudioCtx = new AudioContext();
                    }

                    // Browser kadang me-suspend audio sampai ada interaksi user
                    if (sharedAudioCtx.state === 'suspended') {
                        sharedAudioCtx.resume();
                    }
                    
                    const ctx = sharedAudioCtx;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const now = ctx.currentTime;
                    
                    if (type === 'pop') {
                        // Suara Gelembung Air / UI Pop (Paling Responsif)
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(800, now);
                        osc.frequency.exponentialRampToValueAtTime(300, now + 0.05);
                        gain.gain.setValueAtTime(0, now);
                        gain.gain.linearRampToValueAtTime(0.5, now + 0.01);
                        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.05);
                        osc.start(now);
                        osc.stop(now + 0.05);
                        
                    } else if (type === 'kasir') {
                        // Suara Beep Scanner Kasir
                        osc.type = 'square';
                        osc.frequency.setValueAtTime(1500, now);
                        gain.gain.setValueAtTime(0, now);
                        gain.gain.setValueAtTime(0.1, now + 0.01);
                        gain.gain.setValueAtTime(0, now + 0.08);
                        osc.start(now);
                        osc.stop(now + 0.1);
                        
                    } else if (type === 'ting') {
                        // Suara Lonceng Lembut
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(1200, now);
                        gain.gain.setValueAtTime(0, now);
                        gain.gain.linearRampToValueAtTime(0.4, now + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
                        osc.start(now);
                        osc.stop(now + 0.4);
                        
                    } else if (type === 'click') {
                        // Suara Ketukan Mekanik
                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(100, now);
                        gain.gain.setValueAtTime(0, now);
                        gain.gain.linearRampToValueAtTime(0.5, now + 0.01);
                        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.02);
                        osc.start(now);
                        osc.stop(now + 0.03);
                    }

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                } catch (e) {
                    console.error('AudioContext error', e);
                }
            }
        }
    </script>
    @endscript
</div>
