<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderFulfillment;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'items' => [], // To track fulfillment progress
]);

on(['open-fulfillment-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->loadOrder();
    $this->show = true;
}]);

$loadOrder = function() {
    $this->order = SalesOrder::with(['customer', 'items.item', 'fulfillments'])->find($this->orderId);
    if ($this->order) {
        $this->items = $this->order->items->map(function ($item) {
            $fulfilled = $this->order->fulfillments->where('sales_order_item_id', $item->id)->sum('scanned_qty');
            return [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'name' => $item->item->name,
                'code' => $item->item->code,
                'requires_label' => (bool) $item->item->requires_label,
                'ordered_qty' => $item->qty,
                'fulfilled_qty' => $fulfilled,
                'input_qty' => 0, // Default to 0 so scanning increments it
                'scanned_labels' => [], // Array of label_codes and label ids
            ];
        })->toArray();
    }
};

on(['barcode-scanned' => function ($code) {
    if (!$this->show) return;
    if (empty($code)) return;

    // 1. Cek apakah ini label fisik
    $label = \Modules\Inventory\Models\ItemLabel::where('label_code', $code)->first();
    
    if ($label) {
        // Ini adalah scan label fisik yang spesifik
        if ($label->status !== 'in_stock') {
            \Flux::toast("Label {$code} tidak tersedia (Status: {$label->status})", variant: 'danger');
            return;
        }

        // Cari apakah item_id dari label ini ada di pesanan
        $foundIndex = null;
        foreach ($this->items as $index => $itemData) {
            if ($itemData['item_id'] == $label->item_id) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex !== null) {
            $itemData = $this->items[$foundIndex];
            
            // Cek double scan
            if (in_array($label->id, array_column($itemData['scanned_labels'], 'id'))) {
                \Flux::toast("Label {$code} sudah di-scan!", variant: 'warning');
                return;
            }

            $maxAllowed = $itemData['ordered_qty'] - $itemData['fulfilled_qty'];
            if ($this->items[$foundIndex]['input_qty'] < $maxAllowed) {
                $this->items[$foundIndex]['input_qty']++;
                $this->items[$foundIndex]['scanned_labels'][] = [
                    'id' => $label->id,
                    'code' => $label->label_code
                ];
                \Flux::toast("Label {$code} OK!", variant: 'success');
            } else {
                \Flux::toast("Kuantitas {$itemData['name']} sudah penuh!", variant: 'warning');
            }
        } else {
            \Flux::toast("Barang dari label {$code} tidak ada di pesanan ini.", variant: 'danger');
        }
        return;
    }

    // 2. Jika bukan label fisik, cek apakah ini barcode generic item
    $foundIndex = null;
    foreach ($this->items as $index => $itemData) {
        if ($itemData['code'] === $code || $itemData['name'] === $code) {
            $foundIndex = $index;
            break;
        }
    }

    if ($foundIndex !== null) {
        $itemData = $this->items[$foundIndex];
        
        // Cek apakah item ini mewajibkan label fisik
        if ($itemData['requires_label']) {
            \Flux::toast("Barang {$itemData['name']} wajib di-scan per-label fisik unik!", variant: 'danger');
            return;
        }

        $maxAllowed = $itemData['ordered_qty'] - $itemData['fulfilled_qty'];
        if ($this->items[$foundIndex]['input_qty'] < $maxAllowed) {
            $this->items[$foundIndex]['input_qty']++;
            \Flux::toast("Scan OK: {$itemData['name']}", variant: 'success');
        } else {
            \Flux::toast("Kuantitas {$itemData['name']} sudah penuh!", variant: 'warning');
        }
    } else {
        \Flux::toast("Barcode {$code} tidak dikenali atau tidak ada di pesanan.", variant: 'danger');
    }
}]);

