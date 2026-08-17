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
    'status' => 'processing',
    
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
    
    'notes' => '',
    'expected_delivery_date' => null,
    'tempNoteContent' => '',
    'editingNoteIndex' => null,
    
    'source_queues' => [],
]);

mount(function ($id = null) {
    if ($id) {
        $po = PurchaseOrder::with(['items.item', 'vendor'])->findOrFail($id);
        $this->order_id = $po->id;
        $this->po_number = $po->po_number;
        $this->vendor_id = $po->vendor_id;
        $this->order_date = $po->order_date;
        $this->expected_delivery_date = $po->expected_delivery_date;
        $this->notes = $po->notes ?? '';
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
                'code' => $detail->item->code ?? '-',
                'qty' => $detail->quantity,
                'unit_price' => $detail->unit_price,
                'subtotal' => $detail->subtotal,
                'image' => $detail->item->image ?? null,
                'note' => $detail->notes ?? '', // Hydrate notes from DB
                'custom_attributes' => $detail->custom_attributes ?? [],
                'custom_attachments' => $detail->custom_attachments ?? [],
                'has_history' => $hasHistory,
            ];
        }
    } else {
        $this->po_number = ''; // Will be generated on save

        if (request()->has('token')) {
            $token = request('token');
            $queueIds = \Illuminate\Support\Facades\Cache::pull('pq_create_' . $token);
            
            if (!$queueIds) {
                // Token tidak valid / sudah dipakai / kadaluarsa
                \Flux::toast('Sesi pembuatan PO tidak valid atau sudah kadaluarsa. Silakan pilih antrean lagi.', variant: 'danger');
                return;
            }
            
            $this->source_queues = $queueIds;
            
            $queues = \Modules\Purchase\Models\PurchaseQueue::with('item')
                        ->whereIn('id', $queueIds)
                        ->where('status', 'approved')
                        ->get();
                        
            $grouped = $queues->groupBy('item_id');
            
            foreach ($grouped as $itemId => $qGroup) {
                $item = $qGroup->first()->item;
                $totalQty = $qGroup->sum(function($q) {
                    return $q->approved_qty ?? $q->requested_qty;
                });
                
                $price = $item->purchase_price ?? 0;
                
                $hasHistory = \Modules\Purchase\Models\PurchaseOrderItem::where('item_id', $itemId)
                    ->whereHas('purchaseOrder', function($q) {
                        $q->where('status', '!=', 'draft');
                    })->exists();

                $combinedNotes = $qGroup->map(function($q) use ($qGroup) {
                    return $q->notes ? ($qGroup->count() > 1 ? "Ref #{$q->queue_number}: " : '') . $q->notes : null;
                })->filter()->implode("<br>");
                
                $finalNote = $qGroup->count() > 1 ? '<strong>Gabungan ' . $qGroup->count() . ' tiket:</strong><br>' . $combinedNotes : $combinedNotes;

                $this->items[] = [
                    'id' => null,
                    'item_id' => $itemId,
                    'name' => ($item->alias ?? false) ? $item->alias . ' - ' . $item->name : ($item->name ?? 'Unknown'),
                    'code' => $item->code ?? '-',
                    'qty' => $totalQty,
                    'min_qty' => $totalQty,
                    'unit_price' => $price,
                    'subtotal' => $totalQty * $price,
                    'image' => $item->image ?? null,
                    'note' => $finalNote,
                    'has_history' => $hasHistory,
                    'custom_attributes' => $qGroup->first()->custom_attributes ?? [],
                    'custom_attachments' => $qGroup->first()->custom_attachments ?? [],
                ];
            }
        }
    }
});

// Computed search results remain in Livewire

$vendorSearchResults = computed(function () {
    if (strlen($this->vendor_search_query) < 2) return [];
    return Vendor::where('name', 'like', '%' . $this->vendor_search_query . '%')
               ->orWhere('phone', 'like', '%' . $this->vendor_search_query . '%')
               ->take(5)->get();
});





$clearVendor = function () {
    $this->selected_vendor = null;
    $this->vendor_id = '';
};

