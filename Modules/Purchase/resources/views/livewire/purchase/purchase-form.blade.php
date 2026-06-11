<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount, updated};
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderItem;
use Modules\Purchase\Models\Vendor;
use Modules\Inventory\Models\Item;
use Illuminate\Support\Str;

layout('layouts.app');
title('Form Purchase Order');

state([
    'order_id' => null,
    'po_number' => '',
    'vendor_id' => '',
    'order_date' => date('Y-m-d'),
    'ongkir' => 0,
    'diskon_global' => 0,
    'pajak_persen' => 0,
    'pajak_nominal' => 0,
    'status' => 'draft',
    
    'items' => [], // array of ['id' => null, 'item_id' => id, 'name' => name, 'qty' => 1, 'unit_price' => price, 'subtotal' => price]
    
    'search_query' => '',
    'show_suggestions' => false,

    'vendor_search_query' => '',
    'show_vendor_suggestions' => false,
    'selected_vendor' => null,
    
    'price_history' => [],
    'history_item_name' => '',
    'history_item_id' => null,
    'history_limit' => 5,
    'has_more_history' => false,
    
    'detail_po' => null,
    
    'note_item_index' => null,
    'current_note' => '',
]);

mount(function ($id = null) {
    if ($id) {
        $po = PurchaseOrder::with(['items.item', 'vendor'])->findOrFail($id);
        $this->order_id = $po->id;
        $this->po_number = $po->po_number;
        $this->vendor_id = $po->vendor_id;
        $this->order_date = $po->order_date;
        $this->ongkir = $po->ongkir ?? 0;
        $this->diskon_global = $po->diskon_global ?? 0;
        $this->pajak_nominal = $po->pajak ?? 0;
        $this->status = $po->status;
        
        if ($po->vendor) {
            $this->selected_vendor = $po->vendor->toArray();
        }
        
        // Coba hitung pajak_persen dari pajak_nominal jika ada
        // Ini estimasi, karena kita tidak menyimpan persentase secara eksplisit
        $sub = $po->items->sum('subtotal') + $this->ongkir - $this->diskon_global;
        if ($sub > 0 && $this->pajak_nominal > 0) {
            $this->pajak_persen = round(($this->pajak_nominal / $sub) * 100);
        }

        foreach ($po->items as $detail) {
            $hasHistory = \Modules\Purchase\Models\PurchaseOrderItem::where('item_id', $detail->item_id)
                ->whereHas('purchaseOrder', function($q) {
                    $q->where('status', '!=', 'draft');
                })->exists();

            $this->items[] = [
                'id' => $detail->id,
                'item_id' => $detail->item_id,
                'name' => $detail->item->name ?? 'Unknown',
                'qty' => $detail->quantity,
                'unit_price' => $detail->unit_price,
                'subtotal' => $detail->subtotal,
                'image' => $detail->item->image ?? null,
                'note' => $detail->notes ?? '', // Hydrate notes from DB
                'has_history' => $hasHistory,
            ];
        }
    } else {
        $this->po_number = ''; // Will be generated on save
    }
});

// Computed search results remain in Livewire

$vendorSearchResults = computed(function () {
    if (strlen($this->vendor_search_query) < 2) return [];
    return Vendor::where('name', 'like', '%' . $this->vendor_search_query . '%')
               ->orWhere('phone', 'like', '%' . $this->vendor_search_query . '%')
               ->take(5)->get();
});

$selectVendor = function ($vendorId) {
    $vendor = Vendor::find($vendorId);
    if ($vendor) {
        $this->selected_vendor = $vendor->toArray();
        $this->vendor_id = $vendor->id;
    }
    $this->vendor_search_query = '';
    $this->show_vendor_suggestions = false;
};



$clearVendor = function () {
    $this->selected_vendor = null;
    $this->vendor_id = '';
};

$searchResults = computed(function () {
    if (strlen($this->search_query) < 2) return [];
    return Item::where('name', 'like', '%' . $this->search_query . '%')
               ->orWhere('code', 'like', '%' . $this->search_query . '%')
               ->take(5)->get();
});

// Fungsi addItem dihapus dari Livewire karena sekarang ditangani sepenuhnya oleh AlpineJS di sisi klien.

$showPriceHistory = function ($itemId, $itemName) {
    $this->history_item_name = $itemName;
    $this->history_item_id = $itemId;
    $this->history_limit = 5;
    
    $this->loadHistory();
};

$loadMoreHistory = function () {
    $this->history_limit += 5;
    $this->loadHistory();
};

$loadHistory = function () {
    $baseQuery = \Modules\Purchase\Models\PurchaseOrderItem::where('item_id', $this->history_item_id)
        ->whereHas('purchaseOrder', function($q) {
            $q->where('status', '!=', 'draft');
        });

    $this->has_more_history = $baseQuery->count() > $this->history_limit;

    $this->price_history = \Modules\Purchase\Models\PurchaseOrderItem::with(['purchaseOrder.vendor', 'item.unit'])
        ->where('item_id', $this->history_item_id)
        ->whereHas('purchaseOrder', function($q) {
            $q->where('status', '!=', 'draft');
        })
        ->get()
        ->sortByDesc(fn($poi) => $poi->purchaseOrder->order_date ?? '')
        ->take($this->history_limit)
        ->values()
        ->toArray();
};

