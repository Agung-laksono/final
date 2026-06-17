<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'qty' => 1,
    'notes' => '',
    'warehouse_id' => null,
    'warehouses' => []
]);

on(['open-receiving-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    $this->qty = $this->order ? ($this->order->requested_qty - $this->order->fulfilled_qty) : 1;
    $this->notes = '';
    $this->warehouses = DB::table('warehouses')->get();
    $this->warehouse_id = $this->warehouses->first()->id ?? null;
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'qty' => 'required|numeric|min:1',
        'warehouse_id' => 'required'
    ]);

    if ($this->order) {
        $maxAllowed = $this->order->requested_qty - $this->order->fulfilled_qty;
        if ($this->qty > $maxAllowed) {
            \Flux::toast('Jumlah melebihi sisa yang harus diproduksi.', variant: 'danger');
            return;
        }

        DB::transaction(function () {
            // Update the production order
            $this->order->fulfilled_qty += $this->qty;
            if ($this->order->fulfilled_qty >= $this->order->requested_qty) {
                $this->order->status = 'completed';
            }
            $this->order->save();

            // Insert into item_warehouse (update stock)
            $existingStock = DB::table('item_warehouse')
                ->where('item_id', $this->order->item_id)
                ->where('warehouse_id', $this->warehouse_id)
                ->first();

            if ($existingStock) {
                DB::table('item_warehouse')
                    ->where('id', $existingStock->id)
                    ->update(['stock' => $existingStock->stock + $this->qty, 'updated_at' => now()]);
            } else {
                DB::table('item_warehouse')->insert([
                    'item_id' => $this->order->item_id,
                    'warehouse_id' => $this->warehouse_id,
                    'stock' => $this->qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Create StockMovement history
            StockMovement::create([
                'item_id' => $this->order->item_id,
                'warehouse_id' => $this->warehouse_id,
                'type' => 'in',
                'qty' => $this->qty,
                'reference_number' => $this->order->order_number,
                'notes' => 'Penerimaan hasil produksi. ' . $this->notes,
                'created_by' => auth()->id(),
            ]);
        });
        
        $this->dispatch('status-updated');
        $this->show = false;
        \Flux::toast('Penerimaan hasil produksi berhasil dicatat.', variant: 'success');
    }
};
?>

<flux:modal wire:model="show" class="md:w-[500px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Penerimaan Hasil Produksi</flux:heading>
            <flux:subheading>Terima barang jadi ke dalam gudang (Bisa parsial).</flux:subheading>
        </div>

        @if($order)
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="font-bold text-zinc-900 dark:text-white">{{ $order->item->name }}</div>
                <div class="flex justify-between text-sm mt-2">
                    <div>Target: <span class="font-bold">{{ $order->requested_qty }}</span></div>
                    <div>Sudah Diterima: <span class="font-bold text-emerald-600">{{ $order->fulfilled_qty }}</span></div>
                    <div>Sisa: <span class="font-bold text-red-600">{{ $order->requested_qty - $order->fulfilled_qty }}</span></div>
                </div>
            </div>

            <div class="space-y-4">
                <flux:input type="number" wire:model="qty" label="Jumlah Diterima" min="1" max="{{ $order->requested_qty - $order->fulfilled_qty }}" />
                
                <flux:select wire:model="warehouse_id" label="Masuk ke Gudang" placeholder="Pilih Gudang">
                    @foreach($warehouses as $wh)
                        <flux:select.option value="{{ $wh->id }}">{{ $wh->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea wire:model="notes" label="Catatan (Opsional)" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="save">Terima ke Gudang</flux:button>
            </div>
        @endif
    </div>
</flux:modal>