$searchResults = computed(function () {
    if (strlen($this->search_query) < 2) return [];
    return Item::with(['type', 'unit'])
               ->where('name', 'like', '%' . $this->search_query . '%')
               ->orWhere('code', 'like', '%' . $this->search_query . '%')
               ->take(20)->get();
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
        ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
        ->where('purchase_order_items.item_id', $this->history_item_id)
        ->where('purchase_orders.status', '!=', 'draft')
        ->orderBy('purchase_orders.order_date', 'desc')
        ->orderBy('purchase_order_items.id', 'desc')
        ->select('purchase_order_items.*')
        ->take($this->history_limit)
        ->get()
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
        // Generate PEM-1000 format
        $latestPo = PurchaseOrder::orderBy('id', 'desc')->first();
        $nextId = $latestPo ? $latestPo->id + 1 : 1;
        $this->po_number = 'PEM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    $this->validate([
        'po_number' => 'required|string|max:100|unique:purchase_orders,po_number,' . $this->order_id,
        'vendor_id' => 'required|exists:vendors,id',
        'order_date' => 'required|date',
        'expected_delivery_date' => 'nullable|date',
        'items' => 'required|array|min:1',
        'items.*.qty' => 'required|integer|min:1',
        'items.*.unit_price' => 'required|numeric|min:0',
    ]);

    // Validasi aturan bisnis: Qty tidak boleh kurang dari tiket antrean asal
    if (!empty($this->source_queues)) {
        $queues = \Modules\Purchase\Models\PurchaseQueue::whereIn('id', $this->source_queues)->get();
        
        // Anti-Double-Fulfillment check
        $invalidQueues = $queues->filter(fn($q) => $q->status !== 'approved');
        if ($invalidQueues->isNotEmpty()) {
            \Flux::toast("Beberapa tiket antrean sudah diproses di PO lain. Silakan kembali ke papan Kanban.", variant: 'danger');
            return;
        }

        $groupedQueues = $queues->groupBy('item_id');
        
        foreach ($groupedQueues as $itemId => $qGroup) {
            $minRequired = $qGroup->sum(function($q) {
                return $q->approved_qty ?? $q->requested_qty;
            });
            
            $cartItem = collect($this->items)->firstWhere('item_id', $itemId);
            if (!$cartItem || (float)$cartItem['qty'] < (float)$minRequired) {
                \Flux::toast("Kuantitas pesanan tidak boleh kurang dari {$minRequired} unit karena barang tersebut ditarik dari antrean otomatis.", variant: 'danger');
                return;
            }
        }
    }

    // Recalculate grand total server-side
    $subtotal = collect($this->items)->sum('subtotal');
    $grandTotal = $subtotal + (float)$this->ongkir - (float)$this->diskon_global + (float)$this->pajak_nominal;

    if ($grandTotal <= 0) {
        $this->addError('items', 'Total pesanan tidak boleh nol (0). Silakan periksa kembali harga, diskon, atau ongkos kirim.');
        \Flux::toast('Gagal menyimpan: Total pesanan tidak boleh 0.', variant: 'danger');
        return;
    }

    $hasCustomItems = collect($this->items)->contains(function ($item) {
        return (!empty($item['custom_attributes']) || !empty($item['custom_attachments']));
    });
    $finalNotes = $this->notes;
    if ($hasCustomItems && !str_contains($finalNotes ?? '', '[CUSTOM]')) {
        $finalNotes = trim(($finalNotes ?? '') . "\n\n[CUSTOM]");
    }

    // Set initial status for new PO based on Workflow settings
    $finalStatus = $this->status;
    if (!$this->order_id) {
        $finalStatus = requires_purchase_approval() ? 'pending_approval' : 'processing';
    }

    $data = [
        'po_number' => $this->po_number,
        'vendor_id' => $this->vendor_id,
        'order_date' => $this->order_date,
        'expected_delivery_date' => $this->expected_delivery_date,
        'status' => $finalStatus,
        'ongkir' => $this->ongkir,
        'diskon_global' => $this->diskon_global,
        'pajak' => $this->pajak_nominal,
        'total_amount' => $grandTotal,
        'notes' => $finalNotes,
    ];

    if (!$this->order_id) {
        $data['created_by'] = auth()->id();
    }

    $po = PurchaseOrder::updateOrCreate(
        ['id' => $this->order_id],
        $data
    );

    // Hapus item lama (jika edit) yang tidak ada di keranjang lagi
    $currentItemIds = collect($this->items)->pluck('id')->filter()->toArray();
    PurchaseOrderItem::where('purchase_order_id', $po->id)
                     ->whereNotIn('id', $currentItemIds)
                     ->delete();

    // Simpan items
    foreach ($this->items as $item) {
        $poi = PurchaseOrderItem::updateOrCreate(
            ['id' => $item['id'] ?? null],
            [
                'purchase_order_id' => $po->id,
                'item_id' => $item['item_id'],
                'quantity' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['note'] ?? null,
                'custom_attributes' => $item['custom_attributes'] ?? null,
                'custom_attachments' => $item['custom_attachments'] ?? null,
            ]
        );

        if (!empty($this->source_queues)) {
            $queuesToFulfill = \Modules\Purchase\Models\PurchaseQueue::whereIn('id', $this->source_queues)
                                ->where('item_id', $item['item_id'])
                                ->get();
                                
            foreach ($queuesToFulfill as $q) {
                // Buat fulfillment bridge
                \Modules\Purchase\Models\PurchaseQueueFulfillment::updateOrCreate(
                    [
                        'purchase_queue_id' => $q->id,
                        'purchase_order_item_id' => $poi->id,
                    ],
                    [
                        'fulfilled_qty' => $q->approved_qty ?? $q->requested_qty,
                    ]
                );
                
                // Update queue status to ordered
                $q->ordered_qty = $q->approved_qty ?? $q->requested_qty;
                $q->status = 'ordered';
                $q->save();
            }
        }
    }

    $this->source_queues = []; // reset after success

    \App\Events\KanbanUpdated::safeDispatch('purchase_order');
    Flux::toast("Purchase Order {$po->po_number} berhasil disimpan!", 'success');
    session()->flash('new_po_id', $po->id);
    session()->flash('new_po_number', $po->po_number);
    $this->redirectRoute('purchase.orders.kanban', navigate: true);
    
    return true;
};
?>

