<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
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

on(['open-maklon-modal' => function ($orderIds = []) {
    $this->reset(['vendor_id', 'vendor_name', 'phase_type', 'notes', 'costs', 'global_cost']);
    $this->orderIds = $orderIds;
    
    // Inisialisasi struktur array costs agar @entangle di AlpineJS bisa bekerja
    // Harus membuat key untuk mode "grouped" maupun "single"
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        // Inisialisasi untuk mode single
        $this->costs['single_'.$order->id] = null;
        
        // Kumpulkan untuk mode grouped
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = true;
        }
    }
    
    // Inisialisasi untuk mode grouped
    foreach ($groups as $itemId => $val) {
        $this->costs['group_'.$itemId] = null;
    }
    
    // Beritahu browser bahwa data sudah siap dan minta browser membuka modal maklon secara native
    $this->dispatch('maklon-data-loaded');
}]);

$openFromEvent = function (array $orderIds = []) {
    $this->reset(['vendor_id', 'vendor_name', 'phase_type', 'notes', 'costs', 'global_cost']);
    $this->orderIds = $orderIds;
    
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    
    $groups = [];
    foreach ($orders as $order) {
        $this->costs['single_'.$order->id] = null;
        if (!isset($groups[$order->item_id])) {
            $groups[$order->item_id] = true;
        }
    }
    foreach ($groups as $itemId => $val) {
        $this->costs['group_'.$itemId] = null;
    }
};

$openEditor = function($key) {
    $this->editingNoteKey = $key;
    if ($key === 'global') {
        $this->tempNoteContent = $this->notes;
    } else {
        $this->tempNoteContent = $this->item_notes[$key] ?? '';
    }
    $this->dispatch('show-maklon-editor');
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

$save = function () {
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
            'status' => 'ordered',
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
                $finalNotes = "<p><strong>Jasa Vendor Fase:</strong> " . ucfirst($this->phase_type) . "</p>";
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
                    
                    $order->status = 'in_production';
                    $order->phase_type = $this->phase_type;
                    $order->vendor_cost = $orderCost;
                    $order->purchase_order_id = $po->id;
                    if ($this->notes) {
                        $order->notes = $order->notes . "\n[Maklon to " . $vendor->name . "]: " . $this->notes;
                    }
                    $order->save();
                }
            }
        } else {
            foreach ($this->ordersList as $order) {
                $orderCost = $this->costs['single_'.$order->id] ?? 0;
                $costPerUnit = $orderCost / max(1, $order->requested_qty);

                $itemNote = $this->item_notes['single_'.$order->id] ?? '';
                $finalNotes = "<p><strong>Jasa Vendor Fase:</strong> " . ucfirst($this->phase_type) . " (Ref: " . $order->production_req_number . ")</p>";
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

                $order->status = 'in_production';
                $order->phase_type = $this->phase_type;
                $order->vendor_cost = $orderCost;
                $order->purchase_order_id = $po->id;
                if ($this->notes) {
                    $order->notes = $order->notes . "\n[Maklon to " . $vendor->name . "]: " . $this->notes;
                }
                $order->save();
            }
        }
        return $po;
    });

    $this->dispatch('maklon-po-created');
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    
    if ($poData) {
        \Flux::toast('SPK ' . $poData->po_number . ' berhasil dibuat!', variant: 'success');
        $this->dispatch('open-po-detail-modal', poId: $poData->id);
    } else {
        \Flux::toast('Perintah Kerja Vendor berhasil dibuat!', variant: 'success');
    }
};

$handleVendorSelected = function ($vendorId) {
    $vendor = Vendor::find($vendorId);
    if ($vendor) {
        $this->vendor_id = $vendor->id;
        $this->vendor_name = $vendor->name;
        $this->selectedVendorData = $vendor->toArray();
    }
};
?>
<div @vendor-selected.window="$wire.handleVendorSelected($event.detail.vendor ? $event.detail.vendor.id : $event.detail.vendorId); setTimeout(() => { $flux.modal('vendor-gallery-modal').close() }, 50);"
     @maklon-data-loaded.window="$flux.modal('maklon-modal').show()">