$viewPoDetail = function ($poId) {
    $po = \Modules\Purchase\Models\PurchaseOrder::with(['vendor', 'items.item.unit'])->find($poId);
    if ($po) {
        $this->detail_po = $po->toArray();
    }
};

$saveCart = function ($cartData) {
    $this->items = $cartData['items'] ?? [];
    $this->ongkir = $cartData['ongkir'] ?? 0;
    $this->diskon_global = $cartData['diskon_global'] ?? 0;
    $this->pajak_persen = $cartData['pajak_persen'] ?? 0;
    $this->pajak_nominal = $cartData['pajak_nominal'] ?? 0;

    if (!$this->order_id) {
        // Generate ODM-1000 format
        $latestPo = PurchaseOrder::orderBy('id', 'desc')->first();
        $nextId = $latestPo ? $latestPo->id + 1 : 1000;
        $this->po_number = 'ODM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    $this->validate([
        'po_number' => 'required|string|max:100|unique:purchase_orders,po_number,' . $this->order_id,
        'vendor_id' => 'required|exists:vendors,id',
        'order_date' => 'required|date',
        'items' => 'required|array|min:1',
        'items.*.qty' => 'required|numeric|min:0.1',
        'items.*.unit_price' => 'required|numeric|min:0',
    ]);

    // Recalculate grand total server-side
    $subtotal = collect($this->items)->sum('subtotal');
    $grandTotal = $subtotal + (float)$this->ongkir - (float)$this->diskon_global + (float)$this->pajak_nominal;

    $po = PurchaseOrder::updateOrCreate(
        ['id' => $this->order_id],
        [
            'po_number' => $this->po_number,
            'vendor_id' => $this->vendor_id,
            'order_date' => $this->order_date,
            'status' => $this->status,
            'ongkir' => $this->ongkir,
            'diskon_global' => $this->diskon_global,
            'pajak' => $this->pajak_nominal,
            'total_amount' => $grandTotal,
        ]
    );

    // Hapus item lama (jika edit) yang tidak ada di keranjang lagi
    $currentItemIds = collect($this->items)->pluck('id')->filter()->toArray();
    PurchaseOrderItem::where('purchase_order_id', $po->id)
                     ->whereNotIn('id', $currentItemIds)
                     ->delete();

    // Simpan items
    foreach ($this->items as $item) {
        PurchaseOrderItem::updateOrCreate(
            ['id' => $item['id'] ?? null],
            [
                'purchase_order_id' => $po->id,
                'item_id' => $item['item_id'],
                'quantity' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['note'] ?? null, // Simpan ke DB
            ]
        );
    }

    Flux::toast('Purchase Order berhasil disimpan!', 'success');
    $this->redirectRoute('purchase.orders.kanban', navigate: true);
};
?>

