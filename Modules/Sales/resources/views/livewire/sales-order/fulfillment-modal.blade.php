<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;
use Modules\Inventory\Models\ItemLabel;
use Modules\Sales\Models\SalesOrderFulfillment;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
    'items' => [], // Array of items with inputs
    'manualBarcode' => '',
]);

$refreshItems = function() {
    if (!$this->order) return;
    
    $newItems = [];
    $collisionDetected = false;

    foreach ($this->order->items as $orderItem) {
        $existing = null;
        foreach ($this->items as $oldItem) {
            if ($oldItem['item_id'] === $orderItem->item_id) {
                $existing = $oldItem;
                break;
            }
        }

        $neededQty = $orderItem->qty;
        $stock = DB::table('item_warehouse')->where('item_id', $orderItem->item_id)->sum('stock') ?? 0;
        
        // Cek jika sudah pernah difulfill sebelumnya
        $alreadyConsumed = abs(DB::table('stock_movements')
            ->where('reference_number', $this->order->so_number)
            ->where('item_id', $orderItem->item_id)
            ->where('type', 'out')
            ->sum('quantity') ?? 0);
        
        $remainingNeeded = max(0, $neededQty - $alreadyConsumed);
        
        $currentInputQty = $existing ? $existing['input_qty'] : 0;
        $currentScanned = $existing ? $existing['scanned_labels'] : [];

        if (!$orderItem->item->requires_label) {
            if ($currentInputQty > $stock) {
                $currentInputQty = 0;
                $collisionDetected = true;
            }
        } else {
            if (count($currentScanned) > $stock) {
                $currentScanned = [];
                $currentInputQty = 0;
                $collisionDetected = true;
            }
        }
        
        $newItems[] = [
            'so_item_id' => $orderItem->id,
            'item_id' => $orderItem->item_id,
            'name' => $orderItem->item->name,
            'is_custom' => !empty($orderItem->custom_attributes) || !empty($orderItem->custom_attachments) || str_contains($orderItem->notes ?? '', '[CUSTOM]'),
            'requires_label' => $orderItem->item->requires_label,
            'needed' => $neededQty,
            'already_consumed' => $alreadyConsumed,
            'remaining_needed' => $remainingNeeded,
            'stock' => $stock,
            'input_qty' => $currentInputQty,
            'scanned_labels' => $currentScanned,
        ];
    }
    
    $this->items = $newItems;

    if ($collisionDetected && $this->show) {
        \Flux::toast('PERINGATAN: Stok baru saja diambil pengguna lain! Input di-reset.', variant: 'danger');
    }
};

on(['open-fulfillment-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with('items.item')->find($orderId);
    $this->notes = '';
    $this->items = [];
    
    $this->refreshItems();
    $this->show = true;
}]);

on(['echo:inventory,InventoryUpdated' => function () {
    if ($this->show) {
        $this->refreshItems();
    }
}]);

on(['process-barcode' => function ($code) {
    if (!$this->show) return;

    $code = trim($code);
    if (empty($code)) return;

    $label = ItemLabel::with('item')->where('label_code', $code)->first();

    if (!$label) {
        \Flux::toast('Barcode tidak terdaftar.', variant: 'danger');
        $this->dispatch('scan-error');
        return;
    }

    if ($label->status !== 'in_stock') {
        \Flux::toast("Barcode {$code} tidak tersedia (status: {$label->status}).", variant: 'danger');
        $this->dispatch('scan-error');
        return;
    }

    $itemIndex = null;
    foreach ($this->items as $idx => $material) {
        if ($material['item_id'] === $label->item_id) {
            $itemIndex = $idx;
            break;
        }
    }

    if ($itemIndex === null) {
        \Flux::toast("Bahan {$label->item->name} tidak ada dalam pesanan ini.", variant: 'danger');
        return;
    }

    $material = $this->items[$itemIndex];

    if (!$material['requires_label']) {
        \Flux::toast("Bahan {$label->item->name} tidak membutuhkan scan barcode.", variant: 'warning');
        return;
    }

    $alreadyScanned = collect($material['scanned_labels'])->contains('id', $label->id);
    if ($alreadyScanned) {
        \Flux::toast("Barcode {$code} sudah di-scan sebelumnya.", variant: 'warning');
        return;
    }

    if ($material['input_qty'] >= $material['needed']) {
        \Flux::toast("Kebutuhan bahan {$label->item->name} sudah terpenuhi.", variant: 'warning');
        return;
    }

    $this->items[$itemIndex]['scanned_labels'][] = [
        'id' => $label->id,
        'code' => $label->label_code,
        'warehouse_id' => $label->warehouse_id
    ];
    $this->items[$itemIndex]['input_qty']++;

    \Flux::toast("Berhasil scan: {$label->item->name} ({$code})", variant: 'success');
    $this->dispatch('scan-success');
}]);