<flux:modal name="maklon-modal" class="w-full md:w-[60rem] md:max-w-5xl !p-0 overflow-hidden">
    <div class="relative w-full bg-white dark:bg-zinc-900 flex flex-col {{ $editingNoteKey ? 'h-[85vh] min-h-[600px]' : 'h-[90vh] sm:h-auto sm:max-h-[85vh]' }}">
        
        @if($editingNoteKey)
        <!-- PANEL EDITOR -->
        <div class="flex flex-col w-full h-full bg-white dark:bg-zinc-900 z-50">
            <div class="flex-none px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Editor Catatan</h2>
                </div>
                <button @click="$wire.set('editingNoteKey', null)" class="p-2 text-zinc-400 hover:text-zinc-600 rounded-lg">
                    <flux:icon.x-mark class="w-5 h-5" />
                </button>
            </div>
            <div class="flex-1 p-6" wire:ignore>
                <x-rich-editor wire:model="tempNoteContent" height="100%" />
            </div>
            <div class="flex-none px-6 py-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex justify-end gap-2">
                <flux:button variant="ghost" @click="$wire.set('editingNoteKey', null)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveEditor">Simpan Catatan</flux:button>
            </div>
        </div>
        @else
        <!-- HEADER -->
        <div class="flex-none px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-900/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    <flux:icon.truck class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Proses Maklon SPK</h2>
                    <p class="text-sm text-zinc-500">{{ count($orderIds) }} SPK Terpilih</p>
                </div>
            </div>
            <button @click="$flux.modal('maklon-modal').close()" class="p-2 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg transition-colors">
                <flux:icon.x-mark class="w-5 h-5" />
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 custom-scrollbar">
            <div class="space-y-6">
                <!-- CONTENT GOES HERE -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div x-data="{ localVendor: @entangle('selectedVendorData') }" 
                         @vendor-selected.window="
                            localVendor = $event.detail.vendor;
                            $wire.set('vendor_id', localVendor.id);
                         ">
                        <flux:label>Vendor <span class="text-red-500">*</span></flux:label>
                        
                        <template x-if="localVendor">
                            <div class="mt-2 p-3 border rounded-xl flex items-center gap-3">
                                <!-- Avatar -->
                                <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-zinc-100 flex items-center justify-center font-bold text-zinc-500 text-sm border border-zinc-200">
                                    <template x-if="localVendor.image">
                                        <img :src="'/storage/' + localVendor.image" class="w-full h-full object-cover" />
                                    </template>
                                    <template x-if="!localVendor.image">
                                        <span x-text="localVendor.name.substring(0, 2).toUpperCase()"></span>
                                    </template>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate" x-text="localVendor.name"></div>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 leading-none shrink-0" x-text="localVendor.type"></span>
                                    </div>
                                    <div class="text-[10px] text-zinc-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="flex items-center gap-1 truncate max-w-[120px]">
                                            <flux:icon.phone class="w-3 h-3 shrink-0" /> 
                                            <span x-text="localVendor.phone || '---'"></span>
                                        </span>
                                        <template x-if="localVendor.province || localVendor.city">
                                            <span class="flex items-center gap-1 truncate flex-1" :title="[localVendor.district, localVendor.city, localVendor.province].filter(Boolean).join(', ')">
                                                <flux:icon.map-pin class="w-3 h-3 shrink-0" /> 
                                                <span x-text="[localVendor.district, localVendor.city, localVendor.province].filter(Boolean).join(', ')"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <flux:button size="sm" variant="subtle" @click="$flux.modal('vendor-gallery-modal').show()" class="shrink-0">Ganti</flux:button>
                            </div>
                        </template>

                        <template x-if="!localVendor">
                            <div class="mt-2 p-6 border-2 border-dashed rounded-xl text-center cursor-pointer hover:border-indigo-500 transition-colors" @click="$flux.modal('vendor-gallery-modal').show()">
                                <flux:icon.user-plus class="w-8 h-8 mx-auto text-zinc-400" />
                                <span class="text-sm">Pilih Vendor</span>
                            </div>
                        </template>
                    </div>
                    <div>
                        <flux:label>Tenggat Waktu <span class="text-red-500">*</span></flux:label>
                        <flux:input type="date" wire:model="expected_delivery_date" class="mt-2" />
                    </div>
                </div>

                <div class="border-t pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="md">Item & Biaya</flux:heading>
                        <flux:switch wire:model.live="is_grouped" label="Gabung Item" />
                    </div>

                    <!-- Global Cost Distributor -->
                    <div class="mb-4 bg-indigo-50/50 dark:bg-indigo-900/10 p-4 rounded-xl border border-indigo-100 dark:border-indigo-800/30">
                        <flux:label>Biaya Borongan (Global)</flux:label>
                        <div class="flex gap-2 mt-1">
                            <div class="flex-1">
                                <x-currency-input wire:model="global_cost" placeholder="Masukkan total biaya..." />
                            </div>
                            <flux:button variant="primary" wire:click="distributeGlobalCost" icon="calculator">Distribusikan</flux:button>
                        </div>
                        <p class="text-xs text-zinc-500 mt-2">Biaya akan otomatis dibagi rata (proporsional) ke seluruh item di bawah.</p>
                    </div>
                    
                    @if($this->is_grouped)
                        @foreach($this->groupedOrders as $itemId => $group)
                        <div class="flex items-center gap-4 py-3 border-b">
                            <div class="flex-1">
                                <div class="font-medium">{{ $group['item']->name }}</div>
                                <div class="text-xs text-zinc-500">Qty: {{ $group['total_qty'] }}</div>
                            </div>
                            <div class="w-48">
                                <x-currency-input wire:model="costs.group_{{ $itemId }}" placeholder="Rp 0" />
                            </div>
                            <flux:button size="sm" icon="pencil-square" wire:click="openEditor(`group_{{ $itemId }}`)" />
                        </div>
                        @endforeach
                    @else
                        @foreach($this->ordersList as $order)
                        <div class="flex items-center gap-4 py-3 border-b">
                            <div class="flex-1">
                                <div class="font-medium">{{ $order->item->name }}</div>
                                <div class="text-xs text-zinc-500">Ref: {{ $order->production_req_number }}</div>
                            </div>
                            <div class="w-48">
                                <x-currency-input wire:model="costs.single_{{ $order->id }}" placeholder="Rp 0" />
                            </div>
                            <flux:button size="sm" icon="pencil-square" wire:click="openEditor(`single_{{ $order->id }}`)" />
                        </div>
                        @endforeach
                    @endif
                </div>

                <div class="p-4 bg-zinc-50 rounded-lg">
                    <flux:label>Catatan Tambahan</flux:label>
                    <flux:textarea wire:model="notes" rows="3" class="mt-2" />
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="flex-none px-6 py-4 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex justify-between items-center">
            <flux:button variant="ghost" @click="$flux.modal('maklon-modal').close()">Batal</flux:button>
            <flux:button variant="primary" wire:click="save" class="w-32">
                <span wire:loading.remove wire:target="save">Simpan</span>
                <span wire:loading wire:target="save"><flux:icon.arrow-path class="w-4 h-4 animate-spin" /></span>
            </flux:button>
        </div>
        @endif
    </div>
</flux:modal>

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

@once
<script>
    // Trik khusus untuk Modal Maklon:
    // Karena Maklon menggunakan flux:modal (Top Layer <dialog>), kita perlu
    // "menculik" kontainer menu TinyMCE agar ikut masuk ke dalam dimensi Top Layer ini.
    // Script ini tidak akan merusak rich-editor global.
    document.addEventListener('focusin', function (e) {
        const dialog = e.target.closest('dialog[data-modal="maklon-modal"]');
        if (dialog) {
            const aux = document.getElementById('tinymce-aux-container');
            if (aux && aux.parentNode !== dialog) {
                dialog.appendChild(aux);
            }
        }
    });
</script>
@endonce
</div>