<div class="xl:max-w-7xl xl:mx-auto" x-data="cartSystem({ vendor: @entangle('selected_vendor') })" 
     @clear-cart.window="items = []; calculateTax(); window.dispatchEvent(new CustomEvent('cart-cleared'));"
     @item-selected.window="addItem($event.detail.item)" 
     @vendor-selected.window="vendor = $event.detail.vendor; $wire.vendor_id = vendor.id"
     @item-customized.window="items[$event.detail.index].note = $event.detail.itemData.note; items[$event.detail.index].custom_attributes = $event.detail.itemData.custom_attributes; items[$event.detail.index].custom_attachments = $event.detail.itemData.custom_attachments;"
     @open-item-editor.window="openItemEditor($event.detail.index)"
     @open-global-editor.window="openGlobalEditor()">
    
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
             :class="items.length > 0 ? 'lg:col-span-8 xl:col-span-8' : 'lg:col-span-12 xl:col-span-12 max-w-4xl mx-auto w-full'"
             class="space-y-6">
             
            {{-- Form Input Barang --}}
            <div class="flex flex-col relative">
                
                {{-- Search & Gallery Button --}}
                <div x-data="{ focused: false }"
                     :class="showHeader ? 'translate-y-0 opacity-100' : '-translate-y-[120%] opacity-0 pointer-events-none'"
                     class="sticky top-0 z-20 flex items-center gap-2 p-3 mb-4 bg-white dark:bg-zinc-900 shadow-sm border border-zinc-200 dark:border-zinc-800 rounded-xl transition-all duration-300 ease-in-out">
                    <div class="flex-1 relative" @click.outside="focused = false">
                        <flux:input 
                            wire:model.live.debounce.300ms="search_query" 
                            @focus="focused = true; $wire.set('show_suggestions', true)"
                            icon="magnifying-glass" 
                            placeholder="Ketik nama atau kode barang untuk mencari..." />
                        
                        {{-- Dropdown Suggestion --}}
                        <div x-show="focused && $wire.show_suggestions && $wire.search_query.length >= 2" 
                             x-cloak
                             class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            {{-- Loading State (Skeleton) --}}
                            <div wire:loading.block wire:target="search_query" class="w-full">
                                <ul>
                                    @for($i=0; $i<3; $i++)
                                    <li class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700 last:border-0 flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-zinc-200 dark:bg-zinc-700/50 animate-pulse shrink-0"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="h-4 bg-zinc-200 dark:bg-zinc-700/50 rounded w-3/4 animate-pulse mb-2"></div>
                                            <div class="flex gap-2">
                                                <div class="h-3 bg-zinc-200 dark:bg-zinc-700/50 rounded w-12 animate-pulse"></div>
                                                <div class="h-3 bg-zinc-200 dark:bg-zinc-700/50 rounded w-20 animate-pulse"></div>
                                            </div>
                                        </div>
                                        <div class="shrink-0 pl-3">
                                            <div class="h-2 bg-zinc-200 dark:bg-zinc-700/50 rounded w-6 ml-auto animate-pulse mb-1.5"></div>
                                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700/50 rounded w-8 ml-auto animate-pulse"></div>
                                        </div>
                                    </li>
                                    @endfor
                                </ul>
                            </div>

                            {{-- Search Results --}}
                            <div wire:loading.remove wire:target="search_query">
                                @if(count($this->searchResults) > 0)
                                    <ul class="max-h-64 overflow-y-auto custom-scrollbar">
                                        @foreach($this->searchResults as $res)
                                            <li @click="addItem({ item_id: {{ $res->id }}, name: '{{ addslashes($res->name) }}', code: '{{ $res->code ?? '0001' }}', unit_price: {{ $res->purchase_price ?? 0 }}, image: '{{ $res->image }}', has_history: {{ \Modules\Purchase\Models\PurchaseOrderItem::where('item_id', $res->id)->whereHas('purchaseOrder', function($q){ $q->where('status', '!=', 'draft'); })->exists() ? 'true' : 'false' }} }); $wire.search_query = ''; $wire.show_suggestions = false; window.playSelectSound?.('ting');"
                                                class="px-4 py-3 border-b border-zinc-100 dark:border-zinc-700 last:border-0 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex items-center gap-3 transition-colors">
                                                @if($res->image)
                                                    <img src="{{ Storage::url($res->image) }}" class="w-9 h-9 rounded-lg bg-zinc-100 object-cover shrink-0 shadow-sm">
                                                @else
                                                    <div class="w-9 h-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0 shadow-sm">
                                                        <flux:icon.cube class="w-5 h-5" />
                                                    </div>
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $res->name }}</div>
                                                    <div class="flex items-center gap-1.5 mt-0.5">
                                                        <span class="text-[6px] md:text-[10px] text-zinc-500 font-mono">{{ $res->code ?? '0001' }}</span>
                                                        @if($res->type)
                                                            @php
                                                                $colors = [
                                                                    'bahan baku utama' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border border-amber-200 dark:border-amber-700/50',
                                                                    'bahan baku penolong' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-700/50',
                                                                    'produk jadi' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700/50',
                                                                    'barang setengah jadi' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400 border border-sky-200 dark:border-sky-700/50',
                                                                    'jasa' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400 border border-purple-200 dark:border-purple-700/50',
                                                                    'aset' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/40 dark:text-slate-400 border border-slate-200 dark:border-slate-700/50',
                                                                    'custom' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-700/50',
                                                                ];
                                                                $typeName = strtolower($res->type->name);
                                                                $defaultColors = [
                                                                    'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-700/50',
                                                                    'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border border-rose-200 dark:border-rose-700/50',
                                                                    'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-700/50',
                                                                    'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400 border border-teal-200 dark:border-teal-700/50',
                                                                    'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-400 border border-fuchsia-200 dark:border-fuchsia-700/50'
                                                                ];
                                                                $typeColor = $colors[$typeName] ?? $defaultColors[$res->type->id % count($defaultColors)];
                                                            @endphp
                                                            <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                                                            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded shadow-sm {{ $typeColor }}">{{ $res->type->name }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                @php 
                                                    $stock = \Illuminate\Support\Facades\DB::table('item_warehouse')->where('item_id', $res->id)->sum('stock') ?? 0;
                                                @endphp
                                                <div class="text-right shrink-0 pl-3">
                                                    <div class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-0.5">Stok</div>
                                                    <div class="text-sm font-black text-zinc-700 dark:text-zinc-300 leading-none">{{ $stock }} <span class="text-[10px] font-medium text-zinc-500">{{ $res->unit->name ?? 'pcs' }}</span></div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <div class="px-4 py-5 text-sm text-zinc-500 text-center flex flex-col items-center justify-center">
                                        <flux:icon.x-mark class="w-6 h-6 text-zinc-300 mb-2" />
                                        <span>Barang tidak ditemukan.</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    {{-- Tombol Galeri Barang --}}
                    <div class="shrink-0 overflow-hidden transition-all duration-300 origin-right"
                         :class="focused ? 'max-w-0 opacity-0 !gap-0 sm:max-w-[300px] sm:opacity-100' : 'max-w-[300px] opacity-100'">
                        <flux:button variant="primary" class="shrink-0 !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600 !text-white" x-data="{ loading: false }" x-on:click="loading = true; Livewire.dispatch('open-gallery', { context: 'purchase' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                            <div class="flex items-center gap-2">
                                <flux:icon.squares-2x2 class="w-4 h-4" x-show="!loading" />
                                <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span class="hidden md:block">Galeri</span>
                            </div>
                        </flux:button>
                    </div>
                </div>
                
                {{-- Cart Header (Total Items & Delete All) --}}
                <div class="flex items-center justify-between px-1 sm:px-2 mb-2 mt-1" x-show="items.length > 0" x-cloak>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Total :</h3>
                        <span class="bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 py-0.5 px-2.5 rounded-full text-xs font-semibold shadow-sm" x-text="items.length + ' Item'"></span>
                    </div>
                    <flux:button size="sm" variant="subtle" icon="trash" @click="$flux.modal('confirm-clear-cart').show()" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 !px-2 !h-8 transition-colors">
                        <span class="hidden sm:inline">All</span>
                    </flux:button>
                </div>

                {{-- Daftar Barang Terpilih (Modern List) --}}
                <div class="flex-1 space-y-4">
                    <template x-for="(item, index) in items" :key="item._cart_id || (item.item_id + '_' + index)">
                        <div class="relative flex flex-col sm:flex-row bg-emerald-50/30 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-800/30 rounded-2xl shadow-sm transition-colors">
                            {{-- Delete Button (Top Left over Image) --}}
                            <div class="absolute top-2 left-2 sm:-top-3 sm:-left-3 z-10" x-show="!item.min_qty" x-cloak>
                                <flux:button variant="primary" size="sm" icon="trash" @click="removeItem(index)" class="!rounded-full shadow-md hover:!bg-red-500 hover:!border-red-500 hover:scale-110 transition-all duration-200" />
                            </div>

                            {{-- Image Container (Top on Mobile, Left on Desktop) --}}
                            <div class="w-full sm:w-32 h-32 sm:h-auto shrink-0 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center relative rounded-t-2xl sm:rounded-none sm:rounded-l-2xl overflow-hidden group">
                                <template x-if="item.custom_attachments && item.custom_attachments.length > 0">
                                    <img :src="'/storage/' + item.custom_attachments[0]" class="w-full h-full object-cover absolute inset-0">
                                </template>
                                <template x-if="!(item.custom_attachments && item.custom_attachments.length > 0) && item.image">
                                    <img :src="'/storage/' + item.image" class="w-full h-full object-cover absolute inset-0">
                                </template>
                                <template x-if="!(item.custom_attachments && item.custom_attachments.length > 0) && !item.image">
                                    <flux:icon.cube class="w-10 h-10 text-zinc-300 dark:text-zinc-600" />
                                </template>
                                
                                {{-- Custom Badge Overlay --}}
                                <template x-if="(item.custom_attributes && item.custom_attributes.length > 0) || (item.custom_attachments && item.custom_attachments.length > 0)">
                                    <div class="absolute bottom-0 left-0 w-full bg-emerald-500/95 text-emerald-50 text-[10px] font-bold text-center py-1 uppercase tracking-widest backdrop-blur-md shadow-sm border-t border-emerald-400/50">
                                        Custom MTO
                                    </div>
                                </template>
                            </div>

                            {{-- Content --}}
                            <div class="flex-1 flex flex-col p-4 sm:p-5 relative min-w-0">
                                {{-- Floating Action Buttons on the Right --}}
                                <div class="absolute top-4 right-4 flex items-center gap-2">
                                    <!-- Tombol Customizer -->
                                    <div>
                                        <flux:button 
                                            size="sm" 
                                            icon="adjustments-horizontal" 
                                            @click="openItemCustomizer(index)" 
                                            title="Sesuaikan (Custom Spesifikasi & Gambar)" 
                                            x-bind:class="(item.custom_attributes && item.custom_attributes.length > 0) || (item.custom_attachments && item.custom_attachments.length > 0) ? '!bg-emerald-500 hover:!bg-emerald-600 !border-emerald-600 !text-white' : 'text-slate-400 hover:text-slate-600'"
                                            x-bind:variant="(item.custom_attributes && item.custom_attributes.length > 0) || (item.custom_attachments && item.custom_attachments.length > 0) ? 'primary' : 'subtle'" 
                                        />
                                    </div>

                                    <div :class="open ? 'z-50' : ''" x-data="{ open: false, placement: 'bottom' }">
                                        <div x-show="item.note" x-cloak>
                                            <flux:button size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" />
                                        </div>
                                        <div x-show="!item.note">
                                            <flux:button variant="subtle" size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="text-slate-400 hover:text-slate-600" />
                                        </div>

                                        <!-- Popover Catatan -->
                                        <div x-show="open" x-cloak class="fixed inset-0 bg-zinc-900/50 z-[50] sm:hidden" x-transition.opacity></div>
                                        <div x-show="open" @click.away="open = false" x-transition 
                                             class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] sm:absolute sm:translate-x-0 sm:translate-y-0 sm:right-0 sm:left-auto sm:w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-5 cursor-auto z-[60]" 
                                             :class="placement === 'top' ? 'sm:bottom-full sm:mb-3 sm:origin-bottom-right' : 'sm:top-full sm:mt-3 sm:origin-top-right'"
                                             style="display: none;">
                                            
                                            <div class="flex justify-between items-center mb-4 gap-2">
                                                <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                <div class="flex gap-1 shrink-0">
                                                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" @click="openItemEditor(index); open = false;" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                </div>
                                            </div>
                                            
                                            <!-- Jika terdeteksi HTML (Rich Text) -->
                                            <div x-show="isRichText" x-cloak class="relative group" @click="openItemEditor(index); open = false;">
                                                <div class="w-full min-h-[6rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-xs prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 cursor-pointer" x-html="item.note">
                                                </div>
                                                <div class="absolute inset-0 bg-black/5 dark:bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 bg-white/90 dark:bg-zinc-800/90 px-2 py-1 rounded shadow-sm">Klik untuk edit full</span>
                                                </div>
                                            </div>

                                            <!-- Quick Note -->
                                            <div x-show="!isRichText" class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-3 shadow-inner border border-zinc-200 dark:border-zinc-700 focus-within:border-zinc-300 focus-within:ring-1 focus-within:ring-zinc-300 transition-colors">
                                                <textarea x-model="item.note" class="w-full bg-transparent border-none focus:border-none focus:ring-0 outline-none focus:outline-none text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 dark:placeholder-zinc-500 min-h-[120px] resize-none p-0" placeholder="Tulis catatan..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Product Info --}}
                                <div class="pr-10 sm:pr-12">
                                    <h4 class="font-bold text-[#1a2b4c] dark:text-zinc-100 text-[14px] sm:text-[15px] leading-snug line-clamp-1 uppercase flex items-center flex-wrap">
                                        <span x-text="item.name"></span>
                                        <template x-if="(item.custom_attributes && item.custom_attributes.length > 0) || (item.custom_attachments && item.custom_attachments.length > 0) || (item.note && item.note.toUpperCase().includes('[CUSTOM]'))">
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-black bg-orange-500 text-white border border-orange-600 px-1.5 py-0.5 rounded shadow-sm relative -top-px ml-2">
                                                <flux:icon.sparkles class="w-2.5 h-2.5 text-white" /> CUSTOM
                                            </span>
                                        </template>
                                    </h4>
                                    <div class="text-[12px] sm:text-[13px] text-zinc-400 font-medium mt-0.5 sm:mt-1 uppercase flex items-center gap-2">
                                        <span x-text="item.code || '-'"></span>
                                    </div>
                                    
                                    {{-- Custom Badges Preview --}}
                                    <div x-show="item.custom_attributes && item.custom_attributes.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                        <template x-for="attr in item.custom_attributes">
                                            <span class="text-[10px] bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-sm" x-text="attr.key + ': ' + attr.value"></span>
                                        </template>
                                    </div>
                                    <div x-show="item.custom_attachments && item.custom_attachments.length > 0" class="mt-1 flex gap-1">
                                        <flux:icon.photo class="w-3 h-3 text-amber-500" /> <span class="text-[10px] text-amber-600" x-text="item.custom_attachments.length + ' Gambar'"></span>
                                    </div>
                                    <div x-show="item.note && (!item.custom_attributes || item.custom_attributes.length === 0)" class="mt-1.5 sm:mt-2 flex items-start gap-1.5 text-[11px] sm:text-[12px] text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.document-text class="w-3 h-3 sm:w-3.5 sm:h-3.5 mt-0.5 shrink-0 text-amber-500" />
                                        <span class="italic line-clamp-1 leading-tight prose prose-xs prose-p:my-0" x-html="item.note"></span>
                                    </div>
                                </div>

                                {{-- Controls Row --}}
                                <div class="mt-3 sm:mt-4 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 pr-8 sm:pr-12">
                                    <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                                        {{-- Editable Price Input (Image 1 style) --}}
                                        <div class="relative flex items-center flex-1 sm:w-40 min-w-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg" :class="open ? 'z-50' : ''" x-data="{ open: false, placement: 'bottom', expanded: false }" x-init="$watch('item.unit_price', () => updateItemSubtotal(index))">
                                            <x-rupiah-input 
                                                x-model="item.unit_price" 
                                                align="center" 
                                                appearance="transparent" 
                                                class="w-full pr-8" 
                                            />
                                            <button type="button" @click="open = !open; if(open) { $wire.price_history = []; $wire.showPriceHistory(item.item_id, item.name); $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) } else { $wire.price_history = []; }" 
                                                    class="absolute right-2.5 transition-colors"
                                                    :class="item.has_history ? 'text-blue-500 hover:text-blue-600' : 'text-zinc-300 hover:text-zinc-500'">
                                                <flux:icon.clock class="w-4 h-4" />
                                            </button>
                                            
                                            {{-- Popover Price History (Livewire) --}}
                                            <div x-show="open" x-cloak class="fixed inset-0 bg-zinc-900/50 z-[50] sm:hidden" x-transition.opacity></div>
                                            <div x-show="open" @click.away="open = false" x-transition 
                                                 class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] sm:absolute sm:translate-x-0 sm:translate-y-0 sm:right-auto sm:left-0 sm:w-[380px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-0 cursor-auto overflow-hidden z-[60] transition-all duration-300" 
                                                 :class="[
                                                    placement === 'top' ? 'sm:bottom-full sm:mb-3 sm:origin-bottom-left' : 'sm:top-full sm:mt-3 sm:origin-top-left',
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
                                                                        <div class="text-[11px] text-zinc-500 font-medium group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors" x-text="(new Date(history.purchase_order?.order_date || Date.now())).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) + ' &bull; ' + (history.purchase_order?.po_number || 'PO-0000')">
                                                                        </div>
                                                                        <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                                                            <flux:icon.building-storefront class="w-3.5 h-3.5 text-zinc-400 group-hover:text-cyan-500 transition-colors" />
                                                                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors truncate max-w-[120px]" x-text="history.purchase_order?.vendor?.name || 'Vendor Tidak Diketahui'"></span>
                                                                            <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                                                            <span class="text-[10px] font-bold text-zinc-500 uppercase group-hover:text-cyan-600/70 dark:group-hover:text-cyan-400/70 transition-colors bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded" x-text="'Beli: ' + history.qty + ' ' + (history.item?.unit?.name || '')"></span>
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
                                            <input type="number" inputmode="numeric" pattern="[0-9]*" x-model.number="item.qty" @input="updateItemSubtotal(index)" @change="validateQty(index)" class="w-10 text-center bg-transparent border-none focus:ring-0 p-0 text-[13px] font-bold text-[#1a2b4c] dark:text-zinc-100" :min="item.min_qty || 1" step="1" />
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
                    
                    <div x-show="items.length === 0" x-cloak class="py-24 text-center flex flex-col items-center justify-center border-2 border-dashed border-emerald-200 dark:border-emerald-800 rounded-3xl bg-emerald-50/50 dark:bg-emerald-900/10">
                        <div class="w-24 h-24 bg-emerald-100 dark:bg-emerald-800/50 rounded-full flex items-center justify-center mb-6 shadow-sm">
                            <flux:icon.building-storefront class="w-12 h-12 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <h3 class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">Buat Pesanan Pembelian</h3>
                        <p class="text-emerald-600 dark:text-emerald-400 mt-2 max-w-md">Daftar belanja ke vendor masih kosong. Cari barang untuk ditambahkan ke daftar pesanan.</p>
                        
                        <div class="mt-8 flex gap-4">
                            <flux:button class="!bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600 !text-white" icon="squares-2x2" x-data="{ loading: false }" x-on:click="loading = true; Livewire.dispatch('open-gallery', { context: 'purchase' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)">Buka Galeri</flux:button>
                            <flux:button @click="setTimeout(() => Array.from(document.querySelectorAll('input')).find(i => i.placeholder && i.placeholder.includes('Ketik')).focus(), 100)" variant="subtle" icon="magnifying-glass">Cari Barang</flux:button>
                        </div>
                    </div>
                    </div>
            </div>
        </div>

        {{-- KOLOM KANAN: Ringkasan Biaya & Tombol (Lebar 4 kolom dari 12) --}}
        <div x-show="items.length > 0" x-cloak class="lg:col-span-4 xl:col-span-4 space-y-6 sticky top-6 pb-20">

            {{-- Step 0: Keranjang Terisi --}}
            <div x-show="step === 0" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex flex-col items-center justify-center text-center space-y-4">
                    <flux:button variant="primary" class="w-full mt-2 !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600" @click="step = 1">
                        Lanjut ke Data Vendor <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                    </flux:button>
                </div>
            </div>

            {{-- Informasi Vendor & Catatan (Step 1) --}}
            <div x-show="step >= 1" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-emerald-50/50 dark:bg-emerald-900/10 p-6 rounded-xl border border-emerald-100 dark:border-emerald-800/30 shadow-sm space-y-6">

                {{-- Pilih Vendor --}}
                <div>
                    <flux:heading size="lg" class="mb-3">Vendor / Supplier</flux:heading>
                    
                    <template x-if="vendor">
                        {{-- Vendor Card Terpilih --}}
                        <div class="group flex items-center gap-4 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                            <template x-if="vendor.image">
                                <div class="relative flex-none rounded-lg w-12 h-12 shrink-0 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
                                    <img x-bind:src="'/storage/' + vendor.image" class="w-full h-full object-cover" />
                                </div>
                            </template>
                            <template x-if="!vendor.image">
                                <div class="relative flex-none flex items-center justify-center font-semibold rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 w-12 h-12 text-lg shrink-0 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                                    <span class="select-none" x-text="vendor.name ? vendor.name.substring(0, 2).toUpperCase() : 'VN'"></span>
                                </div>
                            </template>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base leading-none truncate" x-text="vendor.name"></h3>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 leading-none" x-text="vendor.type || 'Umum'"></span>
                                </div>
                                
                                <div class="mt-2 flex flex-col gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.phone class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                        <span class="truncate" x-text="vendor.phone || 'Belum ada nomor telepon'"></span>
                                    </div>
                                    <template x-if="vendor.province || vendor.city">
                                        <div class="flex items-center gap-1.5">
                                            <flux:icon.map-pin class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                            <span class="truncate lg:text-[6px] xl:text-[10px]" x-text="[vendor.district, vendor.city, vendor.province].filter(Boolean).join(', ')"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            {{-- Tombol Silang Batal Pilih --}}
                            <div class="shrink-0">
                                <flux:button type="button" variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors rounded-full w-8 h-8 flex items-center justify-center p-0" @click="vendor = null; $wire.vendor_id = ''; $wire.selected_vendor = null" tooltip="Ganti Vendor"></flux:button>
                            </div>
                        </div>
                    </template>

                    <template x-if="!vendor">
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
                                                <li @click="vendor = {{ json_encode($v->toArray()) }}; $wire.vendor_id = vendor.id; $wire.vendor_search_query = ''; $wire.show_vendor_suggestions = false;"
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
                            <flux:button variant="primary" class="shrink-0 !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('vendor-gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                                <div class="flex items-center gap-2">
                                    <flux:icon.users class="w-4 h-4" x-show="!loading" />
                                    <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span class="hidden xl:block">Galeri</span>
                                </div>
                            </flux:button>
                        </div>
                    </template>
                </div>

                <flux:separator />

                {{-- Catatan Khusus --}}
                <div x-data="{
                    get isRichText() {
                        const val = $wire.notes || '';
                        return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                    }
                }">
                    <div class="flex justify-between items-center mb-3">
                        <flux:heading size="lg">Catatan Khusus</flux:heading>
                        <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" @click="$dispatch('open-global-editor')" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                    </div>
                    
                    <!-- Jika terdeteksi HTML -->
                    <div x-show="isRichText" x-cloak class="relative group" @click="$dispatch('open-global-editor')">
                        <div class="w-full min-h-[5rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-white dark:bg-zinc-900/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 prose-img:rounded-xl cursor-pointer" x-html="$wire.notes">
                        </div>
                    </div>
                    
                    <!-- Quick Note -->
                    <div x-show="!isRichText">
                        <flux:textarea wire:model="notes" placeholder="Tulis catatan atau instruksi khusus untuk vendor..." rows="3" />
                    </div>
                </div>

                {{-- Tombol Lanjut (Hanya muncul jika di Step 1) --}}
                <div x-show="step === 1" x-transition>
                    <flux:separator class="my-4" />
                    <flux:button variant="primary" class="w-full !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600" @click="step = 2">
                        Lanjut ke Tanggal Order <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                    </flux:button>
                </div>
            </div>

            {{-- Informasi Dokumen (Step 2) --}}
            <div x-show="step >= 2" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-emerald-50/50 dark:bg-emerald-900/10 p-6 rounded-xl border border-emerald-100 dark:border-emerald-800/30 shadow-sm space-y-6">
                
                {{-- Tanggal Order --}}
                <div>
                    <flux:heading size="lg" class="mb-3">Tanggal Order</flux:heading>
                    <flux:input type="date" wire:model="order_date" icon="calendar" class="[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-full" required />
                </div>
                
                <flux:separator />
                
                {{-- Tenggat Waktu --}}
                <div x-data="{
                    days: '',
                    isCalculating: false,
                    init() {
                        this.$watch('$wire.expected_delivery_date', (val) => {
                            if(!this.isCalculating) this.calculateDays();
                        });
                        this.$watch('$wire.order_date', (val) => {
                            if(!this.isCalculating) this.calculateDays();
                        });
                        setTimeout(() => this.calculateDays(), 100);
                    },
                    calculateDate() {
                        this.isCalculating = true;
                        if(this.days === '' || this.days < 0) {
                            $wire.expected_delivery_date = null;
                        } else {
                            let baseDate = new Date($wire.order_date || Date.now());
                            baseDate.setDate(baseDate.getDate() + parseInt(this.days));
                            $wire.expected_delivery_date = baseDate.toISOString().split('T')[0];
                        }
                        setTimeout(() => { this.isCalculating = false; }, 50);
                    },
                    calculateDays() {
                        this.isCalculating = true;
                        if(!$wire.expected_delivery_date || !$wire.order_date) {
                            this.days = '';
                        } else {
                            let start = new Date($wire.order_date);
                            let end = new Date($wire.expected_delivery_date);
                            start.setHours(0,0,0,0);
                            end.setHours(0,0,0,0);
                            let diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24));
                            
                            if (diffDays < 0) {
                                $wire.expected_delivery_date = $wire.order_date;
                                this.days = 0;
                            } else {
                                this.days = diffDays;
                            }
                        }
                        setTimeout(() => { this.isCalculating = false; }, 50);
                    }
                }">
                    <flux:heading size="lg" class="mb-3">Tenggat Waktu Pengerjaan<span class="text-red-500">*</span></flux:heading>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-white dark:bg-zinc-900 shadow-sm">
                        <div class="flex items-center gap-4">
                            <flux:input type="number" inputmode="numeric" pattern="[0-9]*" x-model="days" x-on:input="calculateDate" class="w-24 font-bold text-center" min="0" placeholder="0" />
                            <span class="text-sm text-zinc-500 leading-tight">Hari dari<br>sekarang</span>
                        </div>
                        
                        <div class="flex items-center my-4">
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                            <span class="px-4 text-[11px] font-bold text-slate-400 tracking-widest uppercase">ATAU</span>
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                        </div>
                        
                        <flux:input type="date" wire:model="expected_delivery_date" x-bind:min="$wire.order_date" icon="calendar" class="[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-full" />
                    </div>
                </div>

                {{-- Tombol Lanjut (Hanya muncul jika di Step 2) --}}
                <div x-show="step === 2" x-transition>
                    <flux:separator class="my-4" />
                    <flux:button variant="primary" class="w-full !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600" @click="step = 3">
                        Lanjut ke Ringkasan Biaya <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                    </flux:button>
                </div>
            </div>

            {{-- Ringkasan Biaya (Step 3) --}}
            <div x-show="step >= 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-emerald-50/50 dark:bg-emerald-900/10 p-6 rounded-xl border border-emerald-100 dark:border-emerald-800/30 shadow-sm">
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
                                <input type="number" inputmode="numeric" pattern="[0-9]*" x-ref="newTaxInput" x-model="newTax" @keydown.enter.prevent="addOption" @keydown.escape="showAdd = false" class="w-12 px-1 py-0.5 text-xs rounded bg-white border border-zinc-200 dark:border-zinc-600 dark:bg-zinc-900 text-center focus:outline-none focus:border-zinc-400" placeholder="%" />
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
            <div x-show="step >= 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="sm:col-span-2 md:col-span-1 flex flex-col gap-3">
                <div x-show="grand_total <= 0 || !vendor || items.length === 0" x-cloak class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-3 rounded-xl text-sm border border-red-200 dark:border-red-800 flex items-start gap-2 shadow-sm">
                    <flux:icon.exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
                    <div class="flex flex-col">
                        <span class="font-bold">Tidak dapat menyimpan pesanan:</span>
                        <ul class="list-disc pl-4 mt-1 space-y-0.5 text-xs">
                            <li x-show="!vendor">Vendor/Pemasok belum dipilih.</li>
                            <li x-show="items.length === 0">Keranjang belanja masih kosong.</li>
                            <li x-show="items.length > 0 && grand_total <= 0">Total pesanan tidak boleh 0 (periksa harga atau diskon).</li>
                        </ul>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
                    <div class="flex gap-2 w-full">
                        <flux:button variant="ghost" class="w-1/3" href="{{ route('purchase.orders.kanban') }}" wire:navigate wire:loading.attr="disabled"> Batal </flux:button>
                        <flux:button variant="primary" class="w-2/3 !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-600" @click="submitCart()" x-bind:disabled="isSubmitting || grand_total <= 0 || !vendor || items.length === 0">
                            <span x-show="!isSubmitting" class="flex items-center gap-2"><flux:icon.check class="w-4 h-4" /> Simpan Purchase Order</span>
                            <span x-show="isSubmitting" class="flex items-center gap-2">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Menyimpan...
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Modals Eksternal (Di-load sebagai komponen Livewire terpisah) --}}
    <livewire:global.item-gallery-modal context="purchase" />
    <livewire:global.vendor-gallery-modal />
    <livewire:global.item-form-modal />
    <livewire:global.vendor-form-modal />
    
    {{-- Modal Customizer Barang --}}
    <livewire:global.item-customizer-modal />

    {{-- Modal Konfirmasi Hapus Semua --}}
    <flux:modal name="confirm-clear-cart" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Semua Barang?</flux:heading>
                <flux:subheading>Seluruh item di keranjang akan dihapus. Tindakan ini tidak dapat dibatalkan.</flux:subheading>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" x-on:click="$flux.modal('confirm-clear-cart').close()">Batal</flux:button>
                <flux:button variant="danger" x-on:click="$dispatch('clear-cart'); $flux.modal('confirm-clear-cart').close()">Hapus Semua</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
    const initPurchaseCart = () => {
        Alpine.data('cartSystem', (config) => ({
            step: 0,
            vendor: config.vendor,
            items: @json($items),
            ongkir: {{ (float) ($ongkir ?? 0) }},
            diskon_global: {{ (float) ($diskon_global ?? 0) }},
            pajak_persen: {{ (float) ($pajak_persen ?? 0) }},
            pajak_nominal: {{ (float) ($pajak_nominal ?? 0) }},

            showEditor: false,
            editingNoteIndex: null,

            get subtotal_amount() {
                return this.items.reduce((sum, item) => sum + ((parseInt(item.qty) || 0) * (parseFloat(item.unit_price) || 0)), 0);
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
                item.subtotal = (parseInt(item.qty) || 0) * (parseFloat(item.unit_price) || 0);
                this.calculateTax();
            },

            incrementQty(index) {
                this.items[index].qty = (parseInt(this.items[index].qty) || 0) + 1;
                this.updateItemSubtotal(index);
            },

            decrementQty(index) {
                let current = parseInt(this.items[index].qty) || 0;
                let min = parseInt(this.items[index].min_qty) || 1;
                if (current > min) {
                    this.items[index].qty = current - 1;
                    if (this.items[index].qty < min) this.items[index].qty = min;
                    this.updateItemSubtotal(index);
                }
            },

            validateQty(index) {
                let current = parseInt(this.items[index].qty) || 0;
                let min = parseInt(this.items[index].min_qty) || 1;
                if (current < min) {
                    this.items[index].qty = min;
                } else {
                    this.items[index].qty = current;
                }
                this.updateItemSubtotal(index);
            },

            addItem(newItem, forceNew = false) {
                  let existingIndex = -1;
                  if (!forceNew) {
                      existingIndex = this.items.findIndex(i => {
                          let isSameId = i.item_id == newItem.item_id;
                          let isSameImage = i.image == newItem.image;
                          
                          let normalize = (val) => {
                              if (!val) return "";
                              if (typeof val === 'object' && Object.keys(val).length === 0) return "";
                              return JSON.stringify(val);
                          };
                          
                          let isSameAttrs = normalize(i.custom_attributes) === normalize(newItem.custom_attributes);
                          let isSameAttachs = normalize(i.custom_attachments) === normalize(newItem.custom_attachments);
                          
                          return isSameId && isSameImage && isSameAttrs && isSameAttachs;
                      });
                  }
                  
                  if (existingIndex !== -1) {
                    // Update qty
                    this.items[existingIndex].qty = (parseInt(this.items[existingIndex].qty) || 0) + 1;
                    this.updateItemSubtotal(existingIndex);
                    
                    // Force reactivity and move to top if needed
                    let newItems = [...this.items];
                    if (existingIndex > 0) {
                        let item = newItems.splice(existingIndex, 1)[0];
                        newItems.unshift(item);
                    }
                    this.items = newItems;
                } else {
                    this.items.unshift({
                        _cart_id: Date.now() + Math.random().toString(36).substr(2, 9),
                        id: null,
                        item_id: newItem.item_id,
                        name: newItem.name,
                        code: newItem.code || '0001',
                        qty: 1,
                        unit_price: newItem.unit_price,
                        subtotal: newItem.unit_price,
                        image: newItem.image,
                        note: newItem.note || '',
                        custom_attributes: newItem.custom_attributes || [],
                        custom_attachments: newItem.custom_attachments || [],
                        has_history: newItem.has_history
                    });
                    this.calculateTax();
                }
            },

            removeItem(index) {
                const item = this.items[index];
                this.items.splice(index, 1);
                this.calculateTax();
                window.dispatchEvent(new CustomEvent('item-removed-from-cart', { detail: { item_id: item.item_id } }));
            },

            openItemCustomizer(index) {
                let itemData = {
                    item_id: this.items[index].item_id,
                    name: this.items[index].name,
                    note: this.items[index].note || '',
                    custom_attributes: this.items[index].custom_attributes || [],
                    custom_attachments: this.items[index].custom_attachments || []
                };
                Livewire.dispatch('open-customizer', { index: index, itemData: itemData });
            },

            openItemEditor(index) {
                this.$wire.set('editingNoteIndex', index);
                this.$wire.set('tempNoteContent', this.items[index].note || '');
                this.showEditor = true;
            },

            openGlobalEditor() {
                this.$wire.set('editingNoteIndex', 'global');
                this.$wire.set('tempNoteContent', this.$wire.notes || '');
                this.showEditor = true;
            },

            saveEditor() {
                if (this.$wire.editingNoteIndex === 'global') {
                    this.$wire.set('notes', this.$wire.tempNoteContent);
                } else if (this.$wire.editingNoteIndex !== null) {
                    this.items[this.$wire.editingNoteIndex].note = this.$wire.tempNoteContent;
                }
                this.showEditor = false;
                this.$wire.set('editingNoteIndex', null);
                this.$wire.set('tempNoteContent', '');
            },

            isSubmitting: false,

            async submitCart() {
                if (this.isSubmitting) return;
                this.isSubmitting = true;
                
                // Munculkan global loader seketika
                document.dispatchEvent(new Event('livewire:navigate'));
                
                let success = await this.$wire.saveCart({
                    items: this.items,
                    ongkir: this.ongkir,
                    diskon_global: this.diskon_global,
                    pajak_persen: this.pajak_persen,
                    pajak_nominal: this.pajak_nominal,
                    grand_total: this.grand_total
                });

                if (!success) {
                    this.isSubmitting = false;
                    // Matikan global loader jika gagal validasi dsb
                    document.dispatchEvent(new Event('livewire:navigate-end'));
                }
            },
            
            formatRupiah(number) {
                if (!number) return '0';
                return Math.round(number).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }
        }));
    };

    if (!window.hasRegisteredPurchaseCart) {
        window.hasRegisteredPurchaseCart = true;
        if (window.Alpine) {
            initPurchaseCart();
        } else {
            document.addEventListener('alpine:init', initPurchaseCart);
        }
    }
    
    if (!window.hasSetupPurchaseListeners) {
        window.hasSetupPurchaseListeners = true;
        
        let setupPurchaseListeners = () => {
            Livewire.on('customizer-saved', (data) => {
                let detail = data[0];
                let index = detail.index;
                let cartElement = document.querySelector('[x-data^="cartSystem"]');
                if (cartElement) {
                    let cart = Alpine.$data(cartElement);
                    if (cart && cart.items[index]) {
                        cart.items[index].note = detail.note;
                        cart.items[index].custom_attributes = detail.custom_attributes;
                        cart.items[index].custom_attachments = detail.custom_attachments;
                    }
                }
            });
            
            Livewire.on('add-variant-to-cart', (data) => {
                let detail = data[0];
                let cartElement = document.querySelector('[x-data^="cartSystem"]');
                if (cartElement) {
                    let cart = Alpine.$data(cartElement);
                    if (cart) {
                        cart.addItem(detail.item, true);
                        // The newly added or updated item is now at index 0
                        cart.items[0].custom_attributes = detail.custom_attributes;
                        cart.items[0].custom_attachments = detail.custom_attachments;
                    }
                }
            });
        };

        if (window.Livewire) {
            setupPurchaseListeners();
        } else {
            document.addEventListener('livewire:initialized', setupPurchaseListeners);
        }
    }
    </script>

    {{-- Panel Editor Rich Text --}}
    <x-rich-editor-modal />


    {{-- Template Modal untuk Rich Editor --}}
    <livewire:global.template-modal context="purchase" />
    
    @once
    <style>
        .tox-tinymce { height: 100% !important; width: 100% !important; max-width: 100% !important; border: none !important; }
        .tox-tinymce-aux { z-index: 999999 !important; }
    </style>
    <script>
    document.addEventListener('focusin', function (e) {
        if (e.target.closest('.tox-tinymce-aux, .moxman-window') !== null) {
            e.stopImmediatePropagation();
        }
    });
    </script>
    @endonce

</div>
