<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
]);

on(['open-approval-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with(['customer', 'items.item'])->find($orderId);
    $this->notes = '';
    $this->show = true;
}]);

$approve = function () {
    abort_unless(auth()->user()->can('sales.approve.update'), 403);
    
    if ($this->order) {
        $hasDeficit = false;
        // --- CEK KETERSEDIAAN STOK DAN BUAT ANTREAN JIKA KURANG ---
        foreach ($this->order->items as $item) {
            // Hitung Available to Promise (ATP)
            $itemModel = \Modules\Inventory\Models\Item::find($item->item_id);
            // Tambahkan kembali qty item saat ini ke ATP agar tidak double count sebagai booking
            // Karena SO ini masih berstatus 'draft' saat disetujui, qty-nya mungkin sudah masuk dalam 
            // perhitungan booking jika kita ubah status SO ini.
            $atp = $itemModel ? $itemModel->getATP() : 0;

            if ($item->qty > $atp) {
                $deficit = $item->qty - $atp;
                
                // Pastikan belum ada antrean untuk item ini dari SO ini
                $existingRequest = \Modules\Inventory\Models\InventoryRequest::where('item_id', $item->item_id)
                    ->where('source_type', 'sales')
                    ->where('reference_number', $this->order->so_number)
                    ->exists();
                    
                if (!$existingRequest) {
                    \Modules\Inventory\Models\InventoryRequest::create([
                        'item_id' => $item->item_id,
                        'source_type' => 'sales',
                        'reference_number' => $this->order->so_number,
                        'requested_qty' => $deficit,
                        'notes' => 'Defisit stok untuk pesanan pelanggan (ATP: ' . $atp . ', Dipesan: ' . $item->qty . ')',
                        'status' => 'draft',
                    ]);
                    $hasDeficit = true;
                }
            }
        }
        
        $this->order->status = 'processing';
        // Simpan catatan persetujuan jika diperlukan
        $this->order->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
        if ($hasDeficit) {
            \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        }
        $this->show = false;
        \Flux::toast('Pesanan disetujui, lanjut ke pemrosesan gudang.', variant: 'success');
    }
};

$reject = function () {
    abort_unless(auth()->user()->can('sales.approve.update'), 403);
    
    if ($this->order) {
        $this->order->status = 'rejected';
        $this->order->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
        $this->show = false;
        \Flux::toast('Pesanan ditolak.', variant: 'danger');
    }
};

?>

<flux:modal wire:model="show" class="w-full md:w-[32rem] md:max-w-lg">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center">
                <flux:icon.clipboard-document-check class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Persetujuan Pesanan</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    Mohon periksa detail SO <strong>{{ $order->so_number }}</strong> untuk pelanggan <strong>{{ $order->customer->name }}</strong> sebelum menyetujuinya.
                </flux:subheading>
            </div>
        </div>

        <div class="mt-6 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-700">
            <div class="flex justify-between items-center mb-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex flex-col">
                    <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Total Nilai</span>
                    <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex flex-col items-end">
                    <span class="text-[10px] text-zinc-500 font-bold uppercase tracking-wider">Status Pembayaran</span>
                    <flux:badge size="sm" color="{{ $order->payment_status === 'paid' ? 'green' : ($order->payment_status === 'partial' ? 'amber' : 'red') }}">
                        {{ strtoupper($order->payment_status) }}
                    </flux:badge>
                </div>
            </div>
            
            <h4 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-2">Item Pesanan ({{ count($order->items) }})</h4>
            <div class="max-h-40 overflow-y-auto space-y-2 pr-2 custom-scrollbar">
                @foreach($order->items as $item)
                    <div class="flex justify-between items-start text-sm">
                        <div class="flex flex-col">
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $item->item->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $item->qty }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</span>
                        </div>
                        <span class="font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6">
            <flux:textarea wire:model="notes" label="Catatan Tambahan (Opsional)" placeholder="Tambahkan catatan jika ditolak atau pesan untuk gudang..." />
        </div>
        
        <div class="mt-8 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
            <flux:button variant="danger" wire:click="reject" icon="x-mark">Tolak Pesanan</flux:button>
            <flux:button variant="primary" wire:click="approve" icon="check">Setujui Pesanan</flux:button>
        </div>
    </div>
    @endif
</flux:modal>
