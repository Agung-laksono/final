<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount, updated};
use Modules\Sales\Models\quotation;
use Modules\Sales\Models\quotationItem;
use Modules\Sales\Models\Customer;
use Modules\Inventory\Models\Item;
use Illuminate\Support\Str;

layout('layouts.app');
title('Form Pembuatan Penawaran Harga');

state([
    'quotation_id' => null,
    'quotation_number' => '',
    'customer_id' => '',
    'customer_name' => '',
    'customer_phone' => '',
    'quotation_date' => date('Y-m-d'),
    'shipping_fee' => 0,
    'discount' => 0,
    'tax_percent' => 0,
    'tax' => 0,
    'status' => 'draft',
    'valid_until' => null,
    'notes' => '',
    
    'items' => [], // array of ['id' => null, 'item_id' => id, 'name' => name, 'qty' => 1, 'unit_price' => price, 'subtotal' => price]
    
    'search_query' => '',
    'show_suggestions' => false,

    'customer_search_query' => '',
    'show_customer_suggestions' => false,
    'selected_customer' => null,
    
    'price_history' => [],
    'history_item_name' => '',
    'history_item_id' => null,
    'history_limit' => 5,
    'has_more_history' => false,
    
    'detail_po' => null,
    
    'tempNoteContent' => '',
    'editingNoteIndex' => null,
    'showEditor' => false,
    
    
]);

mount(function ($id = null) {
    if ($id) {
        // --- KEAMANAN RELOAD & REFERER (Flash Token) ---
        if (!session()->has('allow_edit_so_' . $id)) {
            abort(403, 'Akses ditolak. Halaman ini hanya dapat diakses langsung melalui tombol Sesuaikan di Kanban Sales.');
        }

        $po = Quotation::with(['items.item', 'customer'])->findOrFail($id);
        
        // --- KEAMANAN EDIT SO ---
        // 1. Validasi Status: Hanya SO berstatus 'draft' yang boleh diedit
        if ($po->status !== 'draft') {
            abort(403, 'Pesanan yang sudah diproses atau disetujui tidak dapat diubah.');
        }

        // 2. Validasi Kepemilikan: Staf Sales biasa hanya boleh mengedit SO buatannya sendiri
        if (auth()->user()->hasAnyRole(['Sales', 'Staf Sales']) && !auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager'])) {
            if ($po->created_by !== auth()->id()) {
                abort(403, 'Anda tidak diizinkan mengedit pesanan milik sales lain.');
            }
        }

        $this->quotation_id = $po->id;
        $this->quotation_number = $po->quotation_number;
        $this->customer_id = $po->customer_id;
        $this->customer_name = $po->customer_name;
        $this->customer_phone = $po->customer_phone;
        $this->quotation_date = $po->quotation_date;
        $this->shipping_fee = $po->shipping_fee ?? 0;
        $this->discount = $po->discount ?? 0;
        $this->tax = $po->pajak ?? 0;
        $this->status = $po->status;
        $this->valid_until = $po->valid_until;
        $this->notes = $po->notes ?? '';
        
        if ($po->customer) {
            $this->selected_customer = $po->customer->toArray();
        } elseif ($po->customer_name) {
            $this->selected_customer = [
                'id' => null,
                'name' => $po->customer_name,
                'phone' => $po->customer_phone,
                'type' => 'Pelanggan Baru (Belum Disimpan)'
            ];
        }
        
        // Coba hitung tax_percent dari tax jika ada
        // Ini estimasi, karena kita tidak menyimpan persentase secara eksplisit
        $sub = $po->items->sum('subtotal') + $this->shipping_fee - $this->discount;
        if ($sub > 0 && $this->tax > 0) {
            $this->tax_percent = round(($this->tax / $sub) * 100);
        }

        foreach ($po->items as $detail) {
            $hasHistory = \Modules\Sales\Models\QuotationItem::where('item_id', $detail->item_id)
                ->whereHas('quotation', function($q) {
                    $q->where('status', '!=', 'draft');
                })->exists();

            $this->items[] = [
                'id' => $detail->id,
                'item_id' => $detail->item_id,
                'name' => $detail->item->name ?? 'Unknown',
                'qty' => $detail->qty,
                'unit_price' => $detail->unit_price,
                'subtotal' => $detail->subtotal,
                'image' => $detail->item->image ?? null,
                'note' => $detail->notes ?? '', // Hydrate notes from DB
                'has_history' => $hasHistory,
            ];
        }
    } else {
        $this->quotation_number = ''; // Will be generated on save

    }
});

// Computed search results remain in Livewire

$customerSearchResults = computed(function () {
    if (strlen($this->customer_search_query) < 2) return [];
    return Customer::where('name', 'like', '%' . $this->customer_search_query . '%')
               ->orWhere('phone', 'like', '%' . $this->customer_search_query . '%')
               ->take(5)->get();
});





