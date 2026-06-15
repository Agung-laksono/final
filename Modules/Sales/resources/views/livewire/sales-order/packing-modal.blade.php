<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\Storage;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    
    'packing_fee' => 0,
    'packing_vendor' => null,
    
    'selecting_for' => null,
]);

on(['open-packing-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::find($orderId);
    if ($this->order) {
        $this->packing_fee = $this->order->packing_fee ?? 0;
        $this->packing_vendor = $this->order->packing_vendor_id ? Vendor::find($this->order->packing_vendor_id)?->toArray() : null;
        
        $this->show = true;
    }
}]);

on(['vendor-selected' => function ($vendorId = null) {
    if (!$this->show || $this->selecting_for !== 'packing') return;
    
    if ($vendorId) {
        $vendor = Vendor::find($vendorId);
        if ($vendor) {
            $this->packing_vendor = $vendor->toArray();
        }
    }
    $this->selecting_for = null;
}]);

$setSelectingFor = function ($type) {
    $this->selecting_for = $type;
};

$clearPackingVendor = function () {
    $this->packing_vendor = null;
};

$savePackingInfo = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);

    if (!$this->order) return;

    // Hitung ulang Grand Total
    $subtotal = $this->order->items->sum('subtotal');
    $tax = $this->order->tax ?? 0;
    $discount = $this->order->discount ?? 0;
    
    // Asumsikan shipping_fee sudah tersimpan atau 0
    $shippingFee = $this->order->shipping_fee ?? 0;
    $grandTotal = $subtotal + (float)$this->packing_fee + (float)$shippingFee - $discount + $tax;

    $this->order->packing_fee = $this->packing_fee;
    $this->order->packing_vendor_id = $this->packing_vendor['id'] ?? null;
    $this->order->total_amount = $grandTotal;
    
    // Status tetap 'packing'
    $this->order->save();
    
    $this->dispatch('status-updated');
    $this->show = false;
    \Flux::toast('Detail Packing berhasil disimpan!', variant: 'success');
};

?>

<flux:modal wire:model="show" class="w-full md:w-[35rem] md:max-w-xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center">
                <flux:icon.archive-box class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Detail Packing</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    SO <strong>{{ $order->so_number }}</strong> - Masukkan biaya packing dan vendor (opsional).
                </flux:subheading>
            </div>
        </div>

        <div class="mt-8 space-y-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col">
            <div>
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Biaya Packing</label>
                <x-rupiah-input wire:model="packing_fee" placeholder="0" />
            </div>
            
            <div class="mt-4">
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Vendor Packing</label>
                
                @if($packing_vendor)
                    <div class="group flex items-center gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                        <flux:avatar src="{{ $packing_vendor['image'] ? Storage::url($packing_vendor['image']) : '' }}" fallback="{{ substr($packing_vendor['name'], 0, 2) }}" class="shadow-sm w-10 h-10" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm leading-none truncate">{{ $packing_vendor['name'] }}</h3>
                            </div>
                            <div class="mt-1 flex flex-col gap-y-1 text-[10px] text-zinc-600 dark:text-zinc-400">
                                <span class="truncate">{{ $packing_vendor['phone'] ?: 'Tidak ada nomor telepon' }}</span>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <flux:button variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-full w-8 h-8 flex items-center justify-center p-0" wire:click="clearPackingVendor" title="Hapus Vendor"></flux:button>
                        </div>
                    </div>
                @else
                    <flux:button variant="subtle" class="w-full justify-center border-dashed border-2 border-zinc-300 dark:border-zinc-600 text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setSelectingFor('packing'); $dispatchTo('global.vendor-gallery-modal', 'set-filter-type', { type: 'Packing', locked: true })" x-on:click="$flux.modal('vendor-gallery-modal').show()" icon="plus">
                        Pilih Vendor Packing
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="savePackingInfo" icon="check">Simpan Detail Packing</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
