<?php
use function Livewire\Volt\{state, mount, computed, layout, title, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\DB;

layout('layouts.app');
title('Buat SPK Maklon');

state([
    'orderIds' => [],
    'vendor_id' => '',
    'vendor_name' => '',
    'phase_type' => 'finishing',
    'notes' => '',
    'costs' => [], // array keyed by item_id or order_id
    'global_cost' => null,
    'expected_delivery_date' => '',
    'is_grouped' => true,
    'item_notes' => [],
    'editingNoteKey' => null,
    'tempNoteContent' => '',
    'mediaUploadTemp' => null,
    'mediaUploadTempName' => null,
    'selectedVendorData' => null,
    'show_preview_modal' => false,
]);

$groupedOrders = computed(function () {
    if (empty($this->orderIds)) return collect();
    $orders = ProductionOrder::with('item')->whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = [
                'item' => $order->item,
                'total_qty' => 0,
                'orders' => []
            ];
        }
        $groups[$order->item_id]['total_qty'] += $order->requested_qty;
        $groups[$order->item_id]['orders'][] = $order;
    }
    
    return $groups;
});

$selectedVendor = computed(function () {
    if (!$this->vendor_id) return null;
    return Vendor::find($this->vendor_id);
});

$ordersList = computed(function () {
    if (empty($this->orderIds)) return collect();
    return \Modules\Production\Models\ProductionOrder::with('item')->whereIn('id', $this->orderIds)->get();
});

mount(function () {
    $token = request('token');
    if (!$token) {
        \Flux::toast('Token tidak valid', variant: 'danger');
        return $this->redirectRoute('production.orders', navigate: true);
    }
    
    $orderIds = \Illuminate\Support\Facades\Cache::pull('maklon_create_' . $token);
    if (!$orderIds) {
        \Flux::toast('Sesi pembuatan SPK sudah kadaluarsa', variant: 'danger');
        return $this->redirectRoute('production.orders', navigate: true);
    }
    
    $this->orderIds = $orderIds;
    
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        $this->costs['single_'.$order->id] = null;
        $this->item_notes['single_'.$order->id] = '';
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = true;
        }
    }
    foreach ($groups as $itemId => $val) {
        $this->costs['group_'.$itemId] = null;
        $this->item_notes['group_'.$itemId] = '';
    }
});

$openEditor = function($key, $currentNote = null) {
    $this->editingNoteKey = $key;
    if ($key === 'global') {
        $this->tempNoteContent = $currentNote !== null ? $currentNote : $this->notes;
    } else {
        $this->tempNoteContent = $currentNote !== null ? $currentNote : ($this->item_notes[$key] ?? '');
    }
};

$saveEditor = function() {
    if ($this->editingNoteKey === 'global') {
        $this->notes = $this->tempNoteContent;
    } else {
        $this->item_notes[$this->editingNoteKey] = $this->tempNoteContent;
    }
    $this->editingNoteKey = null;
};

$distributeGlobalCost = function() {
    $global = (float) $this->global_cost;
    if ($global <= 0) return;
    
    $totalQtyAll = 0;
    
    if ($this->is_grouped) {
        foreach ($this->groupedOrders as $group) {
            $totalQtyAll += $group['total_qty'];
        }
        
        if ($totalQtyAll > 0) {
            $costPerUnit = $global / $totalQtyAll;
            $newCosts = $this->costs;
            foreach ($this->groupedOrders as $itemId => $group) {
                $newCosts['group_'.$itemId] = round($costPerUnit * $group['total_qty']);
            }
            $this->costs = $newCosts;
        }
    } else {
        foreach ($this->ordersList as $order) {
            $totalQtyAll += $order->requested_qty;
        }
        
        if ($totalQtyAll > 0) {
            $costPerUnit = $global / $totalQtyAll;
            $newCosts = $this->costs;
            foreach ($this->ordersList as $order) {
                $newCosts['single_'.$order->id] = round($costPerUnit * $order->requested_qty);
            }
            $this->costs = $newCosts;
        }
    }
};

$openPreview = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $validCosts = [];
    if ($this->is_grouped) {
        foreach ($this->groupedOrders as $itemId => $group) {
            $validCosts['group_'.$itemId] = $this->costs['group_'.$itemId] ?? null;
        }
    } else {
        foreach ($this->ordersList as $order) {
            $validCosts['single_'.$order->id] = $this->costs['single_'.$order->id] ?? null;
        }
    }
    $this->costs = $validCosts;

    $this->validate([
        'vendor_id' => 'required',
        'expected_delivery_date' => 'required|date',
        'costs.*' => 'required|numeric|min:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
        'expected_delivery_date.required' => 'Tenggat waktu pengerjaan wajib diisi.',
        'expected_delivery_date.date' => 'Tenggat waktu pengerjaan harus berupa tanggal yang valid.',
        'costs.*.required' => 'Biaya harus diisi.',
        'costs.*.numeric' => 'Biaya tidak valid.'
    ]);

    $this->show_preview_modal = true;
};

