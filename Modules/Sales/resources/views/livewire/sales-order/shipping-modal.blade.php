<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;
use Modules\Purchase\Models\Vendor;
use Illuminate\Support\Facades\Storage;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    
    'shipping_fee' => 0,
    'courier_vendor' => null,
    
    'selecting_for' => null,
]);

on(['open-shipping-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::find($orderId);
    if ($this->order) {
        $this->shipping_fee = $this->order->shipping_fee ?? 0;
        $this->courier_vendor = $this->order->courier_vendor_id ? Vendor::find($this->order->courier_vendor_id)?->toArray() : null;
        $this->show = true;
    }
}]);

on(['vendor-selected' => function ($vendorId = null) {
    if (!$this->show || $this->selecting_for !== 'courier') return;
    
    if ($vendorId) {
        $vendor = Vendor::find($vendorId);
        if ($vendor) {
            $this->courier_vendor = $vendor->toArray();
        }
    }
    $this->selecting_for = null;
}]);

$setSelectingFor = function ($type) {
    $this->selecting_for = $type;
};

$clearCourierVendor = function () {
    $this->courier_vendor = null;
};

$saveShippingInfo = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    $this->validate([
        'shipping_fee' => 'required|numeric|min:0',
        'courier_vendor' => 'required',
    ], [
        'courier_vendor.required' => 'Vendor Ekspedisi wajib dipilih sebelum mengirim pesanan.',
    ]);

    if (!$this->order) return;

    $subtotal = $this->order->items->sum('subtotal');
    $tax = $this->order->tax ?? 0;
    $discount = $this->order->discount ?? 0;
    
    // Asumsikan packing_fee sudah tersimpan sebelumnya, kita baca nilainya
    $packingFee = $this->order->packing_fee ?? 0;
    $grandTotal = $subtotal + (float)$packingFee + (float)$this->shipping_fee - $discount + $tax;

    $this->order->shipping_fee = $this->shipping_fee;
    $this->order->courier_vendor_id = $this->courier_vendor['id'] ?? null;
    $this->order->courier_vendor = $this->courier_vendor['name'] ?? '';
    
    $this->order->total_amount = $grandTotal;
    
    $this->order->status = 'shipping';
    $this->order->save();
    
    $this->dispatch('status-updated');
    $this->show = false;
    \Flux::toast('Biaya Ekspedisi tersimpan! Pesanan kini dalam status Pengiriman.', variant: 'success');
};

?>

<flux:modal wire:model="show" class="w-full md:w-[35rem] md:max-w-xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center">
                <flux:icon.truck class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Kirim Pesanan (Ekspedisi)</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    SO <strong>{{ $order->so_number }}</strong> - Masukkan detail ongkir dan vendor pengiriman.
                </flux:subheading>
            </div>
        </div>

        <div class="mt-8 space-y-4 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col">
            <div>
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Biaya Ongkir <span class="text-red-500">*</span></label>
                <x-rupiah-input wire:model="shipping_fee" placeholder="0" />
            </div>
            
            <div class="mt-4">
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Vendor Ekspedisi <span class="text-red-500">*</span></label>
                
                @if($courier_vendor)
                    <div class="group flex items-center gap-3 p-3 rounded-xl border border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-900/20 transition-all">
                        <flux:avatar src="{{ $courier_vendor['image'] ? Storage::url($courier_vendor['image']) : '' }}" fallback="{{ substr($courier_vendor['name'], 0, 2) }}" class="shadow-sm w-10 h-10" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-sm leading-none truncate">{{ $courier_vendor['name'] }}</h3>
                            </div>
                            <div class="mt-1 flex flex-col gap-y-1 text-[10px] text-zinc-600 dark:text-zinc-400">
                                <span class="truncate">{{ $courier_vendor['phone'] ?: 'Tidak ada nomor telepon' }}</span>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <flux:button variant="subtle" size="sm" icon="x-mark" class="text-zinc-400 hover:text-red-600 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-full w-8 h-8 flex items-center justify-center p-0" wire:click="clearCourierVendor" title="Ganti Ekspedisi"></flux:button>
                        </div>
                    </div>
                @else
                    <flux:button variant="subtle" class="w-full justify-center border-dashed border-2 border-zinc-300 dark:border-zinc-600 text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="setSelectingFor('courier'); $dispatchTo('global.vendor-gallery-modal', 'set-filter-type', { type: 'Ekspedisi', locked: true })" x-on:click="$flux.modal('vendor-gallery-modal').show()" icon="plus">
                        Pilih Vendor Ekspedisi
                    </flux:button>
                    @error('courier_vendor') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                @endif
            </div>
        </div>

        <div class="mt-6 bg-orange-50 dark:bg-orange-900/20 text-orange-800 dark:text-orange-200 text-xs p-3 rounded-lg flex gap-2 items-start border border-orange-200 dark:border-orange-900/50">
            <flux:icon.information-circle class="w-4 h-4 shrink-0 mt-0.5" />
            <p><strong>Info Penting:</strong> Menyimpan pengiriman akan merekap Biaya Ekspedisi ke nota dan memindahkan pesanan ke kolom <strong>Pengiriman</strong>.</p>
        </div>
        
        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="primary" wire:click="saveShippingInfo" icon="paper-airplane">Kirim Pesanan</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