$clearCustomer = function () {
    $this->selected_customer = null;
    $this->customer_id = '';
};

$quickCreateCustomer = function () {
    $name = trim($this->customer_search_query);
    if (strlen($name) < 2) return;
    
    // Jangan simpan ke database pelanggan asli
    $this->customer_id = null;
    $this->customer_name = $name;
    // customer_phone is already bound
    
    $this->selected_customer = [
        'id' => null,
        'name' => $name,
        'phone' => $this->customer_phone,
        'type' => 'Pelanggan Baru (Belum Disimpan)'
    ];
    
    $this->customer_search_query = '';
    $this->show_customer_suggestions = false;
    
    $this->dispatch('customer-selected', customer: $this->selected_customer);
    
    \Flux::toast('Menggunakan nama pelanggan sementara.', 'success');
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
    $baseQuery = \Modules\Sales\Models\QuotationItem::where('item_id', $this->history_item_id)
        ->whereHas('quotation', function($q) {
            $q->where('status', '!=', 'draft');
        });

    $this->has_more_history = $baseQuery->count() > $this->history_limit;

    $this->price_history = \Modules\Sales\Models\QuotationItem::with(['quotation.customer', 'item.unit'])
        ->where('item_id', $this->history_item_id)
        ->whereHas('quotation', function($q) {
            $q->where('status', '!=', 'draft');
        })
        ->get()
        ->sortByDesc(fn($poi) => $poi->quotation->quotation_date ?? '')
        ->take($this->history_limit)
        ->values()
        ->toArray();
};

$viewPoDetail = function ($poId) {
    $po = \Modules\Sales\Models\Quotation::with(['customer', 'items.item.unit'])->find($poId);
    if ($po) {
        $this->detail_po = $po->toArray();
    }
};

