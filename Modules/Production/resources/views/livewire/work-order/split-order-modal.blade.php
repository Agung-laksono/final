<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Production\Models\ProductionOrderHistory;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'split_qty' => null,
]);

on(['open-split-order-modal' => function ($orderId) {
    $this->reset(['split_qty']);
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with(['item'])->find($this->orderId);
    if ($this->order) {
        $this->split_qty = $this->order->requested_qty - 1; // Default to max possible split
    }
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'split_qty' => 'required|numeric|min:1|max:' . ($this->order->requested_qty - 1),
    ]);
    
    $ord = $this->order;
    $remaining_qty = $ord->requested_qty - $this->split_qty;
    
    \DB::transaction(function () use ($ord, $remaining_qty) {
        // Tentukan Suffix A, B, C
        $baseNumber = preg_replace('/-[A-Z]$/', '', $ord->order_number);
        $existingOrders = ProductionOrder::where('order_number', 'like', $baseNumber . '-%')->pluck('order_number');
        
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
            $splitSuffix = 'A';
            $remainingSuffix = 'B';
        } else {
            $splitSuffix = chr(ord($maxChar) + 1);
            $remainingSuffix = chr(ord($maxChar) + 2);
        }
        
        // Ambil progress bahan saat ini
        $materialProgress = 0;
        $existingNotes = $ord->notes ?? '';
        if (preg_match('/\[MaterialProgress:\s*(\d+)\]/', $existingNotes, $matches)) {
            $materialProgress = (int)$matches[1];
        }
        
        $splitMaterialProgress = min($materialProgress, $this->split_qty);
        $remainingMaterialProgress = max(0, $materialProgress - $this->split_qty);

        // Create A (Split / Didahulukan)
        $splitOrder = $ord->replicate();
        $splitOrder->requested_qty = $this->split_qty;
        $splitOrder->order_number = $baseNumber . '-' . $splitSuffix;
        $splitOrder->notes = $ord->notes . "\n[Dipecah dari " . $ord->order_number . "]";
        
        // Update Material Progress Note
        if ($materialProgress > 0) {
            $splitOrder->notes = preg_replace('/\[MaterialProgress:\s*\d+\]/', "[MaterialProgress: {$splitMaterialProgress}]", $splitOrder->notes);
        }
        
        // Auto-promote status if materials are enough
        if ($splitMaterialProgress >= $splitOrder->requested_qty && in_array($splitOrder->status, ['waiting_material', 'material_fulfillment'])) {
            $splitOrder->status = 'material_issued';
        }
        
        $splitOrder->save();
        
        // Replicate histories for the split order
        $histories = ProductionOrderHistory::where('production_order_id', $ord->id)->get();
        foreach($histories as $hist) {
            $newHist = $hist->replicate();
            $newHist->production_order_id = $splitOrder->id;
            $newHist->save();
        }
        
        // Update B (Remaining / Sisa)
        $ord->requested_qty = $remaining_qty;
        $ord->order_number = $baseNumber . '-' . $remainingSuffix;
        $ord->notes = $ord->notes . "\n[Sisa dari pecahan " . $baseNumber . "-" . $splitSuffix . "]";
        
        // Update Material Progress Note
        if ($materialProgress > 0) {
            $ord->notes = preg_replace('/\[MaterialProgress:\s*\d+\]/', "[MaterialProgress: {$remainingMaterialProgress}]", $ord->notes);
        }
        
        // Adjust status based on new progress
        if ($remainingMaterialProgress >= $ord->requested_qty && in_array($ord->status, ['waiting_material', 'material_fulfillment'])) {
            $ord->status = 'material_issued';
        } elseif ($remainingMaterialProgress < $ord->requested_qty && $ord->status === 'material_issued') {
            $ord->status = 'waiting_material';
        }
        
        $ord->save();
    });

    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('production_order');
    $this->show = false;
    
    // Auto-close detail modal if it's open, because the ID just changed
    $this->dispatch('modal-closed'); // This will trigger listeners to close modals
    
    \Flux::toast('SPK berhasil dipecah menjadi bagian ' . $this->split_qty . ' unit dan ' . $remaining_qty . ' unit.', variant: 'success');
};
?>

<div>
<flux:modal wire:model="show" class="md:w-[450px]">
    @if($order)
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Pecah SPK (Split Order)</flux:heading>
                <flux:subheading>Barang: <strong class="text-zinc-900 dark:text-white">{{ $order->item->name }}</strong> ({{ $order->order_number }})</flux:subheading>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-200 dark:border-amber-800 text-xs text-amber-700 dark:text-amber-400 mb-4">
                <flux:icon.information-circle class="w-4 h-4 inline-block mb-0.5" />
                Pemecahan SPK berguna jika sebagian barang harus segera diproses (karena bahan kurang, atau kapasitas mesin terbatas).
            </div>

            <div class="space-y-4">
                <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live="split_qty" label="Kuantitas yang Didahulukan (Jalan Duluan)" max="{{ $order->requested_qty - 1 }}" min="1" description="Total SPK saat ini adalah {{ $order->requested_qty }} unit." />
                
                @if($split_qty && $split_qty > 0 && $split_qty < $order->requested_qty)
                    <div class="mt-4 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50 dark:bg-zinc-800/50 space-y-2">
                        <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-2">Simulasi Hasil Pemecahan:</div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-medium text-zinc-900 dark:text-white">SPK Pertama (Didahulukan)</span>
                            <flux:badge color="emerald" size="sm">{{ $split_qty }} Unit</flux:badge>
                        </div>
                        <div class="flex justify-between items-center text-sm border-t border-zinc-200 dark:border-zinc-700 pt-2">
                            <span class="font-medium text-zinc-900 dark:text-white">SPK Kedua (Sisa / Menunggu)</span>
                            <flux:badge color="zinc" size="sm">{{ $order->requested_qty - $split_qty }} Unit</flux:badge>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button icon="arrows-right-left" variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled"> Proses Pemecahan </flux:button>
            </div>
        </div>
    @endif
</flux:modal>
</div>