$saveFulfillment = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    if (!$this->order) return;

    $allFulfilled = true;
    $labelsUpdated = false;

    foreach ($this->items as $itemData) {
        $qtyToFulfill = (int) $itemData['input_qty'];
        
        if ($qtyToFulfill > 0) {
            if (!empty($itemData['requires_label'])) {
                // Item requires physical labels
                foreach ($itemData['scanned_labels'] as $label) {
                    SalesOrderFulfillment::create([
                        'sales_order_id' => $this->order->id,
                        'sales_order_item_id' => $itemData['id'],
                        'item_id' => $itemData['item_id'],
                        'item_label_id' => $label['id'],
                        'scanned_qty' => 1,
                        'scanned_by' => auth()->id(),
                    ]);
                    // Update label status
                    \Modules\Inventory\Models\ItemLabel::where('id', $label['id'])->update(['status' => 'bokking']);
                    $labelsUpdated = true;
                }
            } else {
                // Generic item, no labels
                SalesOrderFulfillment::create([
                    'sales_order_id' => $this->order->id,
                    'sales_order_item_id' => $itemData['id'],
                    'item_id' => $itemData['item_id'],
                    'scanned_qty' => $qtyToFulfill,
                    'scanned_by' => auth()->id(),
                ]);
            }
        }

        // Cek apakah dengan ini sudah terpenuhi semua
        if (($itemData['fulfilled_qty'] + $qtyToFulfill) < $itemData['ordered_qty']) {
            $allFulfilled = false;
        }
    }

    if ($labelsUpdated) {
        \App\Events\InventoryUpdated::safeDispatch('Status fisik barang diperbarui (Fulfillment SO: ' . $this->order->so_number . ')');
    }

    if ($allFulfilled) {
        $this->order->status = 'packing';
        $this->order->save();
        \Flux::toast('Fulfillment selesai! Pesanan lanjut ke Packing.', variant: 'success');
        $this->show = false;
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    } else {
        \Flux::toast('Fulfillment parsial disimpan.', variant: 'success');
        $this->loadOrder(); // Reload the data
    }
};

?>

<div>
<flux:modal wire:model="show" class="w-full md:w-[40rem] md:max-w-2xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                <flux:icon.qr-code class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Fulfillment Pesanan (Gudang)</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    SO <strong>{{ $order->so_number }}</strong> - Siapkan barang untuk <strong>{{ $order->customer->name }}</strong>.
                </flux:subheading>
            </div>
        </div>
        
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 bg-zinc-100 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="text-sm text-zinc-600 dark:text-zinc-400 text-center sm:text-left">
                Gunakan <strong>Scanner</strong> untuk barang berlabel, atau ketik manual untuk barang umum.
            </div>
            <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none" tooltip="Gunakan Kamera HP">
                Scanner Barcode
            </flux:button>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            @foreach($items as $index => $item)
                <div class="p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $item['fulfilled_qty'] >= $item['ordered_qty'] ? 'bg-green-50/50 dark:bg-green-900/10' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                    <div class="flex-1">
                        <div class="text-zinc-900 dark:text-zinc-100 font-medium text-base">
                            {{ $item['name'] }}
                            @if($item['fulfilled_qty'] >= $item['ordered_qty'])
                                <div class="inline-block ml-2"><flux:badge size="sm" color="green">Lengkap</flux:badge></div>
                            @endif
                        </div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 flex gap-3">
                            <div>Dipesan: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['ordered_qty'] }}</span></div>
                            <div class="border-l border-zinc-300 dark:border-zinc-600 pl-3">Telah Siap: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['fulfilled_qty'] }}</span></div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 self-end sm:self-auto shrink-0 w-full sm:w-32 justify-between sm:justify-end mt-2 sm:mt-0 pt-2 sm:pt-0 border-t sm:border-0 border-zinc-200 dark:border-zinc-700">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400 sm:hidden">Scan Baru / Input:</span>
                        @if($item['fulfilled_qty'] < $item['ordered_qty'])
                            @if($item['requires_label'])
                                <div class="font-bold text-2xl text-indigo-600 dark:text-indigo-400 text-center w-24 sm:w-full" title="Wajib dipindai menggunakan Scanner Kamera">
                                    {{ $item['input_qty'] }}
                                </div>
                            @else
                                <div class="w-24 sm:w-full">
                                    <flux:input type="number" wire:model="items.{{ $index }}.input_qty" min="0" max="{{ $item['ordered_qty'] - $item['fulfilled_qty'] }}" class="w-full text-center" />
                                </div>
                            @endif
                        @else
                            <span class="text-zinc-400 italic font-medium w-24 sm:w-full text-right sm:text-center">Selesai</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
            <span class="text-xs text-zinc-500 text-center sm:text-left w-full sm:w-auto">
                <flux:icon.information-circle class="inline w-4 h-4 mr-1 text-blue-500" />
                Selesaikan semua item agar SO bisa lanjut ke Packing.
            </span>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 sm:flex-none">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveFulfillment" icon="check" class="flex-1 sm:flex-none">Simpan</flux:button>
            </div>
        </div>
    </div>
    @endif
</flux:modal>

<div x-data @barcode-scanned.window="$wire.dispatch('barcode-scanned', { code: $event.detail.code })"></div>
<x-camera-scanner />
</div>
