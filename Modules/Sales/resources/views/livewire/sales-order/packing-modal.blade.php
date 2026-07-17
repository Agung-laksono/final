<?php
use function Livewire\Volt\{state, on, usesFileUploads};
use Modules\Sales\Models\SalesOrder;

usesFileUploads();

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'packing_fee' => null,
    'packing_receipt' => null,
    'notes' => '',
]);

on(['open-packing-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::find($orderId);
    
    $this->packing_fee = $this->order->packing_fee > 0 ? $this->order->packing_fee : null;
    $this->notes = '';
    $this->packing_receipt = null;
    
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    $this->validate([
        'packing_fee' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:500'
    ]);

    if ($this->order) {
        if ($this->packing_receipt) {
            if (is_string($this->packing_receipt) && str_starts_with($this->packing_receipt, 'data:image')) {
                list($type, $data) = explode(';', $this->packing_receipt);
                list(, $data)      = explode(',', $data);
                $data = base64_decode($data);
                
                $filename = 'receipts/packing_' . uniqid() . '.webp';
                \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $data);
                $this->order->packing_receipt_path = $filename;
            } else {
                $path = $this->packing_receipt->store('receipts/packing', 'public');
                $this->order->packing_receipt_path = $path;
            }
        }

        if ($this->packing_fee !== null && $this->packing_fee !== '') {
            $this->order->packing_fee = $this->packing_fee;
            $this->order->actual_packing_fee = $this->packing_fee;
        }

        if ($this->notes) {
            $this->order->notes = $this->order->notes . "\n[Packing Note]: " . $this->notes;
        }

        $this->order->is_packed = true;
        
        // Tetap di 'packing' karena pergerakan ke 'Tunggu Kurir' 
        // akan dikendalikan secara visual oleh is_packed = true
        $this->order->status = 'packing';
        
        $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
            return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
        }) == '1';
        
        if ($gudangHandlesShipping) {
            $toastMessage = 'Proses packing selesai. Pesanan pindah ke Tunggu Kurir!';
        } else {
            $toastMessage = 'Packing selesai! Pesanan menunggu konfirmasi pengiriman oleh tim Sales.';
        }

        $this->order->save();

        \Flux::toast($toastMessage, variant: 'success');
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
        $this->show = false;
    }
};
?>

<div>
<flux:modal wire:model="show" class="w-full md:w-[42rem] md:max-w-3xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4 mb-6">
            <div class="flex-shrink-0 w-10 h-10 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center">
                <flux:icon.archive-box class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Detail Pengemasan (Packing)</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    Kemas barang untuk SO <strong>{{ $order->so_number }}</strong>.
                </flux:subheading>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
            <div class="space-y-4">
                <div>
                    <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Biaya Packing (Opsional)</label>
                    <x-rupiah-input wire:model="packing_fee" placeholder="0" />
                    <span class="text-[10px] text-zinc-500 mt-1 block">Palet kayu atau kardus besar.</span>
                </div>
                
                <div>
                    <flux:textarea wire:model="notes" label="Catatan Dimensi (Opsional)" placeholder="Misal: P 40 x L 30 x T 20cm, Berat 2Kg..." />
                </div>
            </div>

            <div>
                <label class="block mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Foto Packing / Bukti</label>
                <div class="bg-white dark:bg-zinc-900 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <x-image-cropper id="packing-cropper" wire:model="packing_receipt" :image="$packing_receipt && is_string($packing_receipt) && !str_starts_with($packing_receipt, 'data:image') ? \Illuminate\Support\Facades\Storage::url($packing_receipt) : null" accept="image/*" />
                </div>
                <div wire:loading wire:target="packing_receipt" class="text-xs text-purple-600 mt-2">Memproses gambar...</div>
                <div class="text-[10px] text-zinc-500 mt-1">Disarankan sbg bukti jika pembeli komplain.</div>
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
            <flux:button variant="primary" class="bg-purple-600 hover:bg-purple-700 text-white border-none" wire:click="save" wire:loading.attr="disabled" wire:target="save, packing_receipt">
                <span wire:loading.remove wire:target="save">Tandai Selesai Packing</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </flux:button>
        </div>
    </div>
    @endif
</flux:modal>
</div>
