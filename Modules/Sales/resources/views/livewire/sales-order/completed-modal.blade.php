<?php

use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'totalAmount' => 0,
    'totalPaid' => 0,
    'notes' => ''
]);

on(['open-completed-modal' => function ($orderId) {
    $this->reset(['notes']);
    $this->orderId = $orderId['orderId'] ?? $orderId;
    $this->order = SalesOrder::with(['payments'])->find($this->orderId);
    
    if ($this->order) {
        $this->totalAmount = (float) $this->order->total_amount;
        $this->totalPaid = (float) $this->order->payments->where('status', 'verified')->sum('amount');
        $this->show = true;
    }
}]);

$save = function () {
    $this->validate([
        'notes' => 'nullable|string|max:1000'
    ]);

    if ($this->order) {
        if ($this->totalPaid < $this->totalAmount && empty(trim($this->notes))) {
            \Flux::toast('Wajib mengisi catatan karena pembayaran belum lunas!', variant: 'danger');
            return;
        }

        if (trim($this->notes)) {
            $this->order->notes = $this->order->notes . "\n[Catatan Penutupan]: " . $this->notes;
        }

        $this->order->status = 'completed';
        $this->order->save();

        // Di sini bisa ditambahkan logika pengurangan stok otomatis (mutasi keluar)
        // secara formal di inventory system.

        \Flux::toast('Pesanan berhasil ditutup/selesai!', variant: 'success');
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
        $this->show = false;
    }
};

?>

<flux:modal wire:model="show" class="md:w-[32rem]">
    @if($order)
        <div class="mb-6">
            <flux:heading size="lg">Konfirmasi Selesai</flux:heading>
            <flux:subheading>Tandai pesanan {{ $order->so_number }} telah selesai sepenuhnya.</flux:subheading>
        </div>

        <div class="space-y-5">
            {{-- Status Pembayaran Panel --}}
            <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400 font-medium">Status Pembayaran</span>
                    @if($totalPaid >= $totalAmount)
                        <flux:badge size="sm" color="green" icon="check-circle">Lunas</flux:badge>
                    @else
                        <flux:badge size="sm" color="red" icon="exclamation-triangle">Belum Lunas</flux:badge>
                    @endif
                </div>
                
                <div class="flex justify-between items-end mt-4">
                    <div>
                        <div class="text-[10px] uppercase tracking-wider text-zinc-500 font-bold mb-1">Total Tagihan</div>
                        <div class="text-lg font-mono font-bold text-zinc-900 dark:text-zinc-100">
                            Rp {{ number_format($totalAmount, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] uppercase tracking-wider text-zinc-500 font-bold mb-1">Telah Dibayar</div>
                        <div class="text-lg font-mono font-bold {{ $totalPaid >= $totalAmount ? 'text-emerald-600' : 'text-rose-600' }}">
                            Rp {{ number_format($totalPaid, 0, ',', '.') }}
                        </div>
                    </div>
                </div>
                
                @if($totalPaid < $totalAmount)
                    <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-900/50 flex gap-3">
                        <flux:icon.exclamation-triangle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                        <div class="text-xs text-red-800 dark:text-red-300">
                            <strong>Peringatan!</strong> Terdapat sisa kekurangan sebesar <strong>Rp {{ number_format($totalAmount - $totalPaid, 0, ',', '.') }}</strong>. Anda diwajibkan memberikan alasan penutupan paksa pada kolom catatan di bawah (contoh: pemutihan hutang, retur barang, dll).
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <flux:textarea 
                    wire:model="notes" 
                    label="Catatan Final/Penutupan" 
                    placeholder="Tuliskan catatan opsional. Namun jika pembayaran belum lunas, catatan ini WAJIB diisi..." 
                    class="h-24"
                />
            </div>
        </div>

        <div class="mt-8 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
            <flux:button variant="primary" class="bg-indigo-600 hover:bg-indigo-700 text-white border-none" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Selesaikan Pesanan</span>
                <span wire:loading wire:target="save">Menyimpan...</span>
            </flux:button>
        </div>
    @endif
</flux:modal>