$removeScannedLabel = function($itemIndex, $labelIndex) {
    unset($this->items[$itemIndex]['scanned_labels'][$labelIndex]);
    $this->items[$itemIndex]['scanned_labels'] = array_values($this->items[$itemIndex]['scanned_labels']);
    $this->items[$itemIndex]['input_qty']--;
};

$save = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    if (!$this->order) return;

    // Validation
    foreach ($this->items as $material) {
        $inputQty = (int) $material['input_qty'];
        if ($inputQty < 0) {
            \Flux::toast("Input kuantitas untuk {$material['name']} tidak valid.", variant: 'danger');
            return;
        }
        if ($inputQty > $material['remaining_needed']) {
            \Flux::toast("Kuantitas {$material['name']} melebihi sisa kekurangan.", variant: 'danger');
            return;
        }
        if ($inputQty > $material['stock']) {
            \Flux::toast("Stok {$material['name']} tidak mencukupi.", variant: 'danger');
            return;
        }
        if ($material['requires_label'] && count($material['scanned_labels']) !== $inputQty) {
            \Flux::toast("Jumlah scan barcode untuk {$material['name']} tidak sesuai.", variant: 'danger');
            return;
        }
    }

    $hasDeficit = false;
    
    DB::transaction(function () use (&$hasDeficit) {
        $inventoryService = app(\App\Services\InventoryService::class);
        foreach ($this->items as $material) {
            $inputQty = (int) $material['input_qty'];
            $deficit = max(0, $material['remaining_needed'] - $inputQty);
            
            if ($deficit > 0) {
                $hasDeficit = true;
            }

            if ($inputQty > 0) {
                if ($material['requires_label']) {
                    foreach ($material['scanned_labels'] as $sl) {
                        $labelModel = ItemLabel::find($sl['id']);
                        $labelModel->status = 'booked'; // Set booked temporarily until shipping
                        $labelModel->notes = 'Booked untuk SO: ' . $this->order->so_number;
                        $labelModel->save();
                        
                        SalesOrderFulfillment::create([
                            'sales_order_id' => $this->order->id,
                            'sales_order_item_id' => $material['so_item_id'],
                            'item_id' => $material['item_id'],
                            'item_label_id' => $sl['id'],
                            'scanned_qty' => 1,
                            'scanned_by' => auth()->id()
                        ]);

                        $inventoryService->adjustStock(
                            $material['item_id'],
                            $sl['warehouse_id'],
                            1,
                            'out',
                            $this->order->so_number,
                            'Fulfillment SO. Label: ' . $sl['code']
                        );
                    }
                } else {
                    $remainingToDeduct = $inputQty;
                    $warehouses = DB::table('item_warehouse')
                        ->where('item_id', $material['item_id'])
                        ->where('stock', '>', 0)
                        ->orderBy('stock', 'desc')
                        ->lockForUpdate()
                        ->get();

                    $totalStock = $warehouses->sum('stock');
                    if ($totalStock < $remainingToDeduct) {
                        throw new \Exception("Stok aktual tidak mencukupi.");
                    }

                    foreach ($warehouses as $wh) {
                        if ($remainingToDeduct <= 0) break;
                        $deduct = min($wh->stock, $remainingToDeduct);
                        
                        SalesOrderFulfillment::create([
                            'sales_order_id' => $this->order->id,
                            'sales_order_item_id' => $material['so_item_id'],
                            'item_id' => $material['item_id'],
                            'scanned_qty' => $deduct,
                            'scanned_by' => auth()->id()
                        ]);

                        $inventoryService->adjustStock(
                            $material['item_id'],
                            $wh->warehouse_id,
                            $deduct,
                            'out',
                            $this->order->so_number,
                            'Fulfillment SO.'
                        );
                        $remainingToDeduct -= $deduct;
                    }
                }
            }
        }
        
        $this->order->status = 'packing';
        if ($this->notes) {
            $this->order->notes = $this->order->notes . "\n[Fulfillment Note]: " . $this->notes;
        }
        if ($hasDeficit) {
            $this->order->notes = $this->order->notes . "\n[WARNING]: Fulfillment Parsial (Tidak Lengkap).";
        }
        $this->order->save();
    });

    if ($hasDeficit) {
        \Flux::toast('Fulfillment Parsial tersimpan! SO berlanjut ke tahap Packing dengan stok yang ada.', variant: 'warning');
    } else {
        \Flux::toast('Barang lengkap ditarik dari gudang. Siap untuk Di-Packing.', variant: 'success');
    }

    $this->dispatch('status-updated');
    \App\Events\KanbanUpdated::safeDispatch('sales_order');
    \App\Events\InventoryUpdated::safeDispatch('Fulfillment SO ' . $this->order->so_number . ' diproses');
    $this->show = false;
};
?>

