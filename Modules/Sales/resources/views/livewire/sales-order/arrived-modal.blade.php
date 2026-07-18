<?php

use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\Storage;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    
    // Form fields
    'arrived_receipt_file' => null,
    'notes' => ''
]);

on(['open-arrived-modal' => function ($orderId) {
    $this->reset(['arrived_receipt_file', 'notes']);
    $this->orderId = $orderId['orderId'] ?? $orderId;
    $this->order = SalesOrder::find($this->orderId);
    
    if ($this->order) {
        $this->show = true;
    }
}]);

$save = function () {
    $this->validate([
        'notes' => 'nullable|string|max:500'
    ]);

    if ($this->order) {
        if ($this->arrived_receipt_file) {
            if (is_string($this->arrived_receipt_file) && str_starts_with($this->arrived_receipt_file, 'data:image')) {
                list($type, $data) = explode(';', $this->arrived_receipt_file);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                
                $filename = 'receipts/arrived_' . uniqid() . '.webp';
                Storage::disk('public')->put($filename, $data);
                $this->order->arrived_receipt_path = $filename;
            } else {
                $path = $this->arrived_receipt_file->store('receipts/arrived', 'public');
                $this->order->arrived_receipt_path = $path;
            }
        }

        if ($this->notes) {
            $this->order->notes = $this->order->notes . "\n[Catatan Penerimaan]: " . $this->notes;
        }

        $this->order->status = 'arrived';
        $this->order->save();

        \Flux::toast('Barang sudah diterima pelanggan.', variant: 'success');
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
        $this->show = false;
    }
};

?>

<flux:modal wire:model="show" class="md:w-96">
    @if($order)
        <div class="mb-6">
            <flux:heading size="lg">Konfirmasi Penerimaan</flux:heading>
            <flux:subheading>Tandai pesanan {{ $order->so_number }} telah sampai ke pelanggan.</flux:subheading>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Foto Bukti Terima (Opsional)</label>
                <div class="bg-white dark:bg-zinc-900 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <x-image-cropper id="arrived-cropper" wire:model="arrived_receipt_file" accept="image/*" />
                </div>
                <div wire:loading wire:target="arrived_receipt_file" class="text-xs text-purple-600 mt-2">Memproses gambar...</div>
                <div class="text-xs text-zinc-500 mt-1">Disarankan untuk difoto sebagai bukti sah penerimaan barang.</div>
            </div>
            
            <div>
                <flux:textarea wire:model="notes" label="Catatan Tambahan (Opsional)" placeholder="Misal: Diterima oleh Satpam Bpk. Budi..." />
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
            <flux:button variant="primary" class="bg-teal-600 hover:bg-teal-700 text-white border-none" wire:click="save" wire:loading.attr="disabled" wire:target="save, arrived_receipt_file">
                <span wire:loading.remove wire:target="save">Tandai Sampai</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </flux:button>
        </div>
    @endif
</flux:modal>
