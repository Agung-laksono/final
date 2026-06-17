<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Production\Models\ProductionRecipe;
use Modules\Inventory\Models\InventoryRequest;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
    'items' => [], // Array of BOM items with inputs
]);

on(['open-fulfillment-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    $this->notes = '';
    
    // Load BOM
    $this->items = [];
    if ($this->order) {
        $recipe = ProductionRecipe::with('items.item')->where('item_id', $this->order->item_id)->where('is_active', true)->first();
        if ($recipe) {
            foreach ($recipe->items as $recipeItem) {
                $neededQty = $recipeItem->qty * $this->order->requested_qty;
                $stock = DB::table('item_warehouse')->where('item_id', $recipeItem->item_id)->sum('stock');
                
                $this->items[] = [
                    'item_id' => $recipeItem->item_id,
                    'name' => $recipeItem->item->name,
                    'needed' => $neededQty,
                    'stock' => $stock,
                    'input_qty' => 0,
                    'scanned_labels' => [] // If they use scanner in the future
                ];
            }
        }
    }
    
    $this->show = true;
}]);

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    if ($this->order) {
        $hasDeficit = false;
        
        foreach ($this->items as $material) {
            $inputQty = (int) $material['input_qty'];
            $deficit = max(0, $material['needed'] - $inputQty);
            
            if ($deficit > 0) {
                $hasDeficit = true;
                
                // Cek apakah sudah ada request
                $existingRequest = InventoryRequest::where('item_id', $material['item_id'])
                    ->where('source_type', 'production')
                    ->where('reference_number', $this->order->order_number)
                    ->exists();
                    
                if (!$existingRequest) {
                    InventoryRequest::create([
                        'item_id' => $material['item_id'],
                        'source_type' => 'production',
                        'reference_number' => $this->order->order_number,
                        'requested_qty' => $deficit,
                        'notes' => 'Defisit Bahan Baku Produksi (Disiapkan: ' . $inputQty . ', Butuh: ' . $material['needed'] . ')',
                        'status' => 'draft',
                    ]);
                }
            }
        }
        
        if ($hasDeficit) {
            $this->order->status = 'waiting_material';
            if ($this->notes) {
                $this->order->notes = $this->order->notes . "\n[Material Shortage]: " . $this->notes;
            }
            $this->order->save();
            \Flux::toast('Bahan baku kurang! Otomatis membuat antrean permintaan barang.', variant: 'warning');
        } else {
            $this->order->status = 'in_production';
            if ($this->notes) {
                $this->order->notes = $this->order->notes . "\n[Fulfillment]: " . $this->notes;
            }
            $this->order->save();
            \Flux::toast('Semua bahan disiapkan. Status menjadi Sedang Diproduksi.', variant: 'success');
        }

        $this->dispatch('status-updated');
        $this->show = false;
    }
};
?>

<div>
<flux:modal wire:model="show" class="w-full md:w-[40rem] md:max-w-2xl">
    @if($order)
    <div class="p-4 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 w-10 h-10 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center">
                <flux:icon.cube class="w-5 h-5" />
            </div>
            <div class="flex-1">
                <flux:heading size="lg">Fulfillment Bahan Produksi (Gudang)</flux:heading>
                <flux:subheading class="mt-1 text-sm">
                    PO <strong>{{ $order->order_number }}</strong> - Siapkan bahan untuk <strong>{{ $order->item->name }}</strong>.
                </flux:subheading>
            </div>
        </div>
        
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4 bg-zinc-100 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="text-sm text-zinc-600 dark:text-zinc-400 text-center sm:text-left">
                Gunakan <strong>Scanner</strong> untuk memindai bahan, atau ketik manual.
            </div>
            <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new Event('camera-scanner-modal-opened'))" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none" tooltip="Gunakan Kamera HP">
                Scanner Barcode
            </flux:button>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            @if(count($items) > 0)
                @foreach($items as $index => $item)
                    <div class="p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ $item['input_qty'] >= $item['needed'] ? 'bg-green-50/50 dark:bg-green-900/10' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                        <div class="flex-1">
                            <div class="text-zinc-900 dark:text-zinc-100 font-medium text-base">
                                {{ $item['name'] }}
                                @if($item['input_qty'] >= $item['needed'])
                                    <div class="inline-block ml-2"><flux:badge size="sm" color="green">Lengkap</flux:badge></div>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 flex gap-3">
                                <div>Dibutuhkan: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['needed'] }}</span></div>
                                <div class="border-l border-zinc-300 dark:border-zinc-600 pl-3">Stok Fisik: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['stock'] }}</span></div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3 self-end sm:self-auto shrink-0 w-full sm:w-32 justify-between sm:justify-end mt-2 sm:mt-0 pt-2 sm:pt-0 border-t sm:border-0 border-zinc-200 dark:border-zinc-700">
                            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400 sm:hidden">Siap Diberikan:</span>
                            <div class="w-24 sm:w-full">
                                <flux:input type="number" wire:model="items.{{ $index }}.input_qty" min="0" max="{{ $item['needed'] }}" class="w-full text-center" />
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-lg text-sm flex gap-2">
                    <flux:icon.information-circle class="w-5 h-5 shrink-0" />
                    <p>Tidak ada data resep (BOM) untuk produk ini. Abaikan scan dan langsung konfirmasi penyiapan bahan.</p>
                </div>
            @endif
        </div>

        <div class="mt-4">
            <flux:textarea wire:model="notes" label="Catatan (Opsional)" placeholder="Misal: Bahan baku kurang..." />
        </div>

        <div class="mt-6 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
            <span class="text-xs text-zinc-500 text-center sm:text-left w-full sm:w-auto">
                <flux:icon.information-circle class="inline w-4 h-4 mr-1 text-blue-500" />
                Jika input kurang dari dibutuhkan, pesanan masuk ke status Menunggu Bahan.
            </span>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 sm:flex-none">Batal</flux:button>
                <flux:button variant="primary" wire:click="save" icon="check" class="flex-1 sm:flex-none">Simpan</flux:button>
            </div>
        </div>
    </div>
    @endif
</flux:modal>

<div x-data @barcode-scanned.window="$wire.dispatch('barcode-scanned', { code: $event.detail.code })"></div>
<x-camera-scanner />
</div>
