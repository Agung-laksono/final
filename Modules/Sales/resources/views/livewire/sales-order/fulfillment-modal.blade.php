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
            $fulfilled = $this->order->fulfillments->where('sales_order_item_id', $item->id)->sum('quantity');
            return [
                'id' => $item->id,
                'name' => $item->item->name,
                'code' => $item->item->code,
                'ordered_qty' => $item->qty,
                'fulfilled_qty' => $fulfilled,
                'input_qty' => 0, // Default to 0 so scanning increments it
            ];
        })->toArray();
    }
};

on(['barcode-scanned' => function ($payload) {
    if (!$this->show) return;

    $code = is_array($payload) ? ($payload['code'] ?? '') : $payload;
    if (empty($code)) return;

    $found = false;
    foreach ($this->items as $index => $itemData) {
        if ($itemData['code'] === $code || $itemData['name'] === $code) {
            $found = true;
            $maxAllowed = $itemData['ordered_qty'] - $itemData['fulfilled_qty'];
            
            if ($this->items[$index]['input_qty'] < $maxAllowed) {
                $this->items[$index]['input_qty']++;
                \Flux::toast('Scan OK: ' . $itemData['name'], variant: 'success');
            } else {
                \Flux::toast('Kuantitas ' . $itemData['name'] . ' sudah penuh!', variant: 'warning');
            }
            break;
        }
    }
    
    if (!$found) {
         \Flux::toast('Barcode/Kode ' . $code . ' tidak ada di pesanan ini.', variant: 'danger');
    }
}]);

$saveFulfillment = function () {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    if (!$this->order) return;

    $allFulfilled = true;

    foreach ($this->items as $itemData) {
        $qtyToFulfill = (int) $itemData['input_qty'];
        if ($qtyToFulfill > 0) {
            SalesOrderFulfillment::create([
                'sales_order_id' => $this->order->id,
                'sales_order_item_id' => $itemData['id'],
                'quantity' => $qtyToFulfill,
                'scanned_by' => auth()->id(),
                'notes' => 'Fulfillment form',
            ]);
        }

        // Cek apakah dengan ini sudah terpenuhi semua
        if (($itemData['fulfilled_qty'] + $qtyToFulfill) < $itemData['ordered_qty']) {
            $allFulfilled = false;
        }
    }

    if ($allFulfilled) {
        $this->order->status = 'packing';
        $this->order->save();
        \Flux::toast('Fulfillment selesai! Pesanan lanjut ke Packing.', variant: 'success');
        $this->show = false;
        $this->dispatch('status-updated');
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
            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                Silakan ketik manual jumlah barang, atau gunakan <strong>Scanner</strong>.
            </div>
            <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none" tooltip="Gunakan Kamera HP">
                Scanner Barcode
            </flux:button>
        </div>

        <div class="mt-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-sm whitespace-nowrap min-w-[30rem]">
                <thead class="bg-zinc-100/50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300">Barang</th>
                        <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300 text-center">Dipesan</th>
                        <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300 text-center">Telah Siap</th>
                        <th class="px-4 py-3 font-semibold text-zinc-600 dark:text-zinc-300 w-32">Scan Baru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($items as $index => $item)
                        <tr class="{{ $item['fulfilled_qty'] >= $item['ordered_qty'] ? 'bg-green-50/50 dark:bg-green-900/10' : '' }}">
                            <td class="px-4 py-3 text-zinc-900 dark:text-zinc-100 font-medium">
                                {{ $item['name'] }}
                                @if($item['fulfilled_qty'] >= $item['ordered_qty'])
                                    <flux:badge size="sm" color="green" class="ml-2">Lengkap</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">{{ $item['ordered_qty'] }}</td>
                            <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">{{ $item['fulfilled_qty'] }}</td>
                            <td class="px-4 py-3">
                                @if($item['fulfilled_qty'] < $item['ordered_qty'])
                                    <flux:input type="number" wire:model="items.{{ $index }}.input_qty" min="0" max="{{ $item['ordered_qty'] - $item['fulfilled_qty'] }}" class="w-full text-center" />
                                @else
                                    <span class="text-zinc-400 italic">Selesai</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-between items-center">
            <span class="text-xs text-zinc-500">
                <flux:icon.information-circle class="inline w-4 h-4 mr-1 text-blue-500" />
                Selesaikan semua item agar SO bisa lanjut ke tahap Packing.
            </span>
            <div class="flex gap-3">
                <flux:button variant="ghost" wire:click="$set('show', false)">Batal</flux:button>
                <flux:button variant="primary" wire:click="saveFulfillment" icon="check">Simpan Fulfillment</flux:button>
            </div>
        </div>
    </div>
    @endif
</flux:modal>

<div x-data @barcode-scanned.window="$wire.dispatch('barcode-scanned', { code: $event.detail.code })"></div>
<x-camera-scanner />
</div>
