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
    'costs' => [], // array keyed by item_id
    'global_cost' => null,
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

on(['open-maklon-modal' => function ($orderIds = []) {
    $this->reset(['vendor_id', 'vendor_name', 'phase_type', 'notes', 'costs', 'global_cost']);
    $this->orderIds = $orderIds;
    
    $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
    foreach ($orders as $order) {
        $this->costs[$order->item_id] = null;
    }
    
    $this->show = true;
}]);

$distributeGlobalCost = function() {
    $global = (float) $this->global_cost;
    if ($global <= 0) return;
    
    $totalQtyAll = 0;
    foreach ($this->groupedOrders as $group) {
        $totalQtyAll += $group['total_qty'];
    }
    
    if ($totalQtyAll > 0) {
        $costPerUnit = $global / $totalQtyAll;
        $newCosts = [];
        foreach ($this->groupedOrders as $itemId => $group) {
            $newCosts[$itemId] = round($costPerUnit * $group['total_qty']);
        }
        $this->costs = $newCosts;
    }
};

$copyDown = function($fromItemId) {
    $costToCopy = $this->costs[$fromItemId] ?? 0;
    $newCosts = $this->costs;
    foreach ($newCosts as $itemId => $val) {
        if ($itemId != $fromItemId && empty($val)) {
            $newCosts[$itemId] = $costToCopy;
        }
    }
    $this->costs = $newCosts;
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'vendor_id' => 'required',
        'costs.*' => 'required|numeric|min:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
        'costs.*.numeric' => 'Biaya tidak valid.'
    ]);

    DB::transaction(function () {
        $vendor = Vendor::find($this->vendor_id);
        if (!$vendor) return;

        // Create PO
        $nextId = PurchaseOrder::max('id') + 1;
        $poNumber = 'GUNJAS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $totalAmount = array_sum($this->costs);

        $po = PurchaseOrder::create([
            'po_number' => $poNumber,
            'vendor_id' => $vendor->id,
            'order_date' => now(),
            'status' => 'ordered',
            'total_amount' => $totalAmount,
            'notes' => "Perintah Kerja Maklon/Jasa. " . $this->notes,
            'created_by' => auth()->id()
        ]);

        foreach ($this->groupedOrders as $itemId => $group) {
            $groupCost = $this->costs[$itemId] ?? 0;
            $groupTotalQty = max(1, $group['total_qty']);
            $costPerUnit = $groupCost / $groupTotalQty;

            // Create PO Item
            $po->items()->create([
                'item_id' => $itemId, // we use the finished good item id
                'quantity' => $groupTotalQty,
                'unit_price' => $costPerUnit, // cost per item
                'subtotal' => $groupCost,
                'notes' => "Jasa Maklon Fase: " . ucfirst($this->phase_type)
            ]);

            // Update Production Orders
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
    });

    $this->dispatch('maklon-po-created');
    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    \Flux::toast('Perintah Kerja Maklon berhasil dibuat!', variant: 'success');
};

$handleVendorSelected = function ($vendorId) {
    $vendor = Vendor::find($vendorId);
    if ($vendor) {
        $this->vendor_id = $vendor->id;
        $this->vendor_name = $vendor->name;
    }
};

?>

<div @vendor-selected.window="$wire.handleVendorSelected($event.detail.vendorId); setTimeout(() => { $flux.modal('vendor-gallery-modal').close() }, 50);">
<flux:modal wire:model="show" class="md:w-[700px] max-w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Buat Perintah Kerja Maklon</flux:heading>
            <flux:subheading>Kirim barang-barang ini ke vendor eksternal dan buat tagihannya.</flux:subheading>
        </div>

        <div class="space-y-4">
            <div>
                <flux:label>Pilih Vendor Maklon</flux:label>
                <div class="flex gap-2 mt-1">
                    <flux:input wire:model="vendor_name" readonly placeholder="Pilih Vendor dari Galeri ->" class="flex-1 bg-zinc-50" />
                    <flux:button variant="filled" icon="users" x-on:click="setTimeout(() => { $flux.modal('vendor-gallery-modal').show() }, 50)">Galeri</flux:button>
                </div>
                @error('vendor_id')
                    <div class="text-sm text-red-500 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <flux:select wire:model="phase_type" label="Fase Pengerjaan">
                    <option value="finishing">Finishing</option>
                    <option value="jok">Jok (Upholstery)</option>
                    <option value="rakit">Rakit (Assembly)</option>
                </flux:select>
            </div>

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <flux:heading size="md">Daftar Barang & Biaya Borongan</flux:heading>
                    <div class="w-full sm:w-72">
                        <x-currency-input wire:model="global_cost" placeholder="Total Biaya Global..." class="!bg-yellow-50 dark:!bg-yellow-900/20" />
                        <div class="mt-1 flex justify-end">
                            <flux:button size="xs" variant="subtle" wire:click="distributeGlobalCost" icon="arrows-right-left">Bagi Rata Proporsional</flux:button>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-3">
                    @foreach($this->groupedOrders as $itemId => $group)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1">
                                <div class="font-bold text-sm">{{ $group['item']->name }}</div>
                                <div class="text-xs text-zinc-500 mt-0.5">
                                    Digabungkan dari {{ count($group['orders']) }} pesanan • Total Qty: <strong class="text-zinc-800 dark:text-zinc-200">{{ $group['total_qty'] }}</strong>
                                </div>
                            </div>
                            <div class="w-full sm:w-64 shrink-0 flex gap-2">
                                <div class="relative flex-1">
                                    <x-currency-input wire:model="costs.{{ $itemId }}" placeholder="0" />
                                </div>
                                <flux:button size="sm" variant="subtle" class="px-2 shrink-0" wire:click="copyDown({{ $itemId }})" tooltip="Salin harga ini ke item lain">
                                    <flux:icon.document-duplicate class="w-4 h-4 text-zinc-400 hover:text-indigo-500" />
                                </flux:button>
                            </div>
                        </div>
                        @error('costs.'.$itemId) <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                    @endforeach
                </div>
            </div>

            <div class="mt-4">
                <flux:textarea wire:model="notes" label="Catatan Jasa (Opsional)" placeholder="Catatan untuk vendor..." />
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="save">Simpan & Buat Tagihan</flux:button>
        </div>
    </div>
</flux:modal>
<livewire:global.vendor-gallery-modal />
<livewire:work-order.price-history-modal />
</div>