<div>
<flux:modal wire:model="show" class="w-full md:w-[40rem] md:max-w-2xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center">
                <flux:icon.shopping-cart class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Fulfillment Penjualan</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    Ambil barang untuk SO <strong>{{ $order->so_number }}</strong> ({{ $order->customer_name }}).
                </flux:subheading>
            </div>
        </div>
        
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3 bg-zinc-100 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="flex-1 w-full">
                <flux:input 
                    wire:model="manualBarcode" 
                    x-on:keydown.enter="$dispatch('barcode-scanned', { code: $wire.manualBarcode }); $wire.manualBarcode = ''" 
                    placeholder="Ketik kode barcode manual, lalu tekan Enter..." 
                    icon="qr-code" 
                />
            </div>
            <div class="hidden sm:block text-xs font-bold text-zinc-400">ATAU</div>
            <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none" tooltip="Gunakan Kamera HP">
                Scanner
            </flux:button>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            @forelse($items as $index => $item)
                <div class="p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $item['input_qty'] >= $item['remaining_needed'] ? 'bg-green-50/50 dark:bg-green-900/10' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                    <div class="flex-1">
                        <div class="text-zinc-900 dark:text-zinc-100 font-medium text-base flex items-center gap-2 flex-wrap">
                            <span>{{ $item['name'] }}</span>
                            @if($item['is_custom'] ?? false)
                                <span class="text-[9px] font-black text-amber-600 bg-amber-100 border border-amber-200 px-1.5 py-0.5 rounded shadow-sm flex items-center gap-0.5 whitespace-nowrap">
                                    <flux:icon.sparkles class="w-3 h-3" /> CUSTOM
                                </span>
                            @endif
                            @if($item['input_qty'] >= $item['remaining_needed'])
                                <flux:badge size="sm" color="green">Cukup</flux:badge>
                            @endif
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 flex flex-wrap gap-3 items-center">
                            <div>Pesanan: <span class="font-bold text-rose-600 dark:text-rose-500">{{ $item['remaining_needed'] }}</span> <span class="text-xs text-zinc-400">(Dari {{ $item['needed'] }})</span></div>
                            <div class="border-l border-zinc-300 dark:border-zinc-600 pl-3">Tersedia di Gudang: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['stock'] }}</span></div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 self-end sm:self-auto shrink-0 w-full sm:w-32 justify-between sm:justify-end mt-2 sm:mt-0 pt-2 sm:pt-0 border-t sm:border-0 border-zinc-200 dark:border-zinc-700">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400 sm:hidden">Siap Kirim:</span>
                        <div class="w-24 sm:w-full">
                            @if($item['requires_label'])
                                <div class="text-center font-bold text-xl text-indigo-600 dark:text-indigo-400">
                                    {{ $item['input_qty'] }} <span class="text-xs text-zinc-500 font-normal">Scan</span>
                                </div>
                            @else
                                @if($item['stock'] <= 0)
                                    <div class="text-center text-sm font-bold text-red-500 bg-red-50 dark:bg-red-900/20 py-2 rounded-lg border border-red-100 dark:border-red-800">Kosong</div>
                                @else
                                    <flux:input type="number" 
                                        wire:model.live="items.{{ $index }}.input_qty" 
                                        min="0" 
                                        max="{{ min($item['remaining_needed'], $item['stock']) }}" 
                                        x-on:input="
                                            let maxVal = {{ min($item['remaining_needed'], $item['stock']) }};
                                            if(Number($el.value) > maxVal) { 
                                                $el.value = maxVal; 
                                                $el.dispatchEvent(new Event('input')); 
                                            }
                                        "
                                        class="w-full text-center" />
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                @if($item['requires_label'] && count($item['scanned_labels']) > 0)
                    <div class="mt-2 ml-2 pl-4 border-l-2 border-indigo-200 dark:border-indigo-900/50 flex flex-wrap gap-2">
                        @foreach($item['scanned_labels'] as $labelIndex => $sl)
                            <div class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-xs px-2 py-1 rounded border border-indigo-100 dark:border-indigo-800/50">
                                <flux:icon.qr-code class="w-3 h-3" />
                                {{ $sl['code'] }}
                                <button type="button" wire:click="removeScannedLabel({{ $index }}, {{ $labelIndex }})" class="ml-1 text-indigo-400 hover:text-red-500"><flux:icon.x-mark class="w-3 h-3" /></button>
                            </div>
                        @endforeach
                    </div>
                @endif
            @empty
                <div class="p-5 text-center text-zinc-500">Tidak ada barang.</div>
            @endforelse
        </div>

        @if(count($items) > 0)
        <div class="mt-4">
            <flux:textarea wire:model="notes" label="Catatan Tambahan" placeholder="Misal: Barang cacat, diganti stok lain..." />
        </div>

        @php
            $hasStockDeficit = false;
            $hasAnyInput = false;
            
            foreach($items as $item) {
                if ($item['input_qty'] > 0) $hasAnyInput = true;
                if ($item['remaining_needed'] > $item['input_qty']) {
                    $hasStockDeficit = true;
                }
            }
        @endphp

        <div class="mt-6 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
            <span class="text-xs text-zinc-500 text-center sm:text-left w-full sm:w-auto">
                <flux:icon.information-circle class="inline w-4 h-4 mr-1 text-blue-500" />
                @if($hasStockDeficit)
                    Pengiriman <strong class="text-amber-600 dark:text-amber-500">Parsial</strong>. Lanjutkan ke Packing dengan stok seadanya.
                @else
                    Semua barang lengkap dan siap di-Packing.
                @endif
            </span>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 sm:flex-none"> Batal </flux:button>
                @if(!$hasAnyInput)
                    <flux:button variant="primary" disabled class="flex-1 sm:flex-none opacity-50 cursor-not-allowed">Serahkan</flux:button>
                @elseif($hasStockDeficit)
                    <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="exclamation-triangle" class="flex-1 sm:flex-none bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700 border-amber-500 dark:border-amber-600 text-white">Serahkan Parsial</flux:button>
                @else
                    <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="check" class="flex-1 sm:flex-none">Lengkap, Serahkan</flux:button>
                @endif
            </div>
        </div>
        @endif
    </div>
    @endif
</flux:modal>

<div x-data @barcode-scanned.window="$wire.dispatch('process-barcode', { code: $event.detail.code })"></div>
<x-camera-scanner />

<div x-data="{
    playBeep(type) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            if (type === 'success') {
                osc.frequency.value = 1200;
                osc.type = 'sine';
                gain.gain.setValueAtTime(0.25, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
            } else {
                osc.frequency.value = 300;
                osc.type = 'square';
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.4);
            }
        } catch(e) {}
    }
}" @scan-success.window="playBeep('success')" @scan-error.window="playBeep('error')"></div>
</div>
