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
    'notes' => '',
    'costs' => [], // array keyed by order_id
]);

$orders = computed(function () {
    if (empty($this->orderIds)) return collect();
    return ProductionOrder::with('item')->whereIn('id', $this->orderIds)->get();
});

on(['open-maklon-modal' => function ($orderIds = []) {
    $this->reset(['vendor_id', 'vendor_name', 'notes', 'costs']);
    $this->orderIds = $orderIds;
    
    foreach ($this->orderIds as $id) {
        $this->costs[$id] = null;
    }
    
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'vendor_id' => 'required',
        'costs.*' => 'required|numeric|gt:0'
    ], [
        'vendor_id.required' => 'Pilih vendor terlebih dahulu.',
        'costs.*.gt' => 'Biaya tidak boleh Rp 0.'
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

        $orders = ProductionOrder::whereIn('id', $this->orderIds)->get();
        foreach ($orders as $order) {
            $cost = $this->costs[$order->id] ?? 0;
            
            // Create PO Item
            $po->items()->create([
                'item_id' => $order->item_id, // we use the finished good item id
                'quantity' => $order->requested_qty,
                'unit_price' => $cost / max(1, $order->requested_qty), // cost per item
                'subtotal' => $cost,
                'notes' => "Jasa Maklon PO Prod: " . $order->order_number
            ]);

            // Update Production Order
            $order->status = 'in_production';
            $order->vendor_cost = $cost;
            $order->purchase_order_id = $po->id;
            if ($this->notes) {
                $order->notes = $order->notes . "\n[Maklon to " . $vendor->name . "]: " . $this->notes;
            }
            $order->save();
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

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <flux:heading size="md" class="mb-3">Daftar Barang & Biaya Borongan</flux:heading>
                
                <div class="space-y-3">
                    @foreach($this->orders as $order)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-zinc-50 dark:bg-zinc-800/30 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <div class="flex-1">
                                <div class="font-bold text-sm">{{ $order->item->name }}</div>
                                <div class="text-xs text-zinc-500 mt-0.5">{{ $order->order_number }} • Qty: {{ $order->requested_qty }}</div>
                            </div>
                            <div class="w-full sm:w-48 shrink-0 flex gap-2">
                                <div class="relative flex-1">
                                    <x-currency-input wire:model="costs.{{ $order->id }}" placeholder="0" />
                                    <button type="button" wire:click="$dispatch('open-price-history', { itemId: {{ $order->item_id }} })" class="absolute right-2 top-2 text-zinc-400 hover:text-blue-500 transition-colors" title="Lihat Riwayat Harga">
                                        <flux:icon.clock class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        @error('costs.'.$order->id) <span class="text-xs text-red-500">{{ $message }}</span> @enderror
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
