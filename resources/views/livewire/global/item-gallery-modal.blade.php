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
    public $statusFilter = ''; // 'aktif', 'non-aktif', '' (semua)
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
        $query = Item::with([
            'category', 'subCategory', 'type', 'unit', 'warehouses',
            'customVariants' => fn($q) => $q->with('salesOrder.customer')
                ->whereNotNull('custom_attachments')
                ->where('custom_attachments', '!=', '[]')
                ->where('custom_attachments', '!=', '')
                ->latest('id')
                ->limit(5)
        ])->withCount('customVariants');

        if (strlen($this->searchQuery) >= 2) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('code', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('alias', 'like', '%' . $this->searchQuery . '%');
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

<div x-data="{ 
    currentItem: null, 
    variantsList: [],
    activeVariants: {},
    init() {
        window.addEventListener('item-removed-from-cart', (e) => {
            if (e.detail && e.detail.item_id) {
                this.activeVariants[e.detail.item_id] = null;
            }
        });
        window.addEventListener('cart-cleared', () => {
            this.activeVariants = {};
        });
    },
    selectStandard() {
        playSelectSound();
        $dispatch('item-selected', { item: this.currentItem });
        this.activeVariants[this.currentItem.item_id] = null;
        $flux.modal('select-variant-modal').close();
    },
    selectVariant(v) {
        playSelectSound();
        $dispatch('item-selected', { item: {
            item_id: this.currentItem.item_id,
            name: this.currentItem.name,
            code: this.currentItem.code,
            unit_price: v.unit_price || this.currentItem.unit_price,
            image: v.image_raw || this.currentItem.image,
            unit: this.currentItem.unit,
            has_history: true,
            custom_attributes: v.custom_attributes || [],
            custom_attachments: v.custom_attachments || [],
            note: v.note ? v.note + '<br><strong>Salinan Pesanan: ' + v.customer + '</strong>' : '<strong>Salinan Pesanan: ' + v.customer + '</strong>'
        } });
        this.activeVariants[this.currentItem.item_id] = v;
        $flux.modal('select-variant-modal').close();
    }
}">
    <flux:modal name="gallery-modal" class="md:max-w-4xl max-sm:!p-2 max-sm:!m-0 max-sm:w-full max-sm:h-full max-sm:max-w-none max-sm:max-h-[100dvh] max-sm:rounded-none">
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
                        <div x-data="{ loading: false }" x-on:item-form-ready.window="loading = false">
                            <flux:button x-on:click="loading = true; $dispatch('open-item-modal')" x-bind:disabled="loading" variant="primary" class="shrink-0 px-2 md:px-4">
                                <span x-show="!loading" class="flex items-center">
                                    <flux:icon.plus class="w-4 h-4" />
                                    <span class="hidden md:inline ml-1">Barang Baru</span>
                                </span>
                                <span x-show="loading" class="flex items-center" style="display: none;">
                                    <svg class="animate-spin w-4 h-4 mr-1 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="hidden md:inline">Memuat...</span>
                                </span>
                            </flux:button>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>

            <div wire:loading wire:target="searchQuery, categoryId, typeId, subCategoryId, stockFilter, statusFilter, historyFilter" class="col-span-full py-12">
                <div class="flex flex-col items-center justify-center text-zinc-400">
                    <flux:icon.arrow-path class="w-8 h-8 animate-spin mb-4" />
                    <span class="text-sm">Memuat galeri...</span>
                </div>
            </div>

            <div wire:loading.remove wire:target="searchQuery" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-2">
                @forelse($galleryItems as $item)
                    @php
                        // Group by the first image attachment to avoid identical avatars
                        $groupedVariants = collect($item->customVariants)
                            ->filter(fn($v) => !empty($v->custom_attachments))
                            ->groupBy(fn($v) => $v->custom_attachments[0])
                            ->take(4);
                    @endphp
                    <div wire:key="gallery-item-{{ $item->id }}"
                          @click="
                            if (! {{ $item->is_active ? 'true' : 'false' }}) return;
                            playSelectSound();
                            const activeV = activeVariants[{{ $item->id }}];
                            const isAlreadyInCart = $data.items?.some(i => i.item_id == {{ $item->id }});
                            const hasVariants = {{ $item->customVariants->isNotEmpty() ? 'true' : 'false' }};
                            if (activeV) {
                                // Add selected active variant immediately
                                $dispatch('item-selected', { item: {
                                    item_id: {{ $item->id }},
                                    name: @js($item->name),
                                    code: @js($item->code ?? '0001'),
                                    unit_price: activeV.unit_price || {{ $context === 'sales' ? ($item->selling_price ?? 0) : ($item->purchase_price ?? 0) }},
                                    image: activeV.image_raw || @js($item->image),
                                    unit: @js($item->unit?->name ?? 'pcs'),
                                    has_history: true,
                                    custom_attributes: activeV.custom_attributes || [],
                                    custom_attachments: activeV.custom_attachments || [],
                                    note: activeV.note ? activeV.note + '<br><strong>Salinan Pesanan: ' + activeV.customer + '</strong>' : '<strong>Salinan Pesanan: ' + activeV.customer + '</strong>'
                                } });
                            } else if (hasVariants && !isAlreadyInCart) {
                                currentItem = {
                                    item_id: {{ $item->id }},
                                    name: @js($item->name),
                                    code: @js($item->code ?? '0001'),
                                    unit_price: {{ $context === 'sales' ? ($item->selling_price ?? 0) : ($item->purchase_price ?? 0) }},
                                    image: @js($item->image),
                                    unit: @js($item->unit?->name ?? 'pcs'),
                                    has_history: true
                                };
                                variantsList = @js(collect($item->customVariants)->filter(fn($v) => !empty($v->custom_attachments))->map(function($variant) {
                                    return [
                                        'image' => asset('storage/' . $variant->custom_attachments[0]),
                                        'image_raw' => $variant->custom_attachments[0],
                                        'customer' => $variant->salesOrder?->customer?->name ?? 'Pelanggan Umum',
                                        'date' => $variant->created_at?->format('d M Y') ?? '',
                                        'custom_attributes' => $variant->custom_attributes ?? [],
                                        'custom_attachments' => $variant->custom_attachments ?? [],
                                        'note' => $variant->note ?? '',
                                        'unit_price' => (float) $variant->unit_price,
                                        'attributes' => !empty($variant->custom_attributes) ? collect($variant->custom_attributes)->map(fn($v, $k) => is_numeric($k) ? (is_array($v) ? implode(': ', $v) : $v) : $k . ': ' . (is_array($v) ? implode(': ', $v) : $v))->implode(' | ') : ''
                                    ];
                                })->values()->take(10)->toArray());
                                $flux.modal('select-variant-modal').show();
                            } else {
                                $dispatch('item-selected', { item: { item_id: {{ $item->id }}, name: @js($item->name), code: @js($item->code ?? '0001'), unit_price: {{ $context === 'sales' ? ($item->selling_price ?? 0) : ($item->purchase_price ?? 0) }}, image: @js($item->image), unit: @js($item->unit?->name ?? 'pcs'), has_history: {{ in_array($item->id, $itemsWithHistory) ? 'true' : 'false' }}, custom_attributes: [], custom_attachments: [], note: '' } });
                            }
                          "
                         :class="$data.items?.find(i => i.item_id == {{ $item->id }}) ? 'border-cyan-600 ring-2 ring-cyan-600 shadow-lg scale-[1.02]' : 'border-zinc-200 dark:border-zinc-800 {{ $item->is_active ? 'hover:border-cyan-500/50 hover:shadow-lg hover:scale-[1.02]' : '' }}'"
                         class="relative bg-white dark:bg-zinc-900 rounded-xl overflow-hidden transition-all duration-300 {{ $item->is_active ? 'cursor-pointer group' : 'cursor-not-allowed' }} flex flex-col h-full border">
                        
                        {{-- MENUNGGU PERSETUJUAN Overlay --}}
                        @if (!$item->is_approved)
                        <div class="absolute z-40 top-0 w-full h-full bg-[#000000ba] flex flex-col items-center justify-center">
                            <span class="text-bold text-amber-500 font-bold tracking-widest uppercase">MENUNGGU</span>
                            <span class="text-white text-xs mt-1">PERSETUJUAN</span>
                        </div>
                        @endif
                        {{-- Selection Badge (Elegan & Bersih) --}}
                        <template x-if="$data.items?.find(i => i.item_id == {{ $item->id }})">
                            <div class="absolute inset-0 z-40 pointer-events-none flex items-center justify-center bg-transparent transition-all">
                                {{-- Tombol Batal Seleksi (Kanan Atas) --}}
                                <button type="button" 
                                        @click.stop="
                                            playSelectSound('click');
                                            const idx = $data.items.findIndex(i => i.item_id == {{ $item->id }});
                                            if (idx !== -1) {
                                                $data.removeItem(idx);
                                                activeVariants[{{ $item->id }}] = null;
                                            }
                                        "
                                        class="absolute top-2 right-2 z-50 pointer-events-auto bg-black/35 hover:bg-rose-600 dark:bg-zinc-800/40 dark:hover:bg-rose-600 text-white rounded-full p-1 shadow border border-white/20 backdrop-blur-sm active:scale-95 transition-all focus:outline-none"
                                        title="Batalkan Pilihan">
                                    <flux:icon.x-mark class="w-3 h-3" stroke-width="3" />
                                </button>

                                {{-- Badge Checkmark di Tengah --}}
                                <div class="bg-cyan-600 text-white font-bold rounded-full flex items-center justify-center shadow-lg border border-white/40 dark:border-zinc-700/50 transition-all"
                                     x-bind:class="($data.items?.find(i => i.item_id == {{ $item->id }})?.qty || 1) > 1 ? 'px-3 py-1 gap-1.5 text-xs' : 'w-8 h-8'">
                                    <flux:icon.check-circle class="w-4 h-4" />
                                    <span x-show="($data.items?.find(i => i.item_id == {{ $item->id }})?.qty || 1) > 1" 
                                          x-text="($data.items?.find(i => i.item_id == {{ $item->id }})?.qty || 1) + 'x'"></span>
                                </div>
                            </div>
                        </template>
                        
                        {{-- Gambar Atas (Mencolok) --}}
                        <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-900/50 overflow-hidden border-b border-zinc-100 dark:border-zinc-700">
                            {{-- Image Swap System --}}
                            <template x-if="activeVariants[{{ $item->id }}] && activeVariants[{{ $item->id }}].image">
                                <img :src="activeVariants[{{ $item->id }}].image" class="absolute inset-0 w-full h-full object-cover z-10 transition-all duration-300">
                            </template>

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
                            
                            {{-- Watermark Label (Kiri Atas) --}}
                            @if ($item->requires_label)
                                <div class="absolute top-2 left-2 z-10 pointer-events-none opacity-70 border border-white/50 dark:border-zinc-700/50 rounded p-1 backdrop-blur-sm shadow-sm" title="Berlabel SN">
                                    <flux:icon.qr-code class="w-4 h-4 text-white drop-shadow-md" />
                                </div>
                            @endif

                             {{-- Thumbnail Slider (Bertumpuk Vertikal di Kanan Atas Gambar) --}}
                             @if($groupedVariants->isNotEmpty())
                                 <div class="absolute right-1.5 md:right-2 top-1.5 md:top-2 bottom-1.5 md:bottom-2 flex flex-col gap-1 md:gap-2 overflow-y-auto custom-scrollbar snap-y z-30 items-end pointer-events-auto variant-slider" style="scrollbar-width: none;">
                                     {{-- Thumbnail Variasi --}}
                                     @foreach($groupedVariants as $imagePath => $group)
                                         @php
                                             $variant = $group->first();
                                             $customer = $variant->salesOrder?->customer?->name;
                                             
                                             $attrs = !empty($variant->custom_attributes) ? collect($variant->custom_attributes)->map(function($v, $k) {
                                                 $valStr = is_array($v) ? implode(': ', $v) : $v;
                                                 return is_numeric($k) ? $valStr : $k . ': ' . $valStr;
                                             })->implode(' | ') : '';
                                             
                                             $desc = [];
                                             if ($customer) $desc[] = 'Pesanan: ' . $customer;
                                             if ($attrs) $desc[] = $attrs;
                                             
                                             $variantData = [
                                                 'image' => asset('storage/' . $imagePath),
                                                 'image_raw' => $imagePath,
                                                 'description' => empty($desc) ? 'Varian tanpa detail' : implode(' • ', $desc),
                                                 'customer' => $customer ?? 'Pelanggan Umum',
                                                 'date' => $variant->created_at?->format('d M Y') ?? '',
                                                 'attributes' => $attrs,
                                                 'custom_attributes' => $variant->custom_attributes ?? [],
                                                 'custom_attachments' => $variant->custom_attachments ?? [],
                                                 'note' => $variant->note ?? '',
                                                 'unit_price' => (float) $variant->unit_price
                                             ];
                                         @endphp
                                         <div @click.stop="activeVariants[{{ $item->id }}] = @js($variantData); $el.parentElement.scrollTo({ top: $el.offsetTop - $el.parentElement.clientHeight / 2 + $el.clientHeight / 2, behavior: 'smooth' })"
                                              class="relative w-7 h-7 xl:w-8 xl:h-8 rounded md:rounded-lg border-2 shrink-0 overflow-hidden cursor-pointer snap-start transition-all bg-white/40 dark:bg-zinc-900/40 shadow-md backdrop-blur-md opacity-60 hover:opacity-100"
                                              :class="activeVariants[{{ $item->id }}] && activeVariants[{{ $item->id }}].image === '{{ $variantData['image'] }}' ? 'border-indigo-500' : 'border-white/80 dark:border-zinc-600/80 hover:border-white dark:hover:border-zinc-500'">
                                             <img src="{{ $variantData['image'] }}" class="w-full h-full object-cover">
                                         </div>
                                     @endforeach
                                 </div>
                             @endif
                        </div>
                         <div class="p-3 flex flex-col flex-1 relative">
                             {{-- Badge Tipe Barang & Varian Kustom (Straddling Kiri & Kanan Bawah Gambar) --}}
                             <div class="absolute left-0 right-0 top-0 -translate-y-1/2 flex justify-between items-center z-10 pointer-events-none px-2.5">
                                 <div>
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
                                         <div class="w-max {{ $color }} text-white rounded-md px-1.5 py-0.5 text-[8px] font-bold tracking-wider uppercase shadow-sm border border-white/70 dark:border-zinc-800/80 backdrop-blur-sm">
                                             {{ $item->type->name }}
                                         </div>
                                     @endif
                                 </div>
                                 <div x-show="activeVariants[{{ $item->id }}]" x-cloak>
                                     <div class="w-max bg-indigo-600/90 text-white rounded-md px-1.5 py-0.5 text-[8px] font-bold tracking-wider uppercase shadow-sm border border-white/70 dark:border-zinc-800/80 backdrop-blur-sm"
                                          x-text="activeVariants[{{ $item->id }}] ? activeVariants[{{ $item->id }}].customer : ''">
                                     </div>
                                 </div>
                             </div>
                             
                             {{-- Judul, Meta & Deskripsi --}}
                             <div class="mb-2 flex-1 overflow-hidden flex flex-col pt-0.5">
                                 {{-- Nama Alias --}}
                                 <h2 class="font-semibold text-zinc-800 dark:text-zinc-200 text-base lg:text-lg leading-tight truncate transition-colors"
                                     title="{{ $item->alias ?: $item->name }}">
                                     @if($item->alias)
                                         <span class="font-bold text-lg lg:text-xl">{{ $item->alias }}</span>
                                     @else
                                         <span class="font-bold text-lg lg:text-xl">{{ $item->name }}</span>
                                     @endif
                                 </h2>
                                 {{-- Nama Barang --}}
                                 @if($item->alias)
                                     <h5 class="font-semibold text-zinc-800 dark:text-zinc-200 text-[8px] md:text-[9px] lg:text-[10px] leading-tight truncate transition-colors" 
                                         title="{{ $item->name }}">
                                         {{ $item->name }}
                                     </h5>
                                 @endif
                                 
                                 {{-- Keterangan Varian Kustom Aktif --}}
                                 <div x-show="activeVariants[{{ $item->id }}]" x-cloak class="mt-1">
                                     <span class="inline-block px-1.5 py-0.5 text-[8px] font-semibold bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 rounded border border-indigo-100 dark:border-indigo-800 truncate max-w-full" 
                                           x-text="activeVariants[{{ $item->id }}] ? (activeVariants[{{ $item->id }}].attributes || activeVariants[{{ $item->id }}].note || 'Varian Kustom') : ''"></span>
                                 </div>
                                 
                                 {{-- Kode & Kategori (Baris Super Rapat) --}}
                                 <div class="flex items-center gap-1 mt-1 overflow-hidden">
                                    <span class="text-[6px] sm:text-[8px] font-mono font-medium shrink-0 transition-colors text-zinc-400 dark:text-zinc-500">
                                          {{ $item->code }}
                                    </span>
                                          
                                    <span class="text-zinc-300 dark:text-zinc-600 text-[5px] sm:text-[6px] shrink-0">&bull;</span>
                                    
                                    <div class="flex items-center gap-0.5 overflow-hidden">
                                        <span class="text-[5px] sm:text-[6px] font-bold uppercase tracking-widest truncate text-zinc-400 dark:text-zinc-500"
                                              x-text="activeVariants[{{ $item->id }}] ? 'Riwayat Varian' : '{{ addslashes($item->category?->name ?? 'Tanpa Kategori') }}'">
                                        </span>
                                        
                                        @if($item->subCategory)
                                            <span class="text-zinc-300 dark:text-zinc-600 text-[5px] sm:text-[6px] shrink-0" x-show="!activeVariants[{{ $item->id }}]">&bull;</span>
                                            <span class="text-[5px] sm:text-[6px] font-semibold text-zinc-400/80 uppercase tracking-wider truncate" 
                                                  x-show="!activeVariants[{{ $item->id }}]">
                                                {{ $item->subCategory->name }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
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

        {{-- Floating Back Button (Mobile) --}}
        <div class="sm:hidden sticky bottom-4 left-4 z-[100] w-max -mt-2">
            <button @click="$flux.modal('gallery-modal').close()" class="w-14 h-14 bg-white dark:bg-zinc-800 rounded-full flex items-center justify-center shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 active:scale-95 transition-all focus:outline-none">
                <flux:icon.arrow-left stroke-width="2.5" class="w-6 h-6" />
            </button>
        </div>
        {{-- Popup Pilihan Varian Kustom (Menggunakan Native Flux Modal agar Konsisten dan Bersih) --}}
        <flux:modal name="select-variant-modal" class="md:max-w-2xl max-sm:!p-2">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Pilih Varian Barang</flux:heading>
                    <flux:subheading>Barang ini memiliki beberapa riwayat kustomisasi.</flux:subheading>
                </div>
                
                {{-- List Pilihan (Grid Vertical Cards) --}}
                <div class="grid grid-cols-2 gap-4 max-h-[65vh] overflow-y-auto custom-scrollbar pr-1 p-1">
                    {{-- Opsi 1: Barang Standar --}}
                    <div @click="selectStandard()"
                         class="relative bg-white dark:bg-zinc-900 border-2 border-cyan-100 dark:border-cyan-900/40 rounded-xl overflow-hidden hover:border-cyan-500 hover:shadow-md cursor-pointer transition-all duration-300 flex flex-col h-full">
                        
                        {{-- Badge Standar Kanan Atas --}}
                        <div class="absolute right-2 top-2 z-10">
                            <span class="inline-block px-1.5 py-0.5 text-[8px] font-bold bg-cyan-600/90 text-white rounded border border-white/20 backdrop-blur-sm shadow-sm">✨ STANDAR</span>
                        </div>

                        {{-- Gambar Atas --}}
                        <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-900/50 overflow-hidden border-b border-cyan-100 dark:border-cyan-900/40">
                            <template x-if="currentItem && currentItem.image">
                                <img :src="'/storage/' + currentItem.image" class="w-full h-full object-cover">
                            </template>
                            <template x-if="currentItem && !currentItem.image">
                                <div class="w-full h-full flex items-center justify-center text-zinc-300 dark:text-zinc-700">
                                    <flux:icon.photo class="w-10 h-10" />
                                </div>
                            </template>
                        </div>
                        
                        {{-- Informasi Bawah --}}
                        <div class="p-3 flex flex-col flex-1">
                            <div class="mb-2 flex-1">
                                <h4 class="font-bold text-zinc-800 dark:text-zinc-200 text-xs sm:text-sm leading-tight flex items-center gap-1.5 flex-wrap">
                                    <span>Barang Standar</span>
                                    <span class="px-1 py-0.2 rounded bg-cyan-50 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400 text-[7px] font-black uppercase tracking-wider">ORIGINAL</span>
                                </h4>
                                <div class="text-[9px] text-zinc-400 mt-1 font-mono" x-text="currentItem ? currentItem.code : ''"></div>
                            </div>
                            <div class="mt-auto flex items-end justify-between pt-1.5 border-t border-cyan-50 dark:border-zinc-800/50">
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300" x-text="currentItem ? 'Rp' + formatRupiah(currentItem.unit_price) : ''"></span>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Opsi Kustom --}}
                    <template x-for="v in variantsList">
                        <div @click="selectVariant(v)"
                             class="relative bg-white dark:bg-zinc-900 border-2 border-indigo-100 dark:border-indigo-900/40 rounded-xl overflow-hidden hover:border-indigo-500 hover:shadow-md cursor-pointer transition-all duration-300 flex flex-col h-full">
                            
                            {{-- Badge Pemesan --}}
                            <div class="absolute right-2 top-2 z-10">
                                <span class="inline-block px-1.5 py-0.5 text-[8px] font-bold bg-indigo-600/90 text-white rounded border border-white/20 backdrop-blur-sm shadow-sm" x-text="v.customer"></span>
                            </div>

                            {{-- Gambar Atas --}}
                            <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-900/50 overflow-hidden border-b border-indigo-100 dark:border-indigo-900/40">
                                <img :src="v.image" class="w-full h-full object-cover">
                            </div>
                            
                            {{-- Informasi Bawah --}}
                            <div class="p-3 flex flex-col flex-1">
                                <div class="mb-2 flex-1">
                                    <h4 class="font-bold text-zinc-850 dark:text-zinc-150 text-xs sm:text-sm leading-tight flex items-center gap-1.5 flex-wrap">
                                        <span>Custom Order</span>
                                        <span class="px-1 py-0.2 rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 text-[7px] font-black uppercase">CUSTOM</span>
                                    </h4>
                                    <div class="text-[9px] text-zinc-500 mt-1 line-clamp-2" x-text="v.attributes ? v.attributes : (v.note ? v.note : 'Tanpa detail atribut')"></div>
                                </div>
                                <div class="mt-auto flex items-end justify-between pt-1.5 border-t border-indigo-50 dark:border-zinc-800/50">
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400" x-text="'Rp' + formatRupiah(v.unit_price)"></span>
                                    <span class="text-[8px] text-zinc-400 font-mono" x-text="v.date"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Footer Batal --}}
                <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="ghost" size="sm" @click="$flux.modal('select-variant-modal').close()">Batal</flux:button>
                </div>
            </div>
        </flux:modal>
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