$saveCart = function ($cartData) {
    // --- KEAMANAN SIMPAN EDIT ---
    if ($this->quotation_id) {
        $poCheck = Quotation::find($this->quotation_id);
        if (!$poCheck || $poCheck->status !== 'draft') {
            abort(403, 'Pesanan yang sudah diproses atau disetujui tidak dapat diubah.');
        }
        
        if (auth()->user()->hasAnyRole(['Sales', 'Staf Sales']) && !auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager'])) {
            if ($poCheck->created_by !== auth()->id()) {
                abort(403, 'Anda tidak diizinkan mengedit pesanan milik sales lain.');
            }
        }
    }

    $this->items = $cartData['items'] ?? [];
    $this->shipping_fee = $cartData['shipping_fee'] ?? 0;
    $this->discount = $cartData['discount'] ?? 0;
    $this->tax_percent = $cartData['tax_percent'] ?? 0;
    $this->tax = $cartData['tax'] ?? 0;

    if (!$this->quotation_id) {
        $this->quotation_number = ''; // Let model boot method handle it or we can leave it empty
    }

    $this->validate([
        'customer_id' => 'nullable|exists:customers,id',
        'customer_name' => 'nullable|string|max:255',
        'customer_phone' => 'nullable|string|max:255',
        'quotation_date' => 'required|date',
        'items' => 'required|array|min:1',
        'items.*.qty' => 'required|integer|min:1',
        'items.*.unit_price' => 'required|numeric|min:0',
    ]);
    
    if (!$this->customer_id && !$this->customer_name) {
        $this->addError('customer_id', 'Pelanggan harus dipilih atau diisi.');
        return;
    }



    // Recalculate grand total server-side
    $subtotal = collect($this->items)->sum('subtotal');
    $grandTotal = $subtotal + (float)$this->shipping_fee - (float)$this->discount + (float)$this->tax;

    $hasCustomItems = collect($this->items)->contains(function ($item) {
        return (!empty($item['custom_attributes']) || !empty($item['custom_attachments']));
    });

    if ($hasCustomItems && !str_contains($this->notes, '[CUSTOM]')) {
        $this->notes = '[CUSTOM] ' . $this->notes;
    }

    $data = [
        'quotation_number' => $this->quotation_number,
        'customer_id' => $this->customer_id,
        'customer_name' => $this->customer_name,
        'customer_phone' => $this->customer_phone,
        'quotation_date' => $this->quotation_date,
        'status' => $this->status,
        'shipping_fee' => $this->shipping_fee,
        'discount' => $this->discount,
        'tax' => $this->tax,
        'total_amount' => $grandTotal,
                'packing_fee' => 0,
                'payment_status' => 'unpaid',
        'valid_until' => $this->valid_until,
        'notes' => $this->notes,
    ];

    $isNew = !$this->quotation_id;

    if ($isNew) {
        $data['created_by'] = auth()->id();
        $data['brand_id'] = auth()->user()->brand_id;
    }

    $po = Quotation::updateOrCreate(
        ['id' => $this->quotation_id],
        $data
    );

    // Hapus item lama (jika edit) yang tidak ada di keranjang lagi
    $currentItemIds = collect($this->items)->pluck('id')->filter()->toArray();
    QuotationItem::where('quotation_id', $po->id)
                     ->whereNotIn('id', $currentItemIds)
                     ->delete();

    // Simpan items
    foreach ($this->items as $item) {
        $poi = QuotationItem::updateOrCreate(
            ['id' => $item['id'] ?? null],
            [
                'quotation_id' => $po->id,
                'item_id' => $item['item_id'],
                'qty' => $item['qty'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['note'] ?? null, // Simpan ke DB
                'custom_attributes' => $item['custom_attributes'] ?? null,
                'custom_attachments' => $item['custom_attachments'] ?? null,
            ]
        );
    }

    if ($isNew && $po->status === 'draft') {
        $recipients = \App\Models\User::permission('sales.notifikasi.view')
            ->orWhereHas('roles', function($q) { $q->where('name', 'Super Admin'); })
            ->get();
            
        // Filter out the creator if they are also recipient
        $recipients = $recipients->filter(fn($u) => $u->id !== auth()->id());
        
        \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\QuotationWaitingApprovalNotification($po, auth()->user()));

        // Push Notification via Beams ke semua staf yang perlu approve
        $creatorName = auth()->user()->name;
        $soNumber    = $po->quotation_number;
        $total       = 'Rp ' . number_format($po->total_amount, 0, ',', '.');
        try {
            $beams = new \App\Services\BeamsService();
            $beams->sendToAll(
                "📋 SO Baru Butuh Persetujuan",
                "{$creatorName} membuat {$soNumber} senilai {$total}",
                ['so_id' => $po->id, 'quotation_number' => $soNumber],
                '/sales/orders'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[Beams] Gagal kirim notif SO baru: ' . $e->getMessage());
        }
    }

    if (!$isNew) {
        // Cek jika yang mengedit bukan pembuat SO
        if ($po->created_by && $po->created_by !== auth()->id()) {
            $creator = \App\Models\User::find($po->created_by);
            if ($creator) {
                $creator->notify(new \App\Notifications\quotationRevisedNotification($po, auth()->user()));
            }
        }
    }

    \App\Events\KanbanUpdated::safeDispatch('quotation');
    Flux::toast('Penawaran berhasil disimpan!', 'success');
    
    if ($isNew) {
        session()->flash('new_quotation_id', $po->id);
        session()->flash('new_quotation_number', $po->quotation_number);
    }
    
    $this->redirect(route('sales.quotations.index'), navigate: true);
    
    return true;
};
?>

<div class="xl:max-w-7xl xl:mx-auto" x-data="cartSystem({ customer: @entangle('selected_customer') })" 
     @clear-cart.window="items = []; calculateTax(); window.dispatchEvent(new CustomEvent('cart-cleared'));"
     @item-selected.window="addItem($event.detail.item)" 
     @customer-selected.window="customer = $event.detail.customer; $wire.customer_id = customer.id"
     @open-item-editor.window="openItemEditor($event.detail.index)"
     @open-global-editor.window="openGlobalEditor()">
    
    <div x-data="{ showBanner: localStorage.getItem('hideQuotationBanner') !== 'true', hideBanner(permanently) { this.showBanner = false; if (permanently) { localStorage.setItem('hideQuotationBanner', 'true'); } } }" x-show="showBanner" x-transition class="relative mb-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-xl p-4 sm:p-6 shadow-sm">
        <div class="absolute top-4 right-4"><flux:dropdown position="bottom-end"><flux:button variant="subtle" size="sm" icon="x-mark" class="!px-2 text-amber-500 hover:text-amber-700 hover:bg-amber-100 dark:hover:bg-amber-900/50" /><flux:menu><flux:menu.item @click="hideBanner(false)" icon="eye-slash">Tutup saja</flux:menu.item><flux:menu.item @click="hideBanner(true)" icon="no-symbol">Jangan pernah tampilkan lagi</flux:menu.item></flux:menu></flux:dropdown></div><h1 class="text-2xl font-black text-amber-900 dark:text-amber-100 flex items-center gap-3">
            <flux:icon.document-text class="w-8 h-8 text-amber-600" />
            PENGAJUAN PENAWARAN HARGA (QUOTATION)
        </h1>
        <p class="mt-2 text-amber-700 dark:text-amber-300 text-sm">Gunakan form ini untuk membuat draf penawaran harga kepada pelanggan. Penawaran yang disetujui nantinya dapat dikonversi menjadi pesanan (Sales Order).</p>
    </div>

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
             
             @if ($errors->any())
                 <div class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-4 rounded-xl text-sm border border-red-200 dark:border-red-800">
                     <div class="font-bold flex items-center gap-2 mb-1">
                         <flux:icon.exclamation-triangle class="w-4.5 h-4.5 shrink-0" />
                         Gagal Menyimpan:
                     </div>
                     <ul class="list-disc pl-5 mt-1 space-y-0.5">
                         @foreach ($errors->all() as $error)
                             <li>{{ $error }}</li>
                         @endforeach
                     </ul>
                 </div>
             @endif
             
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
                                            <li @click="addItem({ item_id: {{ $res->id }}, name: '{{ addslashes($res->name) }}', code: '{{ $res->code ?? '0001' }}', unit_price: {{ $res->selling_price ?? 0 }}, image: '{{ $res->image }}' }); $wire.search_query = ''; $wire.show_suggestions = false; window.playSelectSound?.('ting');"
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
                        <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white shrink-0" x-data="{ loading: false }" x-on:click="loading = true; Livewire.dispatch('open-gallery', { context: 'sales' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
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
                        <div class="relative flex flex-col sm:flex-row bg-amber-50/30 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-800/30 rounded-2xl shadow-sm transition-colors">
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
                                        {{-- Tombol Edit (Amber jika ada catatan, Primary jika kosong) --}}
                                        <div x-show="item.note" x-cloak>
                                            <flux:button size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" />
                                        </div>
                                        <div x-show="!item.note">
                                            <flux:button variant="subtle" size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="text-slate-400 hover:text-slate-600" />
                                        </div>
                                        
                                        {{-- Popover Quick Note / Rich Editor --}}
                                        <div x-show="open" x-cloak class="fixed inset-0 bg-zinc-900/50 z-[50] sm:hidden" x-transition.opacity></div>
                                        <div x-show="open" @click.away="open = false" x-transition 
                                             class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] sm:absolute sm:translate-x-0 sm:translate-y-0 sm:right-0 sm:left-auto sm:w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-5 cursor-auto z-[60]" 
                                             :class="placement === 'top' ? 'sm:bottom-full sm:mb-3 sm:origin-bottom-right' : 'sm:top-full sm:mt-3 sm:origin-top-right'"
                                             style="display: none;">
                                            <div x-data="{
                                                get isRichText() {
                                                    const val = item.note || '';
                                                    return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                                                }
                                            }">
                                                <div class="flex justify-between items-center mb-4 gap-2">
                                                    <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                    <div class="flex gap-1 shrink-0">
                                                        <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" @click="openItemEditor(index); open = false;" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                    </div>
                                                </div>
                                                
                                                <!-- Jika terdeteksi HTML -->
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
                                </div>

                                {{-- Product Info --}}
                                <div class="pr-10 sm:pr-12">
                                    <h4 class="font-bold text-[#1a2b4c] dark:text-zinc-100 text-[14px] sm:text-[15px] leading-snug line-clamp-1 uppercase flex items-center gap-1.5 flex-wrap">
                                        <span x-text="item.name"></span>
                                        <template x-if="(item.custom_attributes && item.custom_attributes.length > 0) || (item.custom_attachments && item.custom_attachments.length > 0)">
                                            <span class="inline-flex items-center gap-0.5 text-[9px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 px-1.5 py-0.5 rounded shadow-sm">
                                                <flux:icon.sparkles class="w-2.5 h-2.5 text-emerald-600" /> CUSTOM
                                            </span>
                                        </template>
                                    </h4>
                                    <div class="text-[12px] sm:text-[13px] text-zinc-400 font-medium mt-0.5 sm:mt-1 uppercase" x-text="item.code || '0001'"></div>
                                    
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
                                            <button type="button" @click="open = !open; if(open) { $wire.showPriceHistory(item.item_id, item.name); $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" 
                                                    class="absolute right-2.5 transition-colors"
                                                    :class="item.has_history ? 'text-amber-500 hover:text-amber-600' : 'text-zinc-300 hover:text-zinc-500'">
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
                                                                        <div class="text-[11px] text-zinc-500 font-medium group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors" x-text="(new Date(history.quotation?.quotation_date || Date.now())).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) + ' &bull; ' + (history.quotation?.quotation_number || 'INV-0000')">
                                                                        </div>
                                                                        <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                                                            <flux:icon.building-storefront class="w-3.5 h-3.5 text-zinc-400 group-hover:text-cyan-500 transition-colors" />
                                                                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 group-hover:text-cyan-700 dark:group-hover:text-cyan-400 transition-colors truncate max-w-[120px]" x-text="history.quotation?.customer?.name || 'Customer Tidak Diketahui'"></span>
                                                                            <span class="text-zinc-300 dark:text-zinc-700">&bull;</span>
                                                                            <span class="text-[10px] font-bold text-zinc-500 uppercase group-hover:text-cyan-600/70 dark:group-hover:text-cyan-400/70 transition-colors bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded" x-text="'Beli: ' + history.quantity + ' ' + (history.item?.unit?.name || '')"></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-right flex items-center gap-3">
                                                                        <div class="font-bold text-zinc-700 text-sm group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">
                                                                            <span x-text="'Rp' + formatRupiah(history.unit_price)"></span>
                                                                            <span class="text-[10px] text-zinc-400 font-medium lowercase group-hover:text-cyan-500/80 transition-colors" x-text="'/' + (history.item?.unit?.name || 'unit')"></span>
                                                                        </div>
                                                                        <button type="button" @click.stop="showDetail = !showDetail; if(showDetail && (!$wire.detail_po || $wire.detail_po.id !== history.quotation_id)) $wire.viewPoDetail(history.quotation_id)" 
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
                                                                    Memuat isi Sales Order...
                                                                </div>
                                                                
                                                                <div wire:loading.remove wire:target="viewPoDetail">
                                                                    <template x-if="$wire.detail_po && $wire.detail_po.id === history.quotation_id">
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
                                            <button type="button" @click="decrementQty(index)" class="w-8 h-full flex items-center justify-center text-zinc-400 hover:text-amber-600">-</button>
                                            <input type="number" x-model.number="item.qty" @input="updateItemSubtotal(index)" @change="validateQty(index)" class="w-10 text-center bg-transparent border-none focus:ring-0 p-0 text-[13px] font-bold text-[#1a2b4c] dark:text-zinc-100" :min="item.min_qty || 1" step="1" />
                                            <button type="button" @click="incrementQty(index)" class="w-8 h-full flex items-center justify-center text-zinc-400 hover:text-amber-600">+</button>
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
                    
                    <div x-show="items.length === 0" x-cloak class="py-24 text-center flex flex-col items-center justify-center border-2 border-dashed border-amber-200 dark:border-amber-800 rounded-3xl bg-amber-50/50 dark:bg-amber-900/10">
                        <div class="w-24 h-24 bg-amber-100 dark:bg-amber-800/50 rounded-full flex items-center justify-center mb-6 shadow-sm">
                            <flux:icon.shopping-cart class="w-12 h-12 text-amber-600 dark:text-amber-400" />
                        </div>
                        <h3 class="text-2xl font-bold text-amber-900 dark:text-amber-100">Buat Penawaran Harga Baru</h3>
                        <p class="text-amber-600 dark:text-amber-400 mt-2 max-w-md">Keranjang penawaran masih kosong. Silakan cari barang atau pilih dari galeri untuk memulai transaksi.</p>
                        
                        <div class="mt-8 flex gap-4">
                            <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" icon="squares-2x2" x-data="{ loading: false }" x-on:click="loading = true; Livewire.dispatch('open-gallery', { context: 'sales' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)">Buka Galeri</flux:button>
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
                    <div class="flex gap-2 w-full mt-2">
                        <flux:button variant="subtle" class="w-1/3" href="{{ route('sales.quotations.index') }}" wire:navigate>Batal</flux:button>
                        <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white flex-1" @click="step = 1">
                            Lanjut ke Data Pelanggan <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Informasi Pelanggan & Catatan (Step 1) --}}
            <div x-show="step >= 1" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-amber-50/50 dark:bg-amber-900/10 p-6 rounded-xl border border-amber-100 dark:border-amber-800/30 shadow-sm space-y-6">

                {{-- Pilih Customer --}}
                <div x-data="{ showManualInput: false }">
                    <div class="flex items-center justify-between mb-3">
                        <flux:heading size="lg">Customer / Affiliate</flux:heading>
                        <flux:button size="sm" variant="subtle" class="!px-2 h-7" @click="showManualInput = !showManualInput" x-show="!customer">
                            <span x-show="!showManualInput"><flux:icon.plus class="w-3 h-3 inline-block mr-1" />Baru</span>
                            <span x-show="showManualInput">Batal</span>
                        </flux:button>
                    </div>
                    
                    <template x-if="customer">
                        {{-- Customer Card Terpilih --}}
                        <div class="group flex items-center gap-4 p-3 rounded-xl border border-cyan-200 bg-cyan-50/50 dark:border-cyan-900/50 dark:bg-cyan-900/20 transition-all">
                            <template x-if="customer.image">
                                <div class="relative flex-none rounded-lg w-12 h-12 shrink-0 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50 overflow-hidden">
                                    <img x-bind:src="'/storage/' + customer.image" class="w-full h-full object-cover" />
                                </div>
                            </template>
                            <template x-if="!customer.image">
                                <div class="relative flex-none flex items-center justify-center font-semibold rounded-lg bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 w-12 h-12 text-lg shrink-0 shadow-sm border border-zinc-200/50 dark:border-zinc-700/50">
                                    <span class="select-none" x-text="customer.name ? customer.name.substring(0, 2).toUpperCase() : 'CS'"></span>
                                </div>
                            </template>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-base leading-none truncate" x-text="customer.name"></h3>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-cyan-100 text-cyan-700 dark:bg-cyan-900/50 dark:text-cyan-300 leading-none" x-text="customer.type || 'Umum'"></span>
                                </div>
                                
                                <div class="mt-2 flex flex-col gap-y-1.5 text-xs text-zinc-600 dark:text-zinc-400">
                                    <div class="flex items-center gap-1.5">
                                        <flux:icon.phone class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                        <span class="truncate" x-text="customer.phone || 'Belum ada nomor telepon'"></span>
                                    </div>
                                    <template x-if="customer.province || customer.city">
                                        <div class="flex items-center gap-1.5">
                                            <flux:icon.map-pin class="w-3.5 h-3.5 shrink-0 text-zinc-400" />
                                            <span class="truncate text-[4px] lg:text-[6px] xl:text-[10px]" x-text="[customer.district, customer.city, customer.province].filter(Boolean).join(', ')"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            
                            {{-- Tombol Edit & Silang Batal Pilih --}}
                            <div class="shrink-0 flex items-center gap-1">
                                <template x-if="customer.id">
                                    <flux:button type="button" variant="subtle" size="sm" icon="pencil-square" class="text-zinc-400 hover:text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors rounded-full w-8 h-8 flex items-center justify-center p-0" @click="$dispatch('open-customer-modal', { id: customer.id })" tooltip="Edit Customer"></flux:button>
                                </template>
                                <flux:button type="button" variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors rounded-full w-8 h-8 flex items-center justify-center p-0" @click="customer = null; $wire.customer_id = ''; $wire.selected_customer = null" tooltip="Ganti Customer"></flux:button>
                            </div>
                        </div>
                    </template>

                    <template x-if="!customer">
                        <div>
                            {{-- Customer Search & Gallery Button (Khusus Database) --}}
                            <div x-show="!showManualInput" class="flex items-end gap-2 relative">
                                <div class="flex-1 relative" x-data="{ focused: false }" @click.outside="focused = false">
                                    <flux:input 
                                        wire:model.live.debounce.300ms="customer_search_query" 
                                        @focus="focused = true; $wire.set('show_customer_suggestions', true)"
                                        icon="building-storefront" 
                                        placeholder="Cari customer..." />
                                    
                                    {{-- Dropdown Customer Suggestion --}}
                                    <div x-show="focused && $wire.show_customer_suggestions && $wire.customer_search_query.length >= 2" 
                                         x-cloak
                                         class="absolute z-20 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                                        @if(count($this->customerSearchResults) > 0)
                                            <ul class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-64 overflow-y-auto">
                                                @foreach($this->customerSearchResults as $v)
                                                    <li @click="customer = {{ json_encode($v->toArray()) }}; $wire.customer_id = customer.id; $wire.customer_search_query = ''; $wire.show_customer_suggestions = false;"
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
                                            <div class="px-4 py-6 text-center text-zinc-500 dark:text-zinc-400">
                                                <flux:icon.user class="w-8 h-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                                                <p class="text-sm">Pelanggan tidak ditemukan.</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                {{-- Tombol Galeri Customer --}}
                                <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white shrink-0" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('customer-gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                                    <flux:icon.users class="w-4 h-4 mr-2" />
                                    <span class="hidden sm:inline">Galeri</span>
                                </flux:button>
                            </div>

                            {{-- Manual Input untuk Pelanggan Sementara --}}
                            <div x-show="showManualInput" x-cloak class="flex flex-col gap-2 relative">
                                <flux:input 
                                    wire:model="customer_search_query" 
                                    icon="user" 
                                    placeholder="Nama pelanggan baru..." />
                                <div class="flex gap-2 w-full">
                                    <div class="flex-1">
                                        <flux:input 
                                            wire:model="customer_phone" 
                                            icon="phone" 
                                            placeholder="Nomor HP (opsional)..." />
                                    </div>
                                    <flux:button variant="primary" class="shrink-0" wire:click="quickCreateCustomer" @click="if($wire.customer_search_query.length >= 2) { showManualInput = false; }">
                                        Simpan
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <flux:separator />

                {{-- Catatan Tambahan --}}
                <div x-data="{
                    get isRichText() {
                        const val = $wire.notes || '';
                        return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                    }
                }">
                    <div class="flex justify-between items-center mb-3">
                        <flux:heading size="lg">Catatan Khusus</flux:heading>
                        <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" @click="$dispatch('open-global-editor')" class="!px-2 h-7" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                    </div>
                    
                    <!-- Jika terdeteksi HTML -->
                    <div x-show="isRichText" x-cloak class="relative group" @click="$dispatch('open-global-editor')">
                        <div class="w-full min-h-[5rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-white dark:bg-zinc-900/50 text-sm prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 prose-img:rounded-xl cursor-pointer" x-html="$wire.notes">
                        </div>
                    </div>
                    
                    <!-- Quick Note -->
                    <div x-show="!isRichText">
                        <flux:textarea wire:model="notes" rows="3" placeholder="Tulis catatan atau instruksi khusus untuk pesanan ini..." />
                    </div>
                </div>

                {{-- Tombol Lanjut (Hanya muncul jika di Step 1) --}}
                <div x-show="step === 1" x-transition>
                    <flux:separator class="my-4" />
                    <div class="flex gap-2 w-full">
                        <flux:button variant="subtle" class="w-1/3" href="{{ route('sales.quotations.index') }}" wire:navigate>Batal</flux:button>
                        <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white flex-1" @click="step = 2">
                            Lanjut ke Tanggal Penawaran <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Informasi Dokumen (Step 2) --}}
            <div x-show="step >= 2" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-amber-50/50 dark:bg-amber-900/10 p-6 rounded-xl border border-amber-100 dark:border-amber-800/30 shadow-sm space-y-6">
                
                {{-- Tanggal Penawaran --}}
                <div>
                    <flux:heading size="lg" class="mb-3">Tanggal Penawaran</flux:heading>
                    <flux:input type="date" wire:model="quotation_date" icon="calendar" class="[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-full" required />
                </div>
                
                <flux:separator />
                
                {{-- Masa Berlaku Penawaran (Tenggat) --}}
                <div x-data="{
                    days: '',
                    isCalculating: false,
                    init() {
                        this.$watch('$wire.valid_until', (val) => {
                            if(!this.isCalculating) this.calculateDays();
                        });
                        this.$watch('$wire.quotation_date', (val) => {
                            if(!this.isCalculating) this.calculateDays();
                        });
                        setTimeout(() => this.calculateDays(), 100);
                    },
                    calculateDate() {
                        this.isCalculating = true;
                        if(this.days === '' || this.days < 0) {
                            $wire.valid_until = null;
                        } else {
                            let baseDate = new Date($wire.quotation_date || Date.now());
                            baseDate.setDate(baseDate.getDate() + parseInt(this.days));
                            $wire.valid_until = baseDate.toISOString().split('T')[0];
                        }
                        setTimeout(() => { this.isCalculating = false; }, 50);
                    },
                    calculateDays() {
                        this.isCalculating = true;
                        if(!$wire.valid_until || !$wire.quotation_date) {
                            this.days = '';
                        } else {
                            let start = new Date($wire.quotation_date);
                            let end = new Date($wire.valid_until);
                            start.setHours(0,0,0,0);
                            end.setHours(0,0,0,0);
                            let diffDays = Math.round((end - start) / (1000 * 60 * 60 * 24));
                            
                            if (diffDays < 0) {
                                $wire.valid_until = $wire.quotation_date;
                                this.days = 0;
                            } else {
                                this.days = diffDays;
                            }
                        }
                        setTimeout(() => { this.isCalculating = false; }, 50);
                    }
                }">
                    <flux:heading size="lg">Masa Berlaku Penawaran (Tenggat)<span class="text-red-500">*</span></flux:heading>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">Tentukan batas waktu (kadaluarsa) berlakunya penawaran dan harga ini.</p>
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-white dark:bg-zinc-900 shadow-sm">
                        <div class="flex items-center gap-4">
                            <flux:input type="number" x-model="days" x-on:input="calculateDate" class="w-24 font-bold text-center" min="0" placeholder="0" />
                            <span class="text-sm text-zinc-500 leading-tight">Hari dari<br>sekarang</span>
                        </div>
                        
                        <div class="flex items-center my-4">
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                            <span class="px-4 text-[11px] font-bold text-slate-400 tracking-widest uppercase">ATAU</span>
                            <div class="flex-1 border-t border-zinc-200 dark:border-zinc-700"></div>
                        </div>
                        
                        <flux:input type="date" wire:model="valid_until" x-bind:min="$wire.quotation_date" icon="calendar" class="[&::-webkit-calendar-picker-indicator]:opacity-0 [&::-webkit-calendar-picker-indicator]:absolute [&::-webkit-calendar-picker-indicator]:w-full" />
                    </div>
                </div>

                {{-- Tombol Lanjut (Hanya muncul jika di Step 2) --}}
                <div x-show="step === 2" x-transition>
                    <flux:separator class="my-4" />
                    <div class="flex gap-2 w-full">
                        <flux:button variant="subtle" class="w-1/3" href="{{ route('sales.quotations.index') }}" wire:navigate>Batal</flux:button>
                        <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white flex-1" @click="step = 3">
                            Lanjut ke Ringkasan Biaya <flux:icon.arrow-right class="w-4 h-4 ml-2" />
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Ringkasan Biaya (Step 3) --}}
            <div x-show="step >= 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="bg-amber-50/50 dark:bg-amber-900/10 p-6 rounded-xl border border-amber-100 dark:border-amber-800/30 shadow-sm">
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
                        <x-rupiah-input x-model="discount" @input="calculateTax()" />
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
                                if ($data.tax_percent == val) {
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
                                        :class="[$data.tax_percent == opt ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-white text-zinc-700 border border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700', deleteMode && opt !== 0 ? 'animate-pulse ring-2 ring-red-400' : '']"
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
            <div x-show="step >= 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="sm:col-span-2 md:col-span-1 bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm flex items-center justify-between">
                <div class="flex gap-2 w-full">
                    <flux:button variant="ghost" class="w-1/3" href="{{ route('sales.quotations.index') }}" wire:loading.attr="disabled" wire:navigate> Batal </flux:button>
                    <flux:button class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white w-2/3" @click="submitCart()" x-bind:disabled="isSubmitting">
                        <span x-show="!isSubmitting" class="flex items-center gap-2"><flux:icon.check class="w-4 h-4" /> Simpan Sales Order</span>
                        <span x-show="isSubmitting" class="flex items-center gap-2">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyimpan...
                        </span>
                    </flux:button>
                </div>
            </div>
        </div>

    {{-- Panel Editor Rich Text --}}
    <x-rich-editor-modal />

    {{-- Modals Eksternal (Di-load sebagai komponen Livewire terpisah) --}}
    <livewire:global.item-gallery-modal context="sales" />
    <livewire:global.customer-gallery-modal />
    
    {{-- Modal Tambah Barang dari komponen global --}}
    <livewire:global.item-form-modal />
    <livewire:customer.customer-form-modal />
    
    {{-- Modal Customizer Barang --}}
    <livewire:global.item-customizer-modal />

    {{-- Template Modal untuk Rich Editor --}}
    <livewire:global.template-modal context="sales" />

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
    const initSalesCart = () => {
        Alpine.data('cartSystem', (config) => ({
            step: 0,
            customer: config.customer,
            items: @json($items),
            ongkir: {{ (float) ($ongkir ?? 0) }},
            discount: {{ (float) ($discount ?? 0) }},
            tax_percent: {{ (float) ($tax_percent ?? 0) }},
            tax: {{ (float) ($tax ?? 0) }},
            
            showEditor: false,
            editingNoteIndex: null,
            
            openGlobalEditor() {
                this.editingNoteIndex = 'global';
                // Set Livewire tempNoteContent to current notes value, so TinyMCE loads it
                this.$wire.set('tempNoteContent', this.$wire.notes || '');
                this.showEditor = true;
            },
            
            openItemEditor(index) {
                this.editingNoteIndex = index;
                this.$wire.set('tempNoteContent', this.items[index].note || '');
                this.showEditor = true;
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
            
            saveEditor() {
                // TinyMCE writes to $wire.tempNoteContent via wire:model entangle
                // Read back the final content from Livewire
                const content = this.$wire.tempNoteContent || '';
                if (this.editingNoteIndex === 'global') {
                    this.$wire.set('notes', content);
                } else {
                    this.items[this.editingNoteIndex].note = content;
                }
                this.showEditor = false;
            },

            get subtotal_amount() {
                return this.items.reduce((sum, item) => sum + ((parseInt(item.qty) || 0) * (parseFloat(item.unit_price) || 0)), 0);
            },

            get grand_total() {
                return this.subtotal_amount + (parseFloat(this.ongkir) || 0) - (parseFloat(this.discount) || 0) + (parseFloat(this.tax) || 0);
            },

            calculateTax() {
                if (this.tax_percent > 0) {
                    let sub = this.subtotal_amount + (parseFloat(this.ongkir) || 0) - (parseFloat(this.discount) || 0);
                    this.tax = (this.tax_percent / 100) * sub;
                }
            },

            setPajakPersen(persen) {
                this.tax_percent = persen;
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

            isSubmitting: false,

            async submitCart() {
                if (this.isSubmitting) return;
                this.isSubmitting = true;
                
                // Munculkan global loader seketika
                document.dispatchEvent(new Event('livewire:navigate'));
                
                let success = await this.$wire.saveCart({
                    items: this.items,
                    ongkir: this.ongkir,
                    discount: this.discount,
                    tax_percent: this.tax_percent,
                    tax: this.tax,
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

    if (!window.hasRegisteredSalesCart) {
        window.hasRegisteredSalesCart = true;
        if (window.Alpine) {
            initSalesCart();
        } else {
            document.addEventListener('alpine:init', initSalesCart);
        }
    }
    
    if (!window.hasSetupSalesListeners) {
        window.hasSetupSalesListeners = true;
        
        let setupSalesListeners = () => {
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
                        // The newly added item is now at index 0
                        cart.items[0].custom_attributes = detail.custom_attributes;
                        cart.items[0].custom_attachments = detail.custom_attachments;
                    }
                }
            });
        };

        if (window.Livewire) {
            setupSalesListeners();
        } else {
            document.addEventListener('livewire:initialized', setupSalesListeners);
        }
    }
    </script>
    
    @once
    <style>
        /* TinyMCE: pastikan editor mengisi container flex sepenuhnya */
        .tox-tinymce { 
            height: 100% !important; 
            width: 100% !important; 
            max-width: 100% !important;
            border: none !important; 
        }
        
        /* Fix z-index popup TinyMCE (table menu, dll) agar tidak tertutup modal */
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










