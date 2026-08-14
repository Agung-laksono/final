<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;

state([
    'show' => false,
    'orderId' => null,
    'poId' => null,
    'order' => null,
    'orders' => [],
    'phase' => '', // 'maklon' or 'internal'
    'next_action' => 'queue', // 'queue' or 'receiving'
    'isBulk' => false,
    'completed_qty' => null,
]);

on(['open-finish-phase-modal' => function ($orderId, $phase = 'maklon') {
    $this->reset(['next_action', 'poId', 'isBulk', 'orders']);
    $this->orderId = $orderId;
    $this->phase = $phase;
    $this->order = ProductionOrder::with(['item', 'purchaseOrder.vendor'])->find($this->orderId);
    $this->completed_qty = $this->order->requested_qty;
    $this->show = true;
}]);

on(['open-finish-phase-bulk-modal' => function ($poId, $phase = 'maklon') {
    $this->reset(['next_action', 'orderId', 'order']);
    $this->poId = $poId;
    $this->phase = $phase;
    $this->isBulk = true;
    
    // Ambil semua order yang in_production pada PO ini
    $this->orders = collect(ProductionOrder::with(['item', 'purchaseOrder.vendor'])
        ->where('purchase_order_id', $this->poId)
        ->where('status', 'in_production')
        ->get());
        
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    if (!$this->isBulk && $this->order) {
        $this->validate([
            'completed_qty' => 'required|numeric|min:1|max:' . $this->order->requested_qty,
        ]);
    }
    
    $ordersToProcess = [];

    if ($this->isBulk) {
        $ordersToProcess = collect($this->orders);
    } else {
        $ord = $this->order;
        if ($this->completed_qty < $ord->requested_qty) {
            // Split Logic
            $remaining_qty = $ord->requested_qty - $this->completed_qty;
            
            // Tentukan Suffix A, B, C
            $baseNumber = preg_replace('/-[A-Z]$/', '', $ord->order_number);
            $existingOrders = \Modules\Production\Models\ProductionOrder::where('order_number', 'like', $baseNumber . '-%')->pluck('order_number');
            
            $maxChar = '@';
            foreach ($existingOrders as $existing) {
                $parts = explode('-', $existing);
                $lastPart = end($parts);
                if (strlen($lastPart) === 1 && ctype_alpha($lastPart)) {
                    if ($lastPart > $maxChar) {
                        $maxChar = $lastPart;
                    }
                }
            }

            if ($maxChar === '@') {
                $completedSuffix = 'A';
                $remainingSuffix = 'B';
            } else {
                $completedSuffix = chr(ord($maxChar) + 1);
                $remainingSuffix = chr(ord($maxChar) + 2);
            }
            
            // Create A (Completed)
            $completedOrder = $ord->replicate();
            $completedOrder->requested_qty = $this->completed_qty;
            $completedOrder->order_number = $baseNumber . '-' . $completedSuffix;
            $completedOrder->save();
            
            // Replicate histories for the split completed order
            $histories = \Modules\Production\Models\ProductionOrderHistory::where('production_order_id', $ord->id)->get();
            foreach($histories as $hist) {
                $newHist = $hist->replicate();
                $newHist->production_order_id = $completedOrder->id;
                $newHist->save();
            }
            
            // Update B (Remaining)
            $ord->requested_qty = $remaining_qty;
            $ord->order_number = $baseNumber . '-' . $remainingSuffix;
            $ord->save();
            
            $ordersToProcess = collect([$completedOrder]);
        } else {
            $ordersToProcess = collect([$ord]);
        }
    }

    foreach ($ordersToProcess as $ord) {
        if ($this->next_action === 'queue') {
            $ord->status = 'waiting_vendor'; // Kembali ke Antrean Proses
        } else {
            $ord->status = 'receiving'; // Selesai sepenuhnya, masuk gudang
        }

        $completedPhaseStr = $ord->phase_type ?: $this->phase;
        $vendorName = $ord->purchaseOrder ? $ord->purchaseOrder->vendor->name : 'Internal';
        
        // Log to text notes for quick view
        $ord->notes = trim($ord->notes . "\n[History: " . ucfirst($completedPhaseStr) . " | " . $vendorName . "]");
        $ord->save();

        // Save to pure database relational history
        \Modules\Production\Models\ProductionOrderHistory::create([
            'production_order_id' => $ord->id,
            'phase' => $completedPhaseStr,
            'purchase_order_id' => $ord->purchase_order_id,
            'vendor_id' => $ord->purchaseOrder ? $ord->purchaseOrder->vendor_id : null,
            'status' => 'completed',
        ]);
        
        // Update received_quantity on the PurchaseOrderItem so the SPK knows it's finished
        if ($ord->purchase_order_id) {
            $poi = \Modules\Purchase\Models\PurchaseOrderItem::where('purchase_order_id', $ord->purchase_order_id)
                ->where('item_id', $ord->item_id)
                ->first();
            if ($poi) {
                $poi->received_quantity += $ord->requested_qty;
                $poi->save();
            }
        }

        // Bypassing QC Approval -> Direct to Warehouse
        if ($this->next_action !== 'queue' && !requires_qc_approval()) {
            $ord->status = 'completed';
            $ord->fulfilled_qty = $ord->requested_qty;
            $ord->save();

            $inventoryService = app(\App\Services\InventoryService::class);
            $wh_id = $ord->target_warehouse_id ?? 1; // Default to main warehouse if null
            $inventoryService->adjustStock(
                $ord->item_id,
                $wh_id,
                $ord->requested_qty,
                'in',
                $ord->order_number,
                'Penyelesaian Produksi Otomatis (QC Bypass). ' . $ord->notes
            );
            
            // Generate Labels if required
            if ($ord->item->requires_label) {
                for ($i = 0; $i < $ord->requested_qty; $i++) {
                    do {
                        $code = strtoupper(\Illuminate\Support\Str::random(6));
                    } while (\Modules\Inventory\Models\ItemLabel::where('label_code', $code)->exists());

                    \Modules\Inventory\Models\ItemLabel::create([
                        'item_id' => $ord->item_id,
                        'label_code' => $code,
                        'status' => 'in_stock',
                        'warehouse_id' => $wh_id,
                        'notes' => 'Otomatis Lolos Produksi (Bypass QC): ' . $ord->order_number,
                    ]);
                }
            }
        }
    }

        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        $this->show = false;
        
        if ($this->next_action === 'queue') {
            \Flux::toast(($this->isBulk ? count($this->orders) . ' barang' : 'Pekerjaan') . ' dikembalikan ke Antrean untuk fase selanjutnya.', variant: 'success');
        } else {
            \Flux::toast(($this->isBulk ? count($this->orders) . ' barang' : 'Pekerjaan') . ' selesai dan diteruskan ke Penerimaan Gudang.', variant: 'success');
        }
};

?>

<div>
<flux:modal wire:model="show" class="md:w-[500px]">
    @if($order || $isBulk)
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Selesaikan Fase {{ $phase === 'maklon' ? 'Jasa Luar' : 'Internal' }}</flux:heading>
                @if($isBulk)
                    <flux:subheading>Tentukan langkah selanjutnya untuk <strong>{{ count($this->orders) }} barang</strong> di dalam SPK ini.</flux:subheading>
                @else
                    <flux:subheading>Tentukan langkah selanjutnya untuk <strong>{{ $order->item->name }}</strong> ({{ $order->order_number }}).</flux:subheading>
                @endif
            </div>

            <div class="space-y-4">
                @if(!$isBulk)
                    <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model="completed_qty" label="Berapa Kuantitas yang dikirim saat ini?" max="{{ $order->requested_qty }}" min="1" description="Ubah angka ini jika vendor mengirimkan barang secara parsial (sebagian). Sistem akan membelah pesanan ini otomatis." />
                @endif
                
                <flux:radio.group wire:model="next_action" variant="cards" class="flex-col">
                    <flux:radio value="queue" icon="queue-list" label="Lanjut ke Fase Lain (Kembali ke Antrean)" description="Barang belum selesai sepenuhnya (misal: baru selesai Finishing, masih butuh Jok). Akan dikembalikan ke kolom Antrean Proses." />
                    <flux:radio value="receiving" icon="check-badge" label="Selesai Sepenuhnya (Penerimaan Gudang)" description="Barang sudah jadi 100% dan siap dimasukkan ke stok gudang." />
                </flux:radio.group>
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button icon="check" variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled"> Simpan Penyelesaian </flux:button>
            </div>
        </div>
    @endif
</flux:modal>
</div>
