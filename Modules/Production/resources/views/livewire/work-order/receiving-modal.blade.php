<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
    'distributions' => [],
    'warehouses' => []
]);

on(['open-receiving-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    
    $remaining = $this->order ? ($this->order->requested_qty - $this->order->fulfilled_qty) : 1;
    
    $this->distributions = [
        ['warehouse_id' => '', 'qty' => null]
    ];
    $this->notes = '';
    $this->warehouses = DB::table('warehouses')->get();
    $this->show = true;
}]);

$isMaxQtyReached = computed(function () {
    if (!$this->order) return false;
    $maxAllowed = $this->order->requested_qty - $this->order->fulfilled_qty;
    $total = collect($this->distributions)->sum(fn($d) => (int)($d['qty'] ?? 0));
    return $total >= $maxAllowed || count($this->distributions) >= $this->warehouses->count();
});

$addDistribution = function () {
    $this->distributions[] = ['warehouse_id' => '', 'qty' => null];
};

$removeDistribution = function ($index) {
    unset($this->distributions[$index]);
    $this->distributions = array_values($this->distributions);
};

$updated = function ($property, $value) {
    if ($this->order && str_starts_with($property, 'distributions.') && str_ends_with($property, '.qty')) {
        $parts = explode('.', $property);
        $index = $parts[1];
        
        $totalOther = 0;
        foreach ($this->distributions as $idx => $dist) {
            if ($idx != $index) {
                $totalOther += (int)($dist['qty'] ?? 0);
            }
        }
        
        $maxAllowed = $this->order->requested_qty - $this->order->fulfilled_qty;
        $maxAllowedForThisRow = $maxAllowed - $totalOther;
        
        if ((int)$value > $maxAllowedForThisRow) {
            $this->distributions[$index]['qty'] = max(1, $maxAllowedForThisRow);
        }
    }
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'distributions' => 'required|array|min:1',
        'distributions.*.warehouse_id' => 'required',
        'distributions.*.qty' => 'required|numeric|min:1'
    ], [
        'distributions.*.warehouse_id.required' => 'Gudang tujuan wajib dipilih.',
        'distributions.*.qty.min' => 'Jumlah minimal 1.'
    ]);

    if ($this->order) {
        $totalQty = collect($this->distributions)->sum('qty');
        $maxAllowed = $this->order->requested_qty - $this->order->fulfilled_qty;
        
        if ($totalQty > $maxAllowed) {
            \Flux::toast("Total jumlah yang dialokasikan ($totalQty) melebihi sisa yang harus diproduksi ($maxAllowed).", variant: 'danger');
            return;
        }

        $generatedLabelIds = [];
        $generatedLabelsCount = 0;

        DB::transaction(function () use ($totalQty, &$generatedLabelIds, &$generatedLabelsCount) {
            $inventoryService = app(\App\Services\InventoryService::class);
            
            // Update the production order
            $this->order->fulfilled_qty += $totalQty;
            if ($this->order->fulfilled_qty >= $this->order->requested_qty) {
                $this->order->status = 'completed';
            }
            $this->order->save();

            foreach ($this->distributions as $dist) {
                $qty = $dist['qty'];
                $wh_id = $dist['warehouse_id'];
                
                $inventoryService->adjustStock(
                    $this->order->item_id,
                    $wh_id,
                    $qty, // Quantity
                    'in', // Type
                    $this->order->order_number, // Ref
                    'Penerimaan hasil produksi. ' . $this->notes // Notes
                );

                // Generate Labels if required
                if ($this->order->item->requires_label) {
                    for ($i = 0; $i < $qty; $i++) {
                        do {
                            $code = strtoupper(\Illuminate\Support\Str::random(6));
                        } while (\Modules\Inventory\Models\ItemLabel::where('label_code', $code)->exists());

                        $label = \Modules\Inventory\Models\ItemLabel::create([
                            'item_id' => $this->order->item_id,
                            'label_code' => $code,
                            'status' => 'in_stock',
                            'warehouse_id' => $wh_id,
                            'notes' => 'Dari Hasil Produksi: ' . $this->order->order_number,
                        ]);
                        $generatedLabelIds[] = $label->id;
                        $generatedLabelsCount++;
                    }
                }
            }
        });
        
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        $this->show = false;
        
        if (count($generatedLabelIds) > 0) {
            $this->dispatch('open-print-labels', labelIds: $generatedLabelIds);
            \Flux::toast("Penerimaan hasil produksi berhasil dicatat. $generatedLabelsCount Label Serial berhasil di-generate.", variant: 'success');
        } else {
            \Flux::toast('Penerimaan hasil produksi berhasil dicatat.', variant: 'success');
        }
    }
};
?>

<flux:modal wire:model="show" class="md:w-[600px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Penerimaan Hasil Produksi</flux:heading>
            <flux:subheading>Terima barang jadi dan alokasikan ke beberapa gudang sekaligus.</flux:subheading>
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
                <flux:heading size="md">Alokasi Gudang</flux:heading>
                
                @php
                    $selectedWarehouses = collect($distributions)->pluck('warehouse_id')->filter()->toArray();
                @endphp

                <div class="space-y-3">
                    @foreach($distributions as $index => $dist)
                        <div class="flex items-start gap-3">
                            <div class="flex-1">
                                <flux:select wire:model.live="distributions.{{ $index }}.warehouse_id" placeholder="Pilih Gudang Tujuan">
                                    @foreach($warehouses as $wh)
                                        @php
                                            $isDisabled = in_array($wh->id, $selectedWarehouses) && $dist['warehouse_id'] != $wh->id;
                                        @endphp
                                        <flux:select.option value="{{ $wh->id }}" :disabled="$isDisabled">{{ $wh->name }}{{ $isDisabled ? ' (Sudah dipilih)' : '' }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            
                            @if(!empty($dist['warehouse_id']))
                                <div class="w-24 shrink-0 transition-all duration-300">
                                    <flux:input type="number" wire:model.live.debounce.300ms="distributions.{{ $index }}.qty" min="1" max="{{ $order->requested_qty - $order->fulfilled_qty }}" placeholder="Qty" />
                                </div>
                            @else
                                <div class="w-24 shrink-0"></div>
                            @endif
                            
                            @if(count($distributions) > 1)
                                <flux:button variant="subtle" icon="trash" class="shrink-0 mt-0.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10" wire:click="removeDistribution({{ $index }})" />
                            @else
                                <div class="w-10 shrink-0"></div>
                            @endif
                        </div>
                    @endforeach
                </div>
                
                @if($errors->has('distributions.*.warehouse_id'))
                    <div class="text-sm text-red-500">Mohon pastikan semua pilihan gudang terisi.</div>
                @endif
                
                @if($errors->has('distributions.*.qty'))
                    <div class="text-sm text-red-500">Mohon pastikan semua jumlah/qty terisi minimal 1.</div>
                @endif

                <flux:button variant="subtle" icon="plus" size="sm" class="w-full text-zinc-500 border-dashed" wire:click="addDistribution" :disabled="$this->isMaxQtyReached">Tambah Alokasi Gudang</flux:button>
            </div>

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <flux:textarea wire:model="notes" label="Catatan (Opsional)" />
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="save">Terima ke Gudang</flux:button>
            </div>
        @endif
    </div>
</flux:modal>