<div class="xl:max-w-7xl xl:mx-auto" x-data="cartSystem()" @item-selected.window="addItem($event.detail.item)" @vendor-selected.window="$wire.selectVendor($event.detail.vendorId)">
    
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        {{-- KOLOM KIRI: Daftar Barang (Lebar 7 atau 8 kolom dari 12) --}}
        <div x-data="{ showHeader: true, lastScroll: 0 }" 
             @scroll.window="
                let currentScroll = window.scrollY;
                if (currentScroll > lastScroll && currentScroll > 50) {
                    showHeader = false;
                } else if (currentScroll < lastScroll - 5) {
                    showHeader = true;
                }
                lastScroll = currentScroll;
             "
             class="lg:col-span-8 xl:col-span-8 space-y-6">
             
            {{-- Form Input Barang --}}
            <div class="flex flex-col relative">
                
                {{-- Search & Gallery Button --}}
                <div :class="showHeader ? 'translate-y-0 opacity-100' : '-translate-y-[120%] opacity-0 pointer-events-none'"
                     class="sticky top-0 z-20 flex items-end gap-2 p-3 mb-4 bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800 rounded-xl transition-all duration-300 ease-in-out">
                    <div class="flex-1 relative" x-data="{ focused: false }" @click.outside="focused = false">
                        <flux:input 
                            wire:model.live.debounce.300ms="search_query" 
                            @focus="focused = true; $wire.set('show_suggestions', true)"
                            icon="magnifying-glass" 
                            placeholder="Ketik nama atau kode barang untuk mencari..." />
                        
                        {{-- Dropdown Suggestion --}}
                        <div x-show="focused && $wire.show_suggestions && $wire.search_query.length >= 2" 
                             x-cloak
                             class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            @if(count($this->searchResults) > 0)
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-64 overflow-y-auto">
                                    @foreach($this->searchResults as $res)
                                        <li @click="addItem({ item_id: {{ $res->id }}, name: '{{ addslashes($res->name) }}', code: '{{ $res->code ?? '0001' }}', unit_price: {{ $res->purchase_price ?? 0 }}, image: '{{ $res->image }}' }); $wire.search_query = ''; $wire.show_suggestions = false;"
                                            class="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex items-center gap-3 transition-colors">
                                            @if($res->image)
                                                <img src="{{ Storage::url($res->image) }}" class="w-8 h-8 rounded bg-zinc-100 object-cover">
                                            @else
                                                <div class="w-8 h-8 rounded bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                                                    <flux:icon.cube class="w-4 h-4" />
                                                </div>
                                            @endif
                                            <div>
                                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $res->name }}</div>
                                                <div class="text-[10px] text-zinc-500 font-mono">{{ $res->code }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="px-4 py-3 text-sm text-zinc-500 text-center">Barang tidak ditemukan.</div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Tombol Galeri Barang --}}
                    <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                        <div class="flex items-center gap-2">
                            <flux:icon.squares-2x2 class="w-4 h-4" x-show="!loading" />
                            <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="hidden md:block">Galeri</span>
                        </div>
                    </flux:button>
                </div>
                
                {{-- Cart Header (Total Items & Delete All) --}}
                <div class="flex items-center justify-between px-1 sm:px-2 mb-2 mt-1" x-show="items.length > 0" x-cloak>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Total :</h3>
                        <span class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 py-0.5 px-2.5 rounded-full text-xs font-semibold shadow-sm" x-text="items.length + ' Item'"></span>
                    </div>
                    <flux:button size="sm" variant="subtle" icon="trash" @click="if(confirm('Hapus semua barang dari keranjang?')) { items = []; updateSubtotal(); }" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 !px-2 !h-8 transition-colors">
                        <span class="hidden sm:inline">All</span>
                    </flux:button>
                </div>

                {{-- Daftar Barang Terpilih (Modern List) --}}
                <div class="flex-1 space-y-4">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="relative flex flex-col sm:flex-row bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm transition-colors">
                            {{-- Delete Button (Top Left over Image) --}}
                            <div class="absolute top-2 left-2 sm:-top-3 sm:-left-3 z-10">
                                <flux:button variant="primary" size="sm" icon="trash" @click="removeItem(index)" class="!rounded-full shadow-md hover:!bg-red-500 hover:!border-red-500 hover:scale-110 transition-all duration-200" />
                            </div>

                            {{-- Image Container (Top on Mobile, Left on Desktop) --}}
                            <div class="w-full sm:w-32 h-32 sm:h-auto shrink-0 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center relative rounded-t-2xl sm:rounded-none sm:rounded-l-2xl overflow-hidden">
                                <template x-if="item.image">
                                    <img :src="'/storage/' + item.image" class="w-full h-full object-cover absolute inset-0">
                                </template>
                                <template x-if="!item.image">
                                    <flux:icon.cube class="w-10 h-10 text-zinc-300 dark:text-zinc-600" />
                                </template>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 flex flex-col p-4 sm:p-5 relative min-w-0">
                                {{-- Floating Action Buttons on the Right --}}
                                <div class="absolute bottom-4 right-4" :class="open ? 'z-50' : ''" x-data="{ open: false, placement: 'bottom' }">
                                    {{-- Tombol Edit (Amber jika ada catatan, Primary jika kosong) --}}
                                    <div x-show="item.note" x-cloak>
                                        <flux:button size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" />
                                    </div>
                                    <div x-show="!item.note">
                                        <flux:button variant="primary" size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" />
                                    </div>
                                    
                                    {{-- Popover Quick Note --}}
                                    <div x-show="open" @click.away="open = false" x-transition 
                                         class="absolute right-0 w-[calc(100vw-2rem)] sm:w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-5 cursor-auto z-50" 
                                         :class="placement === 'top' ? 'bottom-full mb-3 origin-bottom-right' : 'top-full mt-3 origin-top-right'"
                                         style="display: none;">
                                        <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase mb-4">CATATAN CEPAT</h3>
                                        <div class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-3 shadow-inner border border-zinc-200 dark:border-zinc-700 focus-within:border-zinc-300 focus-within:ring-1 focus-within:ring-zinc-300 transition-colors">
                                            <textarea x-model="item.note" class="w-full bg-transparent border-none focus:border-none focus:ring-0 outline-none focus:outline-none text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 dark:placeholder-zinc-500 min-h-[120px] resize-none p-0" placeholder="Tulis catatan..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                {{-- Product Info --}}
                                <div class="pr-10 sm:pr-12">
                                    <h4 class="font-bold text-[#1a2b4c] dark:text-zinc-100 text-[14px] sm:text-[15px] leading-snug line-clamp-1 uppercase" x-text="item.name"></h4>
                                    <div class="text-[12px] sm:text-[13px] text-zinc-400 font-medium mt-0.5 sm:mt-1 uppercase" x-text="item.code || '0001'"></div>
                                    
                                    {{-- Cuplikan Catatan (Opsi 1) --}}
                                    <div x-show="item.note" x-cloak class="mt-1.5 sm:mt-2 flex items-start gap-1.5 text-[11px] sm:text-[12px] text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.document-text class="w-3 h-3 sm:w-3.5 sm:h-3.5 mt-0.5 shrink-0 text-amber-500" />
                                        <span class="italic line-clamp-2 leading-tight" x-text="item.note"></span>
                                    </div>
                                </div>

                                {{-- Controls Row --}}
                                <div class="mt-3 sm:mt-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 pr-8 sm:pr-12">
                                    <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                                        {{-- Editable Price Input (Image 1 style) --}}
                                        <div class="relative flex items-center flex-1 sm:w-40 min-w-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg" :class="open ? 'z-50' : ''" x-data="{ open: false, placement: 'bottom', expanded: false }">
                                            <x-rupiah-input 
                                                x-model="item.unit_price" 
                                                @input="updateItemSubtotal(index)"
                                                align="center" 
                                                appearance="transparent" 
                                                class="w-full pr-8" 
                                            />
                                            <button type="button" @click="open = !open; if(open) { $wire.showPriceHistory(item.item_id, item.name); $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" 
                                                    class="absolute right-2.5 transition-colors"
                                                    :class="item.has_history ? 'text-blue-500 hover:text-blue-600' : 'text-zinc-300 hover:text-zinc-500'">
                                                <flux:icon.clock class="w-4 h-4" />
                                            </button>
                                            
                                            {{-- Popover Price History (Livewire) --}}
                                            <div x-show="open" @click.away="open = false" x-transition 
                                                 class="absolute right-0 sm:right-auto sm:left-0 w-[310px] sm:w-[380px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-0 cursor-auto overflow-hidden z-50 transition-all duration-300" 
                                                 :class="[
                                                    placement === 'top' ? 'bottom-full mb-3 origin-bottom-right sm:origin-bottom-left' : 'top-full mt-3 origin-top-right sm:origin-top-left',
                                                    expanded ? 'sm:w-[500px] sm:-left-20' : ''
                                                 ]"
                                                 style="display: none;">
                                                <div class="flex justify-between items-center p-4 border-b border-zinc-100 dark:border-zinc-800">
                                                    <div class="flex items-center gap-3">
                                                        <button type="button" @click="expanded = !expanded" class="text-zinc-400 hover:text-cyan-600 transition-colors" title="Perbesar/Perkecil Tampilan">
                                                            <flux:icon.arrows-pointing-out class="w-3.5 h-3.5" x-show="!expanded" />
                                                            <flux:icon.arrows-pointing-in class="w-3.5 h-3.5" x-show="expanded" x-cloak />
                                                        </button>
                                                        <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">RIWAYAT <span class="text-zinc-500" x-text="'(' + $wire.price_history.length + ')'"></span></h3>
                                                    </div>
                                                    <button type="button" @click="open = false" class="text-zinc-400 hover:text-zinc-600">
                                                        <flux:icon.x-mark class="w-4 h-4" />
                                                    </button>
                                                </div>
                                                <div class="overflow-y-auto p-2 transition-all duration-300" :class="expanded ? 'max-h-[450px]' : 'max-h-64'">
                                                    <template x-for="history in $wire.price_history">
                                                        <div x-data="{ showDetail: false }" class="border-b border-zinc-50 dark:border-zinc-800/50 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors group">
                                                            <div @click="item.unit_price = history.unit_price; updateItemSubtotal(index); open = false" 
                                                                 class="p-3 cursor-pointer">
                                                                <div class="flex justify-between items-center">
                                                                    <div class="flex-1">
                                                                        <div class="text-[11px] text-zinc-500 font-medium group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors" x-text="(new Date(history.purchase_order?.order_date || Date.now())).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) + ' &bull; ' + (history.purchase_order?.po_number || 'INV-0000')">
                                                                        </div>
                                                                        <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                                                            <flux:icon.building-storefront class="w-3.5 h-3.5 text-zinc-400 group-hover:text-cyan-500 transition-colors" />
                                                                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors truncate max-w-[120px]" x-text="history.purchase_order?.vendor?.name || 'Vendor Tidak Diketahui'"></span>
                                                                            <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                                                            <span class="text-[10px] font-bold text-zinc-500 uppercase group-hover:text-cyan-600/70 dark:group-hover:text-cyan-400/70 transition-colors bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded" x-text="'Beli: ' + history.quantity + ' ' + (history.item?.unit?.name || '')"></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-right flex items-center gap-3">
                                                                        <div class="font-bold text-zinc-700 text-sm group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">
                                                                            <span x-text="'Rp' + formatRupiah(history.unit_price)"></span>
                                                                            <span class="text-[10px] text-zinc-400 font-medium lowercase group-hover:text-cyan-500/80 transition-colors" x-text="'/' + (history.item?.unit?.name || 'unit')"></span>
                                                                        </div>
                                                                        <button type="button" @click.stop="showDetail = !showDetail; if(showDetail && (!$wire.detail_po || $wire.detail_po.id !== history.purchase_order_id)) $wire.viewPoDetail(history.purchase_order_id)" 
                                                                                class="p-1.5 text-zinc-400 hover:text-cyan-600 hover:bg-cyan-50 dark:hover:bg-cyan-900/30 rounded-full transition-all" 
                                                                                :class="showDetail ? 'bg-cyan-50 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400' : ''"
                                                                                title="Lihat Detail PO">
                                                                            <flux:icon.eye class="w-4 h-4" />
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            {{-- Inline Detail Pop-up (Accordion) --}}
                                                            <div x-show="showDetail" x-collapse x-cloak class="bg-zinc-100/50 dark:bg-zinc-800/30 border-t border-zinc-100 dark:border-zinc-800 p-3 shadow-inner">
                                                                <div wire:loading wire:target="viewPoDetail" class="text-[10px] text-zinc-400 text-center w-full py-2">
                                                                    Memuat isi Purchase Order...
                                                                </div>
                                                                
                                                                <div wire:loading.remove wire:target="viewPoDetail">
                                                                    <template x-if="$wire.detail_po && $wire.detail_po.id === history.purchase_order_id">
                                                                        <div class="space-y-2">
                                                                            <div class="flex justify-between items-center text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-2">
                                                                                <span>Daftar Barang (Total: <span x-text="'Rp' + formatRupiah($wire.detail_po.grand_total)"></span>)</span>
                                                                            </div>
                                                                            <div class="space-y-1.5 max-h-32 overflow-y-auto pr-1 custom-scrollbar">
                                                                                <template x-for="ditem in $wire.detail_po.items">
                                                                                    <div class="flex justify-between items-center text-[11px] bg-white dark:bg-zinc-900 p-1.5 rounded border border-zinc-200/50 dark:border-zinc-700/50"
                                                                                         :class="ditem.item_id === history.item_id ? 'border-cyan-200 dark:border-cyan-800 bg-cyan-50/30 dark:bg-cyan-900/10' : ''">
                                                                                        <div class="flex-1 truncate pr-2 font-medium" :class="ditem.item_id === history.item_id ? 'text-cyan-700 dark:text-cyan-400' : 'text-zinc-600 dark:text-zinc-300'" x-text="ditem.item?.name || 'Unknown'"></div>
                                                                                        <div class="w-16 text-right text-zinc-500" x-text="ditem.quantity + ' ' + (ditem.item?.unit?.name || '')"></div>
                                                                                        <div class="w-20 text-right font-bold text-zinc-700 dark:text-zinc-300" x-text="'Rp' + formatRupiah(ditem.unit_price)"></div>
                                                                                    </div>
                                                                                </template>
                                                                            </div>
                                                                        </div>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <div x-show="$wire.price_history.length === 0" class="p-4 text-center text-xs text-zinc-400">
                                                        Belum ada riwayat harga.
                                                    </div>
                                                    
                                                    {{-- Tombol Load More --}}
                                                    <div x-show="$wire.has_more_history" class="p-2 border-t border-zinc-100 dark:border-zinc-800">
                                                        <button type="button" wire:click="loadMoreHistory" class="w-full py-1.5 text-xs font-semibold text-cyan-600 dark:text-cyan-400 bg-cyan-50 dark:bg-cyan-900/20 hover:bg-cyan-100 dark:hover:bg-cyan-900/40 rounded transition-colors flex justify-center items-center gap-1">
                                                            <span wire:loading.remove wire:target="loadMoreHistory">Muat Lebih Banyak</span>
                                                            <span wire:loading wire:target="loadMoreHistory">Memuat...</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Qty Control --}}
                                        <div class="flex items-center bg-zinc-50 dark:bg-zinc-800/50 rounded-lg h-[32px] shrink-0">
                                            <button type="button" @click="decrementQty(index)" class="w-8 h-full flex items-center justify-center text-zinc-400 hover:text-blue-600">-</button>
                                            <input type="number" x-model.number="item.qty" @input="updateItemSubtotal(index)" class="w-10 text-center bg-transparent border-none focus:ring-0 p-0 text-[13px] font-bold text-[#1a2b4c] dark:text-zinc-100" min="0.1" step="0.1" />
                                            <button type="button" @click="incrementQty(index)" class="w-8 h-full flex items-center justify-center text-zinc-400 hover:text-blue-600">+</button>
                                        </div>
                                    </div>

                                    {{-- Subtotal --}}
                                    <div class="flex items-center justify-start sm:justify-end w-full sm:flex-1 text-sm mt-1 sm:mt-0">
                                        <span class="text-zinc-400 text-[11px] font-bold mr-1 uppercase">SUB:</span>
                                        <span class="font-bold text-[#1a2b4c] dark:text-zinc-100 text-[14px] sm:text-[15px]" x-text="'Rp' + formatRupiah(item.subtotal)"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="items.length === 0" x-cloak class="py-20 text-center flex flex-col items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl bg-zinc-50/50 dark:bg-zinc-900/20">
                        <div class="w-16 h-16 bg-zinc-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mb-4">
                            <flux:icon.shopping-cart class="w-8 h-8 text-zinc-400 dark:text-zinc-600" />
                        </div>
                        <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Keranjang Kosong</h3>
                        <p class="text-sm text-zinc-500 mt-1 max-w-sm">Mulai ketik di kotak pencarian atau buka galeri untuk menambahkan barang ke PO ini.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN: Ringkasan Biaya & Tombol (Lebar 4 kolom dari 12) --}}
        <div class="lg:col-span-4 xl:col-span-4 sm:grid sm:grid-cols-2 sm:gap-4 md:grid-cols-1 md:gap-0 space-y-6 sticky top-6">

            {{-- Informasi Dokumen --}}
            <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm space-y-6">
                
                {{-- Tanggal Order --}}
                <div>
                    <flux:heading size="lg" class="mb-3">Tanggal Order</flux:heading>
                    <flux:input type="date" wire:model="order_date" icon="calendar" class="[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-full" required />
                </div>
                
                <flux:separator />

                {{-- Pilih Vendor --}}
                <div>
                    <flux:heading size="lg" class="mb-3">Vendor / Supplier</flux:heading>
                    
                    @if($selected_vendor)
                        {{-- Vendor Card Terpilih --}}
                        <div class="group flex items-center gap-4 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                            <flux:avatar src="{{ $selected_vendor['image'] ? Storage::url($selected_vendor['image']) : '' }}" fallback="{{ substr($selected_vendor['name'], 0, 2) }}" size="lg" class="shadow-sm" />
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base leading-none truncate">{{ $selected_vendor['name'] }}</h3>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 leading-none">
                                        {{ $selected_vendor['type'] ?? 'Umum' }}
                                    </span>
                                </div>
                                
                                <div class="mt-2 flex flex-col gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.phone class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                        <span class="truncate">{{ $selected_vendor['phone'] ?: 'Belum ada nomor telepon' }}</span>
                                    </div>
                                    @if($selected_vendor['province'] || $selected_vendor['city'])
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.map-pin class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                        <span class="truncate lg:text-[6px] xl:text-[10px]" title="{{ implode(', ', array_filter([$selected_vendor['district'] ?? null, $selected_vendor['city'] ?? null, $selected_vendor['province'] ?? null])) }}">
                                            {{ implode(', ', array_filter([$selected_vendor['district'] ?? null, $selected_vendor['city'] ?? null, $selected_vendor['province'] ?? null])) }}
                                        </span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Tombol Silang Batal Pilih --}}
                            <div class="shrink-0">
                                <flux:button variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors rounded-full w-8 h-8 flex items-center justify-center p-0" wire:click="clearVendor" wire:loading.attr="disabled" tooltip="Ganti Vendor"></flux:button>
                            </div>
                        </div>
                    @else
                        {{-- Vendor Search & Gallery Button --}}
                        <div class="flex items-end gap-2 relative">
                            <div class="flex-1 relative" x-data="{ focused: false }" @click.outside="focused = false">
                                <flux:input 
                                    wire:model.live.debounce.300ms="vendor_search_query" 
                                    @focus="focused = true; $wire.set('show_vendor_suggestions', true)"
                                    icon="building-storefront" 
                                    placeholder="Cari vendor..." />
                                
                                {{-- Dropdown Vendor Suggestion --}}
                                <div x-show="focused && $wire.show_vendor_suggestions && $wire.vendor_search_query.length >= 2" 
                                     x-cloak
                                     class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                    @if(count($this->vendorSearchResults) > 0)
                                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-64 overflow-y-auto">
                                            @foreach($this->vendorSearchResults as $v)
                                                <li wire:click="selectVendor({{ $v->id }})" wire:loading.class="opacity-50 pointer-events-none"
                                                    class="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex items-center gap-3 transition-colors">
                                                    <flux:avatar src="{{ $v->image ? Storage::url($v->image) : '' }}" fallback="{{ substr($v->name, 0, 2) }}" size="sm" />
                                                    <div>
                                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $v->name }}</div>
                                                        <div class="text-[10px] text-zinc-500">{{ $v->type }}</div>
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <div class="px-4 py-3 text-sm text-zinc-500 text-center">Vendor tidak ditemukan.</div>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Tombol Galeri Vendor --}}
                            <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('vendor-gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                                <div class="flex items-center gap-2">
                                    <flux:icon.squares-2x2 class="w-4 h-4" x-show="!loading" />
                                    <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="hidden xl:block">Galeri</span>
                                </div>
                            </flux:button>
                        </div>
                        @error('vendor_id') <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span> @enderror
                    @endif
                </div>
            </div>

            {{-- Ringkasan Biaya --}}
            <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                <flux:heading size="lg" class="mb-4">Ringkasan Biaya</flux:heading>
                
                <div class="space-y-4">
                    {{-- Subtotal Items --}}
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-zinc-500">Subtotal Barang</span>
                        <span class="font-medium text-zinc-900 dark:text-zinc-100" x-text="'Rp ' + formatRupiah(subtotal_amount)"></span>
                    </div>

                    <flux:separator />

                    {{-- Diskon --}}
                    <flux:field>
                        <flux:label>Diskon Global</flux:label>
                        <x-rupiah-input x-model="diskon_global" @input="calculateTax()" />
                    </flux:field>
                    
                    {{-- Ongkir --}}
                    <flux:field>
                        <flux:label>Ongkos Kirim</flux:label>
                        <x-rupiah-input x-model="ongkir" @input="calculateTax()" />
                    </flux:field>

                    {{-- Pajak PPN (Cached Options) --}}
                    <div x-data="{
                            options: JSON.parse(localStorage.getItem('purchase_tax_options')) || [0, 4, 11, 12],
                            showAdd: false,
                            deleteMode: false,
                            newTax: '',
                            addOption() {
                                let val = parseFloat(this.newTax);
                                if(!isNaN(val) && !this.options.includes(val) && val >= 0) {
                                    this.options.push(val);
                                    this.options.sort((a,b) => a - b);
                                    localStorage.setItem('purchase_tax_options', JSON.stringify(this.options));
                                }
                                this.newTax = '';
                                this.showAdd = false;
                            },
                            removeOption(val) {
                                this.options = this.options.filter(o => o !== val);
                                if(this.options.length === 0) this.options = [0]; // Default fallback
                                localStorage.setItem('purchase_tax_options', JSON.stringify(this.options));
                                if ($data.pajak_persen == val) {
                                    $data.setPajakPersen(0);
                                }
                                if(!this.options.some(o => o !== 0)) {
                                    this.deleteMode = false;
                                }
                            }
                        }" 
                        class="flex items-center justify-between text-sm">
                        
                        <span class="text-zinc-500 whitespace-nowrap text-xs">PPN</span>
                        
                        <div class="flex flex-wrap items-center justify-end gap-1.5 ml-4">
                            <template x-for="opt in options" :key="opt">
                                <div class="relative flex items-center">
                                    <button type="button" 
                                        @click="deleteMode ? removeOption(opt) : $data.setPajakPersen(opt)"
                                        :class="[$data.pajak_persen == opt ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-white text-zinc-700 border border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700', deleteMode && opt !== 0 ? 'animate-pulse ring-2 ring-red-400' : '']"
                                        class="px-2 py-1 text-xs rounded-md font-medium transition-all cursor-pointer"
                                        x-text="opt === 0 ? '0%' : opt + '%'">
                                    </button>
                                    
                                    {{-- Tombol Silang Hapus (Hover Desktop atau Mode Hapus Mobile) --}}
                                    <button type="button" x-show="opt !== 0" @click.stop="removeOption(opt)" 
                                        :class="deleteMode ? 'flex' : 'hidden group-hover:flex'"
                                        class="absolute -top-1.5 -right-1.5 items-center justify-center w-3.5 h-3.5 bg-red-500 text-white rounded-full text-[8px] hover:bg-red-600 shadow-sm z-10">
                                        ✕
                                    </button>
                                </div>
                            </template>
                            
                            {{-- Tombol Tambah --}}
                            <button type="button" x-show="!showAdd" @click="showAdd = true; deleteMode = false; $nextTick(() => $refs.newTaxInput.focus())" class="px-2 py-1 text-xs rounded-md font-medium border border-dashed border-zinc-300 text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 dark:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-300 transition-colors cursor-pointer">
                                +
                            </button>
                            
                            {{-- Tombol Mode Hapus (Untuk Layar Sentuh) --}}
                            <button type="button" x-show="!showAdd && options.some(o => o !== 0)" @click="deleteMode = !deleteMode" 
                                :class="deleteMode ? 'bg-red-50 text-red-600 border-red-200 dark:bg-red-900/30 dark:border-red-800' : 'text-zinc-400 border-transparent hover:bg-zinc-50 dark:hover:bg-zinc-800'"
                                class="p-1 rounded-md border transition-colors cursor-pointer" title="Mode Hapus">
                                <flux:icon.trash class="w-3.5 h-3.5" />
                            </button>
                            
                            {{-- Form Tambah Kecil --}}
                            <div x-show="showAdd" x-cloak class="flex items-center gap-1 bg-zinc-50 dark:bg-zinc-800 p-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                <input type="number" x-ref="newTaxInput" x-model="newTax" @keydown.enter.prevent="addOption" @keydown.escape="showAdd = false" class="w-12 px-1 py-0.5 text-xs rounded bg-white border border-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 text-center focus:outline-none focus:border-zinc-400" placeholder="%" />
                                <button type="button" @click="addOption" class="p-1 text-emerald-600 hover:text-emerald-700 dark:text-emerald-500" title="Simpan"><flux:icon.check class="w-3 h-3" /></button>
                                <button type="button" @click="showAdd = false" class="p-1 text-zinc-400 hover:text-red-500" title="Batal"><flux:icon.x-mark class="w-3 h-3" /></button>
                            </div>
                        </div>
                    </div>

                    <flux:separator />

                    {{-- Grand Total --}}
                    <div class="flex justify-between items-end">
                        <span class="text-base font-medium text-zinc-700 dark:text-zinc-300">Grand Total</span>
                        <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + formatRupiah(grand_total)"></span>
                    </div>
                    
                </div>
            </div>
            
            {{-- Tombol Aksi --}}
            <div class="sm:col-span-2 md:col-span-1 bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
                <div class="flex gap-2 w-full">
                    <flux:button variant="ghost" class="w-1/3" href="{{ route('purchase.orders.kanban') }}" wire:navigate wire:loading.attr="disabled">Batal</flux:button>
                    <flux:button variant="primary" class="w-2/3" icon="check" @click="submitCart()" x-bind:disabled="isSubmitting">
                        <span x-show="!isSubmitting">Simpan PO</span>
                        <span x-show="isSubmitting" class="flex items-center gap-2">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </div>
        </div>
    </form>

    {{-- Modals Eksternal (Di-load sebagai komponen Livewire terpisah) --}}
    <livewire:global.item-gallery-modal />
    <livewire:global.vendor-gallery-modal />
    
    {{-- Modal Tambah Barang dari komponen global --}}
    <livewire:global.item-form-modal />
    <livewire:global.vendor-form-modal />


    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cartSystem', () => ({
            items: @json($items),
            ongkir: {{ (float) ($ongkir ?? 0) }},
            diskon_global: {{ (float) ($diskon_global ?? 0) }},
            pajak_persen: {{ (float) ($pajak_persen ?? 0) }},
            pajak_nominal: {{ (float) ($pajak_nominal ?? 0) }},

            get subtotal_amount() {
                return this.items.reduce((sum, item) => sum + ((parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0)), 0);
            },

            get grand_total() {
                return this.subtotal_amount + (parseFloat(this.ongkir) || 0) - (parseFloat(this.diskon_global) || 0) + (parseFloat(this.pajak_nominal) || 0);
            },

            calculateTax() {
                if (this.pajak_persen > 0) {
                    let sub = this.subtotal_amount + (parseFloat(this.ongkir) || 0) - (parseFloat(this.diskon_global) || 0);
                    this.pajak_nominal = (this.pajak_persen / 100) * sub;
                }
            },

            setPajakPersen(persen) {
                this.pajak_persen = persen;
                this.calculateTax();
            },

            updateItemSubtotal(index) {
                let item = this.items[index];
                item.subtotal = (parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
                this.calculateTax();
            },

            incrementQty(index) {
                this.items[index].qty = (parseFloat(this.items[index].qty) || 0) + 1;
                this.updateItemSubtotal(index);
            },

            decrementQty(index) {
                let current = parseFloat(this.items[index].qty) || 0;
                if (current > 1) {
                    this.items[index].qty = current - 1;
                    this.updateItemSubtotal(index);
                }
            },

            addItem(newItem) {
                let existingIndex = this.items.findIndex(i => i.item_id == newItem.item_id);
                if (existingIndex !== -1) {
                    // Pindahkan item ke urutan paling atas (index 0)
                    let item = this.items.splice(existingIndex, 1)[0];
                    this.items.unshift(item);
                    // Increment qty karena posisinya sekarang ada di index 0
                    this.incrementQty(0);
                } else {
                    this.items.unshift({
                        id: null,
                        item_id: newItem.item_id,
                        name: newItem.name,
                        code: newItem.code || '0001',
                        qty: 1,
                        unit_price: newItem.unit_price,
                        subtotal: newItem.unit_price,
                        image: newItem.image,
                        note: '',
                        has_history: newItem.has_history
                    });
                    this.calculateTax();
                }
            },

            removeItem(index) {
                this.items.splice(index, 1);
                this.calculateTax();
            },

            isSubmitting: false,

            async submitCart() {
                if (this.isSubmitting) return;
                this.isSubmitting = true;
                try {
                    await this.$wire.saveCart({
                        items: this.items,
                        ongkir: this.ongkir,
                        diskon_global: this.diskon_global,
                        pajak_persen: this.pajak_persen,
                        pajak_nominal: this.pajak_nominal,
                        grand_total: this.grand_total
                    });
                } finally {
                    this.isSubmitting = false;
                }
            },
            
            formatRupiah(number) {
                if (!number) return '0';
                return Math.round(number).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
        }));
    });
    </script>

</div>
