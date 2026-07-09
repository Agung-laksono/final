<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'vendor_cost' => 0,
    'notes' => ''
]);

on(['open-vendor-cost-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::find($orderId);
    $this->vendor_cost = $this->order && $this->order->vendor_cost > 0 ? $this->order->vendor_cost : null;
    $this->notes = '';
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'vendor_cost' => 'required|numeric|gt:0'
    ], [
        'vendor_cost.gt' => 'Biaya tidak boleh Rp 0.'
    ]);

    if ($this->order) {
        $this->order->vendor_cost = $this->vendor_cost;
        if ($this->notes) {
            $this->order->notes = $this->order->notes . "\n[Vendor Cost]: " . $this->notes;
        }
        $this->order->save();
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        $this->show = false;
        \Flux::toast('Biaya vendor berhasil disimpan.', variant: 'success');
    }
};
?>

<flux:modal wire:model="show" class="md:w-[400px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Input Biaya Vendor</flux:heading>
            <flux:subheading>Masukkan estimasi atau rincian biaya pihak ketiga (maklon/jasa).</flux:subheading>
        </div>

        @if($order)
            <div class="space-y-4">
                <x-currency-input wire:model="vendor_cost" label="Total Biaya Vendor (Rp)" placeholder="0" />
                <flux:textarea wire:model="notes" label="Catatan Jasa/Maklon (Opsional)" placeholder="Misal: Jasa potong kayu dan finishing..." />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button icon="check" variant="primary" wire:click="save"> Simpan B </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
