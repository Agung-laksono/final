<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Modules\Inventory\Models\ItemLabel;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'rejectNotes' => '',
    'movements' => [],
]);

on(['open-material-receipt-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    $this->rejectNotes = '';
    
    if ($this->order) {
        // Fetch what was given based on stock movements
        $movements = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('units', 'items.unit_id', '=', 'units.id')
            ->where('stock_movements.reference_number', $this->order->order_number)
            ->where('stock_movements.type', 'out')
            ->select('items.id as item_id', 'items.name as item_name', 'units.name as unit_name', 'items.requires_label', DB::raw('SUM(stock_movements.quantity) as total_qty'))
            ->groupBy('items.id', 'items.name', 'units.name', 'items.requires_label')
            ->get();
            
        $labels = ItemLabel::where('notes', 'like', 'Dikonsumsi untuk Produksi: ' . $this->order->order_number)->get()->groupBy('item_id');
        
        foreach ($movements as $mov) {
            $mov->labels = isset($labels[$mov->item_id]) ? $labels[$mov->item_id] : collect();
        }
            
        $this->movements = $movements;
    }
    
    $this->show = true;
}]);

$accept = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    if ($this->order) {
        $this->order->status = 'waiting_vendor';
        $this->order->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \Flux::toast('Bahan diterima secara fisik. Pesanan masuk ke proses produksi.', variant: 'success');
        $this->show = false;
    }
};

$reject = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'rejectNotes' => 'required|min:5'
    ], [
        'rejectNotes.required' => 'Wajib mengisi alasan penolakan/kekurangan.',
        'rejectNotes.min' => 'Alasan terlalu singkat.'
    ]);
    
    if ($this->order) {
        DB::transaction(function () {
            // 1. Revert Stock Movements
            $outMovements = DB::table('stock_movements')
                ->where('reference_number', $this->order->order_number)
                ->where('type', 'out')
                ->get();
                
            $inventoryService = app(\App\Services\InventoryService::class);
            
            foreach ($outMovements as $mov) {
                // Return stock to warehouse
                $inventoryService->adjustStock(
                    $mov->item_id,
                    $mov->warehouse_id,
                    $mov->quantity,
                    'in',
                    $this->order->order_number,
                    'Retur Penolakan Produksi: ' . $this->rejectNotes
                );
            }
            
            // 2. Revert Labels if any
            $labels = ItemLabel::where('notes', 'like', 'Dikonsumsi untuk Produksi: ' . $this->order->order_number)->get();
            foreach ($labels as $label) {
                $label->status = 'in_stock';
                $label->notes = 'Retur Penolakan Produksi';
                $label->save();
            }
            
            // 3. Delete previous OUT movements for this WO to reset the "alreadyConsumed" counter
            DB::table('stock_movements')
                ->where('reference_number', $this->order->order_number)
                ->where('type', 'out')
                ->delete();
                
            // 4. Update Order
            $this->order->status = 'waiting_material';
            $this->order->notes = $this->order->notes . "\n[RETUR BAHAN]: " . $this->rejectNotes;
            $this->order->save();
        });

        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \Flux::toast('Bahan dikembalikan ke Gudang. Pesanan kembali ke status Menunggu Bahan.', variant: 'warning');
        $this->show = false;
    }
};
?>

<flux:modal wire:model="show" class="md:w-[500px]">
    @if($order)
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Serah-Terima Bahan Fisik</flux:heading>
            <flux:subheading>Validasi fisik bahan baku yang diserahkan oleh Gudang untuk SPK <strong>{{ $order->order_number }}</strong>.</flux:subheading>
        </div>

        <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <h4 class="text-sm font-bold text-zinc-700 dark:text-zinc-300 mb-3 uppercase tracking-wider">Bahan yang Diserahkan (Sistem):</h4>
            <div class="space-y-2">
                @forelse($movements as $mov)
                    <div class="bg-white dark:bg-zinc-900 px-3 py-2 border border-zinc-100 dark:border-zinc-800 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $mov->item_name }}</span>
                            <span class="font-bold text-blue-600">{{ $mov->total_qty }} <span class="text-xs text-zinc-500 font-normal">{{ $mov->unit_name }}</span></span>
                        </div>
                        
                        @if($mov->labels && count($mov->labels) > 0)
                            <div class="mt-2 pt-2 border-t border-dashed border-zinc-200 dark:border-zinc-800">
                                <div class="text-[10px] font-bold text-zinc-400 mb-1 uppercase tracking-wider">Daftar Serial / Barcode:</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($mov->labels as $label)
                                        <span class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs px-1.5 py-0.5 rounded border border-indigo-100 dark:border-indigo-800/50 font-mono">
                                            <flux:icon.qr-code class="w-3 h-3" />
                                            {{ $label->label_code }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-zinc-500 italic">Tidak ada catatan bahan (kemungkinan kesalahan sistem).</div>
                @endforelse
            </div>
            
            <div class="mt-4 flex items-start gap-2 bg-blue-50 dark:bg-blue-900/30 p-3 rounded-lg text-sm text-blue-700 dark:text-blue-300">
                <flux:icon.information-circle class="w-5 h-5 shrink-0 mt-0.5" />
                <p>Silakan hitung fisik bahan yang Anda terima. Apakah jumlahnya sudah sesuai dengan catatan sistem di atas?</p>
            </div>
        </div>

        <div x-data="{ mode: 'confirm' }">
            <div class="flex gap-2 mb-4" x-show="mode === 'confirm'">
                <flux:button variant="danger" x-on:click="mode = 'reject'" class="flex-1" icon="x-mark">Tolak / Ada Kekurangan</flux:button>
                <flux:button variant="primary" wire:click="accept" class="flex-1" icon="check">Ya, Fisik Sesuai</flux:button>
            </div>
            
            <div x-show="mode === 'reject'" x-cloak class="space-y-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <flux:heading size="sm" class="text-red-600">Form Retur Bahan ke Gudang</flux:heading>
                <p class="text-xs text-zinc-500">Tuliskan bahan apa yang kurang/cacat. Semua bahan yang sudah diserahkan akan dibatalkan secara sistem dan dikembalikan ke antrean pemenuhan Gudang.</p>
                <flux:textarea wire:model="rejectNotes" placeholder="Misal: Kurang 1 kayu, dan 1 paku berkarat..." />
                
                <div class="flex gap-2">
                    <flux:button variant="ghost" x-on:click="mode = 'confirm'"> Batal </flux:button>
                    <flux:button variant="danger" wire:click="reject" class="flex-1">Kirim Retur ke Gudang</flux:button>
                </div>
            </div>
        </div>
    </div>
    @endif
</flux:modal>
