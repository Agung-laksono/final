<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
    'reject_notes' => '',
    'qty_good' => 0,
    'qty_reject' => 0,
]);

on(['open-qc-modal' => function ($orderId) {
    $this->reset(['notes', 'reject_notes']);
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    
    $remaining = $this->order ? ($this->order->requested_qty - $this->order->fulfilled_qty) : 1;
    $this->qty_good = $remaining;
    $this->qty_reject = 0;
    
    $this->show = true;
}]);

$updatedQtyGood = function($value) {
    if (!$this->order) return;
    $remaining = $this->order->requested_qty - $this->order->fulfilled_qty;
    $val = (int)$value;
    if ($val < 0) $val = 0;
    if ($val + $this->qty_reject > $remaining) {
        $val = $remaining - $this->qty_reject;
    }
    $this->qty_good = $val;
};

$updatedQtyReject = function($value) {
    if (!$this->order) return;
    $remaining = $this->order->requested_qty - $this->order->fulfilled_qty;
    $val = (int)$value;
    if ($val < 0) $val = 0;
    if ($val + $this->qty_good > $remaining) {
        $val = $remaining - $this->qty_good;
    }
    $this->qty_reject = $val;
};

$save = function () {
    abort_unless(auth()->user()->can('inventory.stock.create'), 403);
    
    // Validasi penugasan gudang
    $user = auth()->user();
    $isAuthorized = $user->hasRole('Super Admin') || $user->warehouses->pluck('id')->contains($this->order->target_warehouse_id);
    abort_unless($isAuthorized, 403, 'Anda tidak ditugaskan di gudang ini.');
    
    $remaining = $this->order->requested_qty - $this->order->fulfilled_qty;
    
    if ($this->qty_good + $this->qty_reject > $remaining) {
        \Flux::toast("Total tidak boleh melebihi sisa antrean ($remaining).", variant: 'danger');
        return;
    }
    if ($this->qty_good == 0 && $this->qty_reject == 0) {
        \Flux::toast("Mohon isi Qty Lolos atau Qty Cacat.", variant: 'danger');
        return;
    }
    
    if ($this->qty_reject > 0 && empty($this->reject_notes)) {
        $this->addError('reject_notes', 'Catatan/alasan cacat wajib diisi jika ada barang Reject.');
        return;
    }

    if ($this->order) {
        $generatedLabelIds = [];
        $generatedLabelsCount = 0;

        DB::transaction(function () use (&$generatedLabelIds, &$generatedLabelsCount, $remaining) {
            $inventoryService = app(\App\Services\InventoryService::class);
            $ord = $this->order;
            $qty_unprocessed = $remaining - ($this->qty_good + $this->qty_reject);
            $ordForStock = null;
            
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

            if ($qty_unprocessed > 0) {
                // PARTIAL RECEIVING (Floating Receipt)
                $ord->requested_qty = $qty_unprocessed;
                $ord->save(); // Original order stays as 'receiving' for the rest

                if ($this->qty_good > 0) {
                    $goodOrder = $ord->replicate();
                    $goodOrder->requested_qty = $this->qty_good;
                    $goodOrder->fulfilled_qty = $this->qty_good;
                    $goodOrder->order_number = $baseNumber . '-' . $getNextSuffix();
                    $goodOrder->status = 'completed';
                    $goodOrder->save();
                    $ordForStock = $goodOrder;
                    
                    $histories = \Modules\Production\Models\ProductionOrderHistory::where('production_order_id', $ord->id)->get();
                    foreach($histories as $hist) {
                        $newHist = $hist->replicate();
                        $newHist->production_order_id = $goodOrder->id;
                        $newHist->save();
                    }
                }
                
                if ($this->qty_reject > 0) {
                    $rejectOrder = $ord->replicate();
                    $rejectOrder->requested_qty = $this->qty_reject;
                    $rejectOrder->fulfilled_qty = 0;
                    $rejectOrder->order_number = $baseNumber . '-' . $getNextSuffix();
                    $rejectOrder->status = 'waiting_vendor';
                    $rejectOrder->notes = trim($ord->notes . "\n\n[QC REJECT] Dikembalikan oleh gudang sebagian. Alasan: " . $this->reject_notes);
                    $rejectOrder->save();
                    
                    $histories = \Modules\Production\Models\ProductionOrderHistory::where('production_order_id', $ord->id)->get();
                    foreach($histories as $hist) {
                        $newHist = $hist->replicate();
                        $newHist->production_order_id = $rejectOrder->id;
                        $newHist->save();
                    }
                }
            } else {
                // FULL RECEIVING (All items processed)
                if ($this->qty_reject == 0) {
                    // Case 1: All Good
                    $ord->status = 'completed';
                    $ord->fulfilled_qty += $this->qty_good;
                    $ord->save();
                    $ordForStock = $ord;
                } elseif ($this->qty_good == 0) {
                    // Case 2: All Reject
                    $ord->status = 'waiting_vendor'; 
                    $ord->notes = trim($ord->notes . "\n\n[QC REJECT] Semua barang ditolak oleh gudang. Alasan: " . $this->reject_notes);
                    $ord->save();
                } else {
                    // Case 3: Partial Good & Partial Reject
                    $oldNotes = $ord->notes;
                    $ord->requested_qty = $this->qty_good;
                    $ord->fulfilled_qty = $this->qty_good;
                    $ord->order_number = $baseNumber . '-' . $getNextSuffix();
                    $ord->status = 'completed';
                    $ord->save();
                    $ordForStock = $ord;
                    
                    $rejectOrder = $ord->replicate();
                    $rejectOrder->requested_qty = $this->qty_reject;
                    $rejectOrder->fulfilled_qty = 0;
                    $rejectOrder->order_number = $baseNumber . '-' . $getNextSuffix();
                    $rejectOrder->status = 'waiting_vendor';
                    $rejectOrder->notes = trim($oldNotes . "\n\n[QC REJECT] Dikembalikan oleh gudang sebagian. Alasan: " . $this->reject_notes);
                    $rejectOrder->save();
                    
                    $histories = \Modules\Production\Models\ProductionOrderHistory::where('production_order_id', $ord->id)->get();
                    foreach($histories as $hist) {
                        $newHist = $hist->replicate();
                        $newHist->production_order_id = $rejectOrder->id;
                        $newHist->save();
                    }
                }
            }

            // Adjust Stock & Generate Labels for Good Qty
            if ($this->qty_good > 0 && $ordForStock) {
                $qty = $this->qty_good;
                $wh_id = $ordForStock->target_warehouse_id;
                
                $inventoryService->adjustStock(
                    $ordForStock->item_id,
                    $wh_id,
                    $qty, 
                    'in', 
                    $ordForStock->order_number, 
                    'Penerimaan QC Lolos hasil produksi. ' . $this->notes
                );

                // Generate Labels if required
                if ($ordForStock->item->requires_label) {
                    for ($i = 0; $i < $qty; $i++) {
                        do {
                            $code = strtoupper(\Illuminate\Support\Str::random(6));
                        } while (\Modules\Inventory\Models\ItemLabel::where('label_code', $code)->exists());

                        $label = \Modules\Inventory\Models\ItemLabel::create([
                            'item_id' => $ordForStock->item_id,
                            'label_code' => $code,
                            'status' => 'in_stock',
                            'warehouse_id' => $wh_id,
                            'notes' => 'QC Lolos Produksi: ' . $ordForStock->order_number,
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
        
        if ($this->qty_good > 0 && $this->qty_reject > 0) {
            \Flux::toast("Berhasil! $this->qty_good diterima gudang. $this->qty_reject diretur ke produksi.", variant: 'success');
        } elseif ($this->qty_good > 0) {
            \Flux::toast("Penerimaan gudang berhasil dicatat.", variant: 'success');
        } else {
            \Flux::toast("Semua barang diretur kembali ke produksi.", variant: 'warning');
        }

        if (count($generatedLabelIds) > 0) {
            $this->dispatch('open-print-labels', labelIds: $generatedLabelIds);
        }
    }
};
?>

<flux:modal wire:model="show" class="md:w-[700px]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Quality Control & Penerimaan Gudang</flux:heading>
            <flux:subheading>Validasi fisik barang dari vendor/produksi sebelum masuk stok.</flux:subheading>
        </div>

        @if($order)
            <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="font-bold text-zinc-900 dark:text-white">{{ $order->item->name }}</div>
                <div class="flex justify-between text-sm mt-2">
                    <div>No. Pesanan: <span class="font-bold">{{ $order->order_number }}</span></div>
                    <div>Target Validasi: <span class="font-bold text-blue-600">{{ $order->requested_qty - $order->fulfilled_qty }}</span></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-xl border border-emerald-200 dark:border-emerald-800/50">
                    <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live.debounce.300ms="qty_good" 
                        label="Qty Lolos (Good)" 
                        min="0" 
                        max="{{ $order->requested_qty - $order->fulfilled_qty - $qty_reject }}" 
                        x-on:input="
                            let maxVal = Math.max(0, {{ $order->requested_qty - $order->fulfilled_qty }} - Number($wire.qty_reject || 0));
                            if(Number($el.value) > maxVal) {
                                $el.value = maxVal > 0 ? maxVal : '';
                                $el.dispatchEvent(new Event('input'));
                            }
                        "
                        description="Barang mulus masuk ke stok" />
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-xl border border-red-200 dark:border-red-800/50">
                    <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model.live.debounce.300ms="qty_reject" 
                        label="Qty Cacat (Reject)" 
                        min="0" 
                        max="{{ $order->requested_qty - $order->fulfilled_qty - $qty_good }}" 
                        x-on:input="
                            let maxVal = Math.max(0, {{ $order->requested_qty - $order->fulfilled_qty }} - Number($wire.qty_good || 0));
                            if(Number($el.value) > maxVal) {
                                $el.value = maxVal > 0 ? maxVal : '';
                                $el.dispatchEvent(new Event('input'));
                            }
                        "
                        description="Barang rusak, diretur ke Produksi" />
                </div>
            </div>
            
            @if($qty_reject > 0)
                <div class="border border-red-200 dark:border-red-800 p-4 rounded-xl bg-red-50/50 dark:bg-red-900/10">
                    <flux:textarea wire:model="reject_notes" label="Alasan Retur (Wajib)" placeholder="Contoh: Lecet di bagian kaki, finishing kurang rata..." />
                </div>
            @endif

            @if($qty_good > 0)
                <div class="space-y-4">
                    <flux:heading size="md">Gudang Tujuan (Terkunci)</flux:heading>
                    
                    <div class="flex items-center justify-between bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-3 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                                <flux:icon.building-storefront class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $order->targetWarehouse->name ?? 'Unknown' }}</div>
                                <div class="text-xs text-zinc-500">Alokasi otomatis dari Kepala Inventory</div>
                            </div>
                        </div>
                        <div class="font-bold text-xl text-emerald-600">+{{ $qty_good }}</div>
                    </div>
                </div>

                <div class="mt-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                    <flux:textarea wire:model="notes" label="Catatan Penerimaan Gudang (Opsional)" />
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-700 mt-6">
                <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
                <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled">Sahkan QC & Terima</flux:button>
            </div>
        @endif
    </div>
</flux:modal>