$confirmSaveMaklon = function () {
    $poData = DB::transaction(function () {
        $vendor = Vendor::find($this->vendor_id);
        if (!$vendor) return null;

        $nextId = PurchaseOrder::max('id') + 1;
        $poNumber = 'GUNJAS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $totalAmount = array_sum($this->costs);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'vendor_id' => $vendor->id,
            'order_date' => now(),
            'expected_delivery_date' => $this->expected_delivery_date,
            'status' => requires_purchase_approval() ? 'pending_approval' : 'approved',
            'total_amount' => $totalAmount,
            'notes' => "Perintah Kerja Vendor/Jasa. Tenggat Waktu: " . $this->expected_delivery_date . ".\n\n" . $this->notes,
            'created_by' => auth()->id()
        ]);

        if ($this->is_grouped) {
            foreach ($this->groupedOrders as $itemId => $group) {
                $groupCost = $this->costs['group_'.$itemId] ?? 0;
                $groupTotalQty = max(1, $group['total_qty']);
                $costPerUnit = $groupCost / $groupTotalQty;

                $itemNote = $this->item_notes['group_'.$itemId] ?? '';
                $finalNotes = "";
                if (!empty($itemNote)) {
                    $finalNotes .= $itemNote;
                }

                $po->items()->create([
                    'item_id' => $itemId,
                    'quantity' => $groupTotalQty,
                    'unit_price' => $costPerUnit,
                    'subtotal' => $groupCost,
                    'notes' => $finalNotes
                ]);

                foreach ($group['orders'] as $order) {
                    $orderCost = $costPerUnit * $order->requested_qty;
                    
                    $order->status = requires_production_approval() ? 'pending_approval' : 'in_production';
                    $order->phase_type = $this->phase_type;
                    $order->vendor_cost = $orderCost;
                    $order->purchase_order_id = $po->id;
                    if ($this->notes) {
                        $order->notes = $order->notes . "\n[Jasa Luar to " . $vendor->name . "]: " . $this->notes;
                    }
                    $order->save();
                }
            }
        } else {
            foreach ($this->ordersList as $order) {
                $orderCost = $this->costs['single_'.$order->id] ?? 0;
                $costPerUnit = $orderCost / max(1, $order->requested_qty);

                $itemNote = $this->item_notes['single_'.$order->id] ?? '';
                $finalNotes = "";
                if (!empty($itemNote)) {
                    $finalNotes .= $itemNote;
                }

                $po->items()->create([
                    'item_id' => $order->item_id,
                    'quantity' => $order->requested_qty,
                    'unit_price' => $costPerUnit,
                    'subtotal' => $orderCost,
                    'notes' => $finalNotes
                ]);

                $order->status = requires_production_approval() ? 'pending_approval' : 'in_production';
                $order->phase_type = $this->phase_type;
                $order->vendor_cost = $orderCost;
                $order->purchase_order_id = $po->id;
                if ($this->notes) {
                    $order->notes = $order->notes . "\n[Jasa Luar to " . $vendor->name . "]: " . $this->notes;
                }
                $order->save();
            }
        }
        return $po;
    });

    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    
    if ($poData) {
        $approvers = \App\Models\User::withPermissionOrSuperAdmin(['purchase.order.update', 'finance.notifikasi.view'])->get();
        \Illuminate\Support\Facades\Notification::send($approvers, new \App\Notifications\PurchaseOrderWaitingApprovalNotification($poData));

        \Flux::toast('SPK ' . $poData->po_number . ' berhasil dibuat!', variant: 'success');
        session()->flash('new_po_id', $poData->id);
        session()->flash('new_po_number', $poData->po_number);
    } else {
        \Flux::toast('Perintah Kerja Vendor berhasil dibuat!', variant: 'success');
    }
    
    $this->redirectRoute('production.orders', navigate: true);
};
?>
<div x-data="{ showEditor: false }"
     @vendor-selected.window="
        if ($event.detail.vendor) {
            $wire.vendor_id = $event.detail.vendor.id;
            $wire.selectedVendorData = $event.detail.vendor;
        }
        setTimeout(() => { $flux.modal('vendor-gallery-modal').close() }, 50);
     "
     class="xl:max-w-7xl xl:mx-auto py-8 px-4 sm:px-6 lg:px-8">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        {{-- KOLOM KIRI (8 Kolom): DAFTAR BARANG --}}
        <div class="lg:col-span-8 space-y-6">
            {{-- Header Kiri --}}
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    <flux:icon.truck class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Daftar Pekerjaan Vendor</h2>
                    <p class="text-sm text-zinc-500">{{ count($orderIds) }} Surat Perintah Kerja (SPK) Terpilih</p>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <flux:switch wire:model.live="is_grouped" label="Gabung Item (Berdasarkan Produk yang Sama)" />
            </div>

            <div class="space-y-4">
                @if($this->is_grouped)
                    @foreach($this->groupedOrders as $itemId => $group)
                        <div wire:key="group-{{ $itemId }}" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm flex flex-col" x-data="{ open: false, placement: 'bottom', note: @entangle('item_notes.group_'.$itemId) }">
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row gap-4 relative">
                                {{-- Popover Edit Catatan (Absolute Top Right) --}}
                                <div class="absolute top-4 right-4 flex items-center justify-end" :class="open ? 'z-50' : ''">
                                    <div x-show="note" x-cloak>
                                        <flux:button size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" />
                                    </div>
                                    <div x-show="!note">
                                        <flux:button variant="subtle" size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="text-slate-400 hover:text-slate-600" />
                                    </div>
                                    
                                    {{-- Popover Content --}}
                                    <div x-show="open" x-cloak class="fixed inset-0 bg-zinc-900/50 z-[50] sm:hidden" x-transition.opacity></div>
                                    <div x-show="open" @click.away="open = false" x-transition 
                                         class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] sm:absolute sm:translate-x-0 sm:translate-y-0 sm:right-0 sm:left-auto sm:w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-5 cursor-auto z-[60]" 
                                         :class="placement === 'top' ? 'sm:bottom-full sm:mb-2 sm:origin-bottom-right' : 'sm:top-full sm:mt-2 sm:origin-top-right'"
                                         style="display: none;">
                                        <div x-data="{
                                            get isRichText() {
                                                const val = note || '';
                                                return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                                            }
                                        }">
                                            <div class="flex justify-between items-center mb-4 gap-2">
                                                <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                <div class="flex gap-1 shrink-0">
                                                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" @click="$wire.openEditor('group_{{ $itemId }}', note).then(() => { showEditor = true; open = false; })" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                </div>
                                            </div>
                                            
                                            <!-- Jika terdeteksi HTML -->
                                            <div x-show="isRichText" x-cloak class="relative group" @click="$wire.openEditor('group_{{ $itemId }}', note).then(() => { showEditor = true; open = false; })">
                                                <div class="w-full min-h-[6rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-xs prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 cursor-pointer" x-html="note">
                                                </div>
                                                <div class="absolute inset-0 bg-black/5 dark:bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 bg-white/90 dark:bg-zinc-800/90 px-2 py-1 rounded shadow-sm">Klik untuk edit full</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Quick Note -->
                                            <div x-show="!isRichText" class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-3 shadow-inner border border-zinc-200 dark:border-zinc-700 focus-within:border-zinc-300 focus-within:ring-1 focus-within:ring-zinc-300 transition-colors">
                                                <textarea x-model="note" class="w-full bg-transparent border-none focus:border-none focus:ring-0 outline-none focus:outline-none text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 dark:placeholder-zinc-500 min-h-[120px] resize-none p-0" placeholder="Tulis catatan..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Image --}}
                                <div class="w-20 h-20 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700/50">
                                    @if($group['item']->image)
                                        <img src="{{ Storage::url($group['item']->image) }}" class="w-full h-full object-cover rounded-xl" />
                                    @else
                                        <flux:icon.cube class="w-8 h-8 text-zinc-300" />
                                    @endif
                                </div>
                                
                                {{-- Content --}}
                                <div class="flex-1 min-w-0 flex flex-col justify-center pr-8">
                                    <h4 class="font-bold text-zinc-900 dark:text-white text-base truncate">{{ $group['item']->name }}</h4>
                                    <div class="text-sm text-zinc-500 mt-1">Total Kuantitas: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $group['total_qty'] }}</span> {{ $group['item']->unit->name ?? 'pcs' }}</div>
                                    <div class="text-xs text-zinc-400 mt-0.5">Menggabungkan {{ count($group['orders']) }} referensi produksi</div>
                                    
                                    <div x-show="note" x-cloak class="mt-2.5 inline-flex items-start gap-1.5 max-w-full bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1.5 rounded-md border border-amber-200/60 dark:border-amber-900/50">
                                        <flux:icon.document-text class="w-3.5 h-3.5 text-amber-600 dark:text-amber-500 shrink-0 mt-0.5" />
                                        <span class="text-[11px] font-medium text-amber-800 dark:text-amber-300 line-clamp-1 leading-tight prose prose-xs prose-p:my-0" x-html="note"></span>
                                    </div>
                                </div>

                                {{-- Input Cost & Action --}}
                                <div class="flex flex-col sm:items-end justify-center gap-2 shrink-0">
                                    <div class="w-full sm:w-48">
                                        <flux:label class="text-[10px] uppercase tracking-wider !mb-1">Biaya Jasa</flux:label>
                                        <x-currency-input wire:model.live="costs.group_{{ $itemId }}" placeholder="0" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    @foreach($this->ordersList as $order)
                        <div wire:key="single-{{ $order->id }}" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm flex flex-col" x-data="{ open: false, placement: 'bottom', note: @entangle('item_notes.single_'.$order->id) }">
                            <div class="p-4 sm:p-5 flex flex-col sm:flex-row gap-4 relative">
                                {{-- Popover Edit Catatan (Absolute Top Right) --}}
                                <div class="absolute top-4 right-4 flex items-center justify-end" :class="open ? 'z-50' : ''">
                                    <div x-show="note" x-cloak>
                                        <flux:button size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="!bg-amber-500 hover:!bg-amber-600 !border-amber-600 !text-white" />
                                    </div>
                                    <div x-show="!note">
                                        <flux:button variant="subtle" size="sm" icon="pencil-square" @click="open = !open; if(open) { $nextTick(() => { placement = ($el.getBoundingClientRect().bottom > window.innerHeight - 300) ? 'top' : 'bottom' }) }" class="text-slate-400 hover:text-slate-600" />
                                    </div>
                                    
                                    {{-- Popover Content --}}
                                    <div x-show="open" x-cloak class="fixed inset-0 bg-zinc-900/50 z-[50] sm:hidden" x-transition.opacity></div>
                                    <div x-show="open" @click.away="open = false" x-transition 
                                         class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[calc(100vw-2rem)] sm:absolute sm:translate-x-0 sm:translate-y-0 sm:right-0 sm:left-auto sm:w-[320px] bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-2xl p-5 cursor-auto z-[60]" 
                                         :class="placement === 'top' ? 'sm:bottom-full sm:mb-2 sm:origin-bottom-right' : 'sm:top-full sm:mt-2 sm:origin-top-right'"
                                         style="display: none;">
                                        <div x-data="{
                                            get isRichText() {
                                                const val = note || '';
                                                return val.includes('<p>') || val.includes('<br>') || val.includes('<strong>') || val.includes('<em>') || val.includes('<img') || val.includes('<table') || val.includes('<ul') || val.includes('<ol');
                                            }
                                        }">
                                            <div class="flex justify-between items-center mb-4 gap-2">
                                                <h3 class="text-[11px] font-bold text-slate-400 tracking-wider uppercase">CATATAN ITEM</h3>
                                                <div class="flex gap-1 shrink-0">
                                                    <flux:button size="xs" variant="subtle" icon="arrows-pointing-out" class="!px-2 h-7" @click="$wire.openEditor('single_{{ $order->id }}', note).then(() => { showEditor = true; open = false; })" title="Buka Editor Lengkap">Editor Lengkap</flux:button>
                                                </div>
                                            </div>
                                            
                                            <!-- Jika terdeteksi HTML -->
                                            <div x-show="isRichText" x-cloak class="relative group" @click="$wire.openEditor('single_{{ $order->id }}', note).then(() => { showEditor = true; open = false; })">
                                                <div class="w-full min-h-[6rem] max-h-[12rem] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 bg-zinc-50 dark:bg-zinc-800/50 text-xs prose prose-sm max-w-none text-zinc-800 dark:text-zinc-200 cursor-pointer" x-html="note">
                                                </div>
                                                <div class="absolute inset-0 bg-black/5 dark:bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-lg">
                                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 bg-white/90 dark:bg-zinc-800/90 px-2 py-1 rounded shadow-sm">Klik untuk edit full</span>
                                                </div>
                                            </div>
                                            
                                            <!-- Quick Note -->
                                            <div x-show="!isRichText" class="bg-slate-50 dark:bg-zinc-800 rounded-xl p-3 shadow-inner border border-zinc-200 dark:border-zinc-700 focus-within:border-zinc-300 focus-within:ring-1 focus-within:ring-zinc-300 transition-colors">
                                                <textarea x-model="note" class="w-full bg-transparent border-none focus:border-none focus:ring-0 outline-none focus:outline-none text-sm text-slate-700 dark:text-zinc-300 placeholder-slate-400 dark:placeholder-zinc-500 min-h-[120px] resize-none p-0" placeholder="Tulis catatan..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Image --}}
                                <div class="w-20 h-20 rounded-xl bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700/50">
                                    @if($order->item->image)
                                        <img src="{{ Storage::url($order->item->image) }}" class="w-full h-full object-cover rounded-xl" />
                                    @else
                                        <flux:icon.cube class="w-8 h-8 text-zinc-300" />
                                    @endif
                                </div>
                                
                                {{-- Content --}}
                                <div class="flex-1 min-w-0 flex flex-col justify-center pr-8">
                                    <h4 class="font-bold text-zinc-900 dark:text-white text-base truncate">{{ $order->item->name }}</h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:bg-zinc-700 font-mono border border-zinc-200 dark:border-zinc-700">{{ $order->production_req_number }}</span>
                                    </div>
                                    <div class="text-sm text-zinc-500 mt-1.5">Kuantitas: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $order->requested_qty }}</span></div>
                                    
                                    <div x-show="note" x-cloak class="mt-2.5 inline-flex items-start gap-1.5 max-w-full bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1.5 rounded-md border border-amber-200/60 dark:border-amber-900/50">
                                        <flux:icon.document-text class="w-3.5 h-3.5 text-amber-600 dark:text-amber-500 shrink-0 mt-0.5" />
                                        <span class="text-[11px] font-medium text-amber-800 dark:text-amber-300 line-clamp-1 leading-tight prose prose-xs prose-p:my-0" x-html="note"></span>
                                    </div>
                                </div>

                                {{-- Input Cost & Action --}}
                                <div class="flex flex-col sm:items-end justify-center gap-2 shrink-0">
                                    <div class="w-full sm:w-48">
                                        <flux:label class="text-[10px] uppercase tracking-wider !mb-1">Biaya Jasa</flux:label>
                                        <x-currency-input wire:model.live="costs.single_{{ $order->id }}" placeholder="0" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
                
                {{-- Catatan SPK Global --}}
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-5 mt-8">
                    <flux:label class="font-bold text-lg mb-2">Catatan Internal / Dokumen SPK</flux:label>
                    <flux:textarea wire:model="notes" rows="3" placeholder="Tulis catatan yang akan tampil di dokumen fisik SPK (Bukan untuk vendor)..." />
                </div>
            </div>
        </div>

        {{-- KOLOM KANAN (4 Kolom): PANEL STICKY --}}
        <div class="lg:col-span-4 lg:sticky lg:top-6 space-y-6">
            
            {{-- Vendor Card --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-5" x-data="{ localVendor: @entangle('selectedVendorData') }">
                <flux:heading size="md" class="mb-4">Informasi Vendor <span class="text-red-500">*</span></flux:heading>
                
                <template x-if="localVendor">
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/50 flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full overflow-hidden shrink-0 bg-white shadow-sm flex items-center justify-center font-bold text-zinc-500 text-lg border border-zinc-200">
                                <template x-if="localVendor.image">
                                    <img :src="'/storage/' + localVendor.image" class="w-full h-full object-cover" />
                                </template>
                                <template x-if="!localVendor.image">
                                    <span x-text="localVendor.name.substring(0, 2).toUpperCase()"></span>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate text-lg" x-text="localVendor.name"></div>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 inline-block mt-1" x-text="localVendor.type"></span>
                            </div>
                        </div>
                        <div class="text-xs text-zinc-500 space-y-1.5 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                            <span class="flex items-center gap-2">
                                <flux:icon.phone class="w-3.5 h-3.5 shrink-0" /> 
                                <span x-text="localVendor.phone || '---'"></span>
                            </span>
                            <template x-if="localVendor.province || localVendor.city">
                                <span class="flex items-start gap-2">
                                    <flux:icon.map-pin class="w-3.5 h-3.5 shrink-0 mt-0.5" /> 
                                    <span x-text="[localVendor.district, localVendor.city, localVendor.province].filter(Boolean).join(', ')" class="leading-snug"></span>
                                </span>
                            </template>
                        </div>
                        <flux:button size="sm" variant="subtle" @click="$flux.modal('vendor-gallery-modal').show()" class="w-full mt-1">Ganti Vendor</flux:button>
                    </div>
                </template>

                <template x-if="!localVendor">
                    <div class="p-6 bg-zinc-50 dark:bg-zinc-800/50 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-xl text-center cursor-pointer hover:border-indigo-500 hover:bg-indigo-50/50 transition-colors" @click="$flux.modal('vendor-gallery-modal').show()">
                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-zinc-200">
                            <flux:icon.user-plus class="w-5 h-5 text-zinc-400" />
                        </div>
                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">Pilih Vendor Jasa Luar</h3>
                        <p class="text-[11px] text-zinc-500 mt-1">Klik untuk mencari dari database</p>
                    </div>
                </template>
                <flux:error name="vendor_id" class="mt-2" />
            </div>

            {{-- Pengaturan SPK --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-5">
                <flux:heading size="md" class="mb-4">Pengaturan SPK</flux:heading>
                
                <div class="space-y-4">
                    <div>
                        <flux:label>Tenggat Waktu Selesai <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="expected_delivery_date" class="mt-1" />
                        <flux:error name="expected_delivery_date" class="mt-1" />
                    </div>
                    
                    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:label class="!mb-1 text-[11px] uppercase tracking-wider text-zinc-500">Kalkulator Biaya Borongan</flux:label>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <x-currency-input wire:model="global_cost" placeholder="Rp 0" />
                            </div>
                            <flux:button variant="primary" wire:click="distributeGlobalCost" icon="calculator">Bagi</flux:button>
                        </div>
                        <p class="text-[10px] text-zinc-500 mt-1.5 leading-tight">Masukkan total ongkos global, lalu klik "Bagi" untuk mendistribusikan merata ke semua item di sebelah kiri secara proporsional.</p>
                    </div>
                </div>
            </div>

            {{-- Grand Total & Action --}}
            <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/30 rounded-2xl shadow-sm p-6">
                <div class="text-sm font-semibold text-indigo-900 dark:text-indigo-300 mb-1">Grand Total Biaya Jasa</div>
                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mb-6 flex items-baseline gap-1">
                    <span class="text-lg">Rp</span> <span x-text="new Intl.NumberFormat('id-ID').format({{ array_sum(array_filter($this->costs, 'is_numeric')) }})"></span>
                </div>
                
                <div class="flex flex-col gap-3">
                    <flux:button variant="primary" class="w-full text-base py-4 font-bold h-auto" icon="document-check" wire:click="openPreview">
                        Simpan & Buat SPK
                    </flux:button>
                    <flux:button variant="ghost" class="w-full" href="{{ route('production.orders') }}" wire:navigate>Batal & Kembali</flux:button>
                </div>
            </div>
            
        </div>
    </div>
    
    {{-- PREVIEW MODAL (A4 Document Style) --}}
    <flux:modal wire:model="show_preview_modal" class="min-w-[50rem] max-w-4xl p-0">
        <div class="bg-zinc-100 dark:bg-zinc-900 rounded-xl overflow-hidden">
            {{-- Header Action Bar --}}
            <div class="bg-zinc-800 dark:bg-zinc-950 text-white p-4 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg">Mode Pratinjau Dokumen</h3>
                    <p class="text-xs text-zinc-400">Tampilan ini mensimulasikan hasil cetak SPK fisik.</p>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" class="text-white hover:bg-zinc-700" wire:click="$set('show_preview_modal', false)">Kembali Edit</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" wire:click="confirmSaveMaklon">Konfirmasi & Terbitkan SPK</flux:button>
                </div>
            </div>
            
            {{-- A4 Paper Simulation --}}
            <div class="p-6 md:p-8 max-h-[75vh] overflow-y-auto custom-scrollbar bg-zinc-200 dark:bg-black/50">
                <div class="flex flex-col gap-8 items-center pb-8">
                    {{-- HALAMAN 1: SUMMARY --}}
                    <div class="bg-white dark:bg-white w-full shadow-xl border border-zinc-300 p-8 md:p-12 text-zinc-900 font-sans shrink-0 flex flex-col relative" style="max-width: 210mm; min-height: 297mm;">
                        
                        {{-- Document Header --}}
                        <div class="flex justify-between items-start border-b-2 border-zinc-900 pb-6 mb-6">
                            <div>
                                <div class="text-2xl font-black uppercase tracking-wider text-zinc-900">Guna Jati</div>
                                <div class="text-sm text-zinc-600 mt-1">Jl. Raya Industri No. 123, Jepara<br>Jawa Tengah, Indonesia</div>
                            </div>
                            <div class="text-right">
                                <h1 class="text-3xl font-black text-zinc-900 tracking-tight uppercase">SPK <span class="text-md normal-case">(Surat perintah kerja)</span></h1>
                                <div class="text-zinc-600 font-medium mt-1">Draft Dokumen / Menunggu Terbit</div>
                            </div>
                        </div>
                        
                        {{-- Meta Info --}}
                        <div class="flex justify-between mb-8">
                            <div class="w-1/2">
                                <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-2">Ditujukan Kepada (Vendor):</h3>
                                <div class="font-bold text-xl text-zinc-900">{{ $this->selectedVendor->name ?? '-' }}</div>
                                @if($this->selectedVendorData && !empty($this->selectedVendorData['phone']))
                                    <div class="text-sm text-zinc-600 mt-1">Telp: {{ $this->selectedVendorData['phone'] }}</div>
                                @endif
                                @if($this->selectedVendorData && !empty($this->selectedVendorData['district']))
                                    <div class="text-sm text-zinc-600">{{ $this->selectedVendorData['district'] ?? '' }}, {{ $this->selectedVendorData['city'] ?? '' }}</div>
                                @endif
                            </div>
                            <div class="w-1/3 space-y-2">
                                <div class="flex justify-between border-b border-dashed border-zinc-300 pb-1">
                                    <span class="text-zinc-500">Tanggal Terbit:</span>
                                    <span class="font-medium text-zinc-900">{{ now()->format('d M Y') }}</span>
                                </div>
                                <div class="flex justify-between border-b border-dashed border-zinc-300 pb-1">
                                    <span class="text-zinc-500">Tenggat Waktu:</span>
                                    <span class="font-bold text-red-600">{{ $expected_delivery_date ? \Carbon\Carbon::parse($expected_delivery_date)->format('d M Y') : '-' }}</span>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Items Table (Summary Only) --}}
                        <table class="w-full text-left border-collapse mb-6">
                            <thead>
                                <tr class="bg-zinc-100 border-y-2 border-zinc-900 text-zinc-900 font-bold">
                                    <th class="p-3 w-12 text-center">No</th>
                                    <th class="p-3">Nama Barang</th>
                                    <th class="p-3 text-center w-20">Qty</th>
                                    <th class="p-3 text-right w-32">Harga Satuan</th>
                                    <th class="p-3 text-right w-40">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200">
                                @php $i = 1; @endphp
                                @if($is_grouped)
                                    @foreach($this->groupedOrders as $itemId => $group)
                                        @php 
                                            $subtotal = $this->costs['group_'.$itemId] ?? 0; 
                                            $qty = max(1, $group['total_qty']);
                                            $unitPrice = $subtotal / $qty;
                                        @endphp
                                        <tr>
                                            <td class="p-3 text-center text-zinc-900">{{ $i++ }}</td>
                                            <td class="p-3 font-bold text-zinc-900">{{ $group['item']->name }}</td>
                                            <td class="p-3 text-center text-zinc-900">{{ $qty }} {{ $group['item']->unit->name ?? 'pcs' }}</td>
                                            <td class="p-3 text-right text-zinc-900">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-bold text-zinc-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    @foreach($this->ordersList as $order)
                                        @php 
                                            $subtotal = $this->costs['single_'.$order->id] ?? 0; 
                                            $qty = max(1, $order->requested_qty);
                                            $unitPrice = $subtotal / $qty;
                                        @endphp
                                        <tr>
                                            <td class="p-3 text-center text-zinc-900">{{ $i++ }}</td>
                                            <td class="p-3 font-bold text-zinc-900">{{ $order->item->name }}</td>
                                            <td class="p-3 text-center text-zinc-900">{{ $qty }}</td>
                                            <td class="p-3 text-right text-zinc-900">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                                            <td class="p-3 text-right font-bold text-zinc-900">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-zinc-900">
                                    <td colspan="4" class="p-3 text-right font-bold uppercase tracking-wider text-sm text-zinc-900">Grand Total Biaya Jasa:</td>
                                    <td class="p-3 text-right text-xl font-black bg-zinc-100 text-zinc-900">Rp {{ number_format(array_sum(array_filter($this->costs, 'is_numeric')), 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        {{-- Global Notes --}}
                        @if(!empty($this->notes))
                            <div class="mb-12">
                                <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-2 border-b border-zinc-200 pb-1">Catatan Tambahan SPK:</h3>
                                <div class="text-sm prose prose-sm max-w-none text-zinc-800 leading-relaxed bg-zinc-50 p-4 border border-zinc-200 rounded-lg">
                                    {!! nl2br(strip_tags($this->notes)) !!}
                                </div>
                            </div>
                        @endif
                        
                        {{-- Signatures --}}
                        <div class="grid grid-cols-3 gap-8 mt-auto pt-8 text-center text-sm text-zinc-900">
                            <div>
                                <div class="mb-16 font-medium text-zinc-600">Dibuat Oleh,</div>
                                <div class="border-b border-zinc-400 mx-8 pb-1 font-bold">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-zinc-500 mt-1">Admin Produksi</div>
                            </div>
                            <div>
                                <div class="mb-16 font-medium text-zinc-600">Disetujui Oleh,</div>
                                <div class="border-b border-zinc-400 mx-8 pb-1 font-bold">(............................)</div>
                                <div class="text-xs text-zinc-500 mt-1">Manajer Produksi</div>
                            </div>
                            <div>
                                <div class="mb-16 font-medium text-zinc-600">Diterima Oleh Vendor,</div>
                                <div class="border-b border-zinc-400 mx-8 pb-1 font-bold">{{ $this->selectedVendor->name ?? '(............................)' }}</div>
                                <div class="text-xs text-zinc-500 mt-1">Tanda Tangan & Cap</div>
                            </div>
                        </div>
                    </div>

                    {{-- HALAMAN 2: LAMPIRAN DETAIL CATATAN --}}
                    @php
                        $hasAnyItemNotes = false;
                        if($is_grouped) {
                            foreach($this->groupedOrders as $itemId => $group) {
                                if(!empty($this->item_notes['group_'.$itemId])) $hasAnyItemNotes = true;
                            }
                        } else {
                            foreach($this->ordersList as $order) {
                                if(!empty($this->item_notes['single_'.$order->id])) $hasAnyItemNotes = true;
                            }
                        }
                    @endphp

                    @if($hasAnyItemNotes)
                    <div class="bg-white dark:bg-white w-full shadow-xl border border-zinc-300 p-8 md:p-12 text-zinc-900 font-sans shrink-0" style="max-width: 210mm; min-height: 297mm;">
                        <div class="text-center border-b-2 border-zinc-900 pb-6 mb-8">
                            <h2 class="text-2xl font-black uppercase tracking-wider text-zinc-900">Lampiran SPK Vendor</h2>
                            <div class="text-zinc-600 mt-1">Rincian Pekerjaan & Catatan Khusus Instruksi Vendor</div>
                        </div>

                        <div class="space-y-8">
                            @if($is_grouped)
                                @foreach($this->groupedOrders as $itemId => $group)
                                    @php $note = $this->item_notes['group_'.$itemId] ?? ''; @endphp
                                    @if(!empty($note))
                                        <div class="border border-zinc-200 rounded-lg overflow-hidden flex flex-col page-break-inside-avoid">
                                            <div class="bg-zinc-100 px-4 py-3 border-b border-zinc-200 flex justify-between items-center">
                                                <h4 class="font-bold text-zinc-900 text-lg">{{ $group['item']->name }}</h4>
                                                <span class="text-sm font-medium bg-white px-2 py-1 rounded border border-zinc-200 shadow-sm">Kuantitas: {{ max(1, $group['total_qty']) }} {{ $group['item']->unit->name ?? 'pcs' }}</span>
                                            </div>
                                            <div class="flex flex-col sm:flex-row p-6 gap-6 bg-white">
                                                @if($group['item']->image)
                                                    <div class="w-full sm:w-1/3 shrink-0">
                                                        <img src="{{ asset('storage/' . $group['item']->image) }}" class="w-full h-auto object-contain rounded-lg border border-zinc-200 shadow-sm" alt="{{ $group['item']->name }}">
                                                    </div>
                                                @else
                                                    <div class="w-full sm:w-1/3 shrink-0 flex items-center justify-center bg-zinc-50 border border-zinc-200 rounded-lg min-h-[150px]">
                                                        <div class="text-center text-zinc-400">
                                                            <flux:icon.photo class="w-8 h-8 mx-auto mb-2" />
                                                            <span class="text-xs">Tidak ada gambar</span>
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="w-full sm:w-2/3 prose prose-sm max-w-none text-zinc-800 leading-relaxed">
                                                    <h5 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3 border-b border-zinc-100 pb-2">Instruksi Khusus Vendor:</h5>
                                                    {!! nl2br(strip_tags($note)) !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                @foreach($this->ordersList as $order)
                                    @php $note = $this->item_notes['single_'.$order->id] ?? ''; @endphp
                                    @if(!empty($note))
                                        <div class="border border-zinc-200 rounded-lg overflow-hidden flex flex-col page-break-inside-avoid">
                                            <div class="bg-zinc-100 px-4 py-3 border-b border-zinc-200 flex justify-between items-center">
                                                <h4 class="font-bold text-zinc-900 text-lg">{{ $order->item->name }}</h4>
                                                <span class="text-sm font-medium bg-white px-2 py-1 rounded border border-zinc-200 shadow-sm">Kuantitas: {{ max(1, $order->requested_qty) }}</span>
                                            </div>
                                            <div class="flex flex-col sm:flex-row p-6 gap-6 bg-white">
                                                @if($order->item->image)
                                                    <div class="w-full sm:w-1/3 shrink-0">
                                                        <img src="{{ asset('storage/' . $order->item->image) }}" class="w-full h-auto object-contain rounded-lg border border-zinc-200 shadow-sm" alt="{{ $order->item->name }}">
                                                    </div>
                                                @else
                                                    <div class="w-full sm:w-1/3 shrink-0 flex items-center justify-center bg-zinc-50 border border-zinc-200 rounded-lg min-h-[150px]">
                                                        <div class="text-center text-zinc-400">
                                                            <flux:icon.photo class="w-8 h-8 mx-auto mb-2" />
                                                            <span class="text-xs">Tidak ada gambar</span>
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="w-full sm:w-2/3 prose prose-sm max-w-none text-zinc-800 leading-relaxed">
                                                    <h5 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3 border-b border-zinc-100 pb-2">Instruksi Khusus Vendor:</h5>
                                                    {!! nl2br(strip_tags($note)) !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>
    
    {{-- Panel Editor Rich Text --}}
    <x-rich-editor-modal
        title="Editor Lengkap"
        subtitle="Instruksi Khusus Vendor"
        wireModel="tempNoteContent"
        showVariable="showEditor"
        onSave="$wire.saveEditor().then(() => { showEditor = false; })"
        onCancel="showEditor = false"
    />
    
    <livewire:global.template-modal context="production" />
    <livewire:global.vendor-gallery-modal />
    <livewire:global.vendor-form-modal />
    
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
