<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'distributions' => [],
    'warehouses' => []
]);

on(['open-allocate-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    
    $remaining = $this->order ? ($this->order->requested_qty) : 1;
    
    $this->distributions = [
        ['warehouse_id' => '', 'qty' => $remaining]
    ];
    $this->warehouses = DB::table('warehouses')->get();
    $this->show = true;
}]);

$addDistribution = function () {
    $this->distributions[] = ['warehouse_id' => '', 'qty' => null];
};

$removeDistribution = function ($index) {
    unset($this->distributions[$index]);
    $this->distributions = array_values($this->distributions);
};

$save = function () {
    abort_unless(auth()->user()->can('inventory.stock.create'), 403);
    
    $this->validate([
        'distributions' => 'required|array|min:1',
        'distributions.*.warehouse_id' => 'required',
        'distributions.*.qty' => 'required|numeric|min:1'
    ], [
        'distributions.*.warehouse_id.required' => 'Gudang tujuan wajib dipilih.',
        'distributions.*.qty.min' => 'Jumlah minimal 1.'
    ]);
    
    $totalDistQty = collect($this->distributions)->sum('qty');
    $requestedQty = $this->order->requested_qty;
    
    if ($totalDistQty !== $requestedQty) {
        \Flux::toast("Total alokasi gudang ($totalDistQty) tidak cocok dengan kuantitas pesanan ($requestedQty).", variant: 'danger');
        return;
    }

    if ($this->order) {
        DB::transaction(function () {
            $ord = $this->order;
            
            // Helper for generating suffixes A, B, C...
            $baseNumber = preg_replace('/-[A-Z]$/', '', $ord->order_number);
            $existingOrders = \Modules\Production\Models\ProductionOrder::where('order_number', 'like', $baseNumber . '-%')->pluck('order_number');
            $maxChar = '@';
            foreach ($existingOrders as $existing) {
                $parts = explode('-', $existing);
                $lastPart = end($parts);
                if (strlen($lastPart) === 1 && ctype_alpha($lastPart)) {
                    if ($lastPart > $maxChar) $maxChar = $lastPart;
                }
            }
            $getNextSuffix = function() use (&$maxChar) {
                if ($maxChar === '@') { $maxChar = 'A'; } 
                else { $maxChar = chr(ord($maxChar) + 1); }
                return $maxChar;
            };

            // Loop distributions to split order
            $first = true;
            $oldNotes = $ord->notes;
            
            foreach ($this->distributions as $dist) {
                $qty = $dist['qty'];
                $wh_id = $dist['warehouse_id'];
                
                if ($first) {
                    $ord->requested_qty = $qty;
                    $ord->fulfilled_qty = 0; // reset
                    $ord->target_warehouse_id = $wh_id;
                    $ord->order_number = $baseNumber . '-' . $getNextSuffix();
                    $ord->save();
                    $first = false;
                } else {
                    $newOrder = $ord->replicate();
                    $newOrder->requested_qty = $qty;
                    $newOrder->fulfilled_qty = 0;
                    $newOrder->target_warehouse_id = $wh_id;
                    $newOrder->order_number = $baseNumber . '-' . $getNextSuffix();
                    $newOrder->notes = $oldNotes;
                    $newOrder->save();
                    
                    // Replicate histories
                    $histories = \Modules\Production\Models\ProductionOrderHistory::where('production_order_id', $ord->id)->get();
                    foreach($histories as $hist) {
                        $newHist = $hist->replicate();
                        $newHist->production_order_id = $newOrder->id;
                        $newHist->save();
                    }
                }
            }
        });
        
        $this->dispatch('status-updated');
        $this->show = false;
        \Flux::toast("Alokasi berhasil. Perintah penerimaan fisik telah diteruskan ke gudang cabang.", variant: 'success');
    }
};
?>

<flux:modal wire:model="show" class="md:w-[600px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Alokasi Gudang Tujuan</flux:heading>
            <flux:subheading>Atur rute penempatan barang sebelum diterima oleh gudang cabang.</flux:subheading>
        </div>

        @if($order)
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="font-bold text-zinc-900 dark:text-white">{{ $order->item->name }}</div>
                <div class="flex justify-between text-sm mt-2">
                    <div>No. Pesanan: <span class="font-bold">{{ $order->order_number }}</span></div>
                    <div>Total Barang: <span class="font-bold text-blue-600">{{ $order->requested_qty }}</span></div>
                </div>
            </div>

            <div class="space-y-4">
                <flux:heading size="md">Bagi Rute Pengiriman (Total: {{ $order->requested_qty }})</flux:heading>
                
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
                            
                            <div class="w-24 shrink-0 transition-all duration-300">
                                <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live.debounce.300ms="distributions.{{ $index }}.qty" min="1" max="{{ $order->requested_qty }}" placeholder="Qty" />
                            </div>
                            
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

                <flux:button variant="subtle" icon="plus" size="sm" class="w-full text-zinc-500 border-dashed" wire:click="addDistribution">Tambah Rute Gudang</flux:button>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-6">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button icon="check" variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled"> Simpan Alokasi </flux:button>
            </div>
        @endif
    </div>
</flux:modal>
