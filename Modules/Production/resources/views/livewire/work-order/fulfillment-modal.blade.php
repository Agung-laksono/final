<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Production\Models\ProductionRecipe;
use Modules\Inventory\Models\InventoryRequest;
use Modules\Inventory\Models\ItemLabel;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
    'notes' => '',
    'production_mode' => 'internal', // 'internal' or 'maklon'
    'items' => [], // Array of BOM items with inputs
]);

$refreshItems = function() {
    if (!$this->order) return;
    
    $recipe = ProductionRecipe::with('items.item')->where('item_id', $this->order->item_id)->where('is_active', true)->first();
    if ($recipe) {
        $newItems = [];
        $collisionDetected = false;

        foreach ($recipe->items as $recipeItem) {
            $existing = null;
            foreach ($this->items as $oldItem) {
                if ($oldItem['item_id'] === $recipeItem->item_id) {
                    $existing = $oldItem;
                    break;
                }
            }

            $neededQty = $recipeItem->qty * $this->order->requested_qty;
            $stock = DB::table('item_warehouse')->where('item_id', $recipeItem->item_id)->sum('stock');
            
            $alreadyConsumed = DB::table('stock_movements')
                ->where('reference_number', $this->order->order_number)
                ->where('item_id', $recipeItem->item_id)
                ->where('type', 'out')
                ->sum('quantity') ?? 0;
            
            $remainingNeeded = max(0, $neededQty - $alreadyConsumed);
            
            $existingRequest = InventoryRequest::where('item_id', $recipeItem->item_id)
                ->where('source_type', 'production')
                ->where('reference_number', $this->order->order_number)
                ->first();
            
            $currentInputQty = $existing ? $existing['input_qty'] : 0;
            $currentScanned = $existing ? $existing['scanned_labels'] : [];

            if (!$recipeItem->item->requires_label) {
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
                'item_id' => $recipeItem->item_id,
                'name' => $recipeItem->item->name,
                'requires_label' => $recipeItem->item->requires_label,
                'needed' => $neededQty,
                'already_consumed' => $alreadyConsumed,
                'remaining_needed' => $remainingNeeded,
                'stock' => $stock,
                'input_qty' => $currentInputQty,
                'scanned_labels' => $currentScanned,
                'request_status' => $existingRequest ? $existingRequest->status : null,
                'request_qty' => $existingRequest ? $existingRequest->requested_qty : 0,
            ];
        }
        $this->items = $newItems;

        if ($collisionDetected && $this->show) {
            \Flux::toast('PERINGATAN: Stok baru saja diambil pengguna lain secara Real-Time! Input Anda di-reset.', variant: 'danger');
        }
    }
};

on(['open-fulfillment-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = ProductionOrder::with('item')->find($orderId);
    $this->notes = '';
    $this->production_mode = 'internal';
    $this->items = [];
    
    $this->refreshItems();
    
    $this->show = true;
}]);

on(['echo:inventory,InventoryUpdated' => function () {
    if ($this->show) {
        $this->refreshItems();
    }
}]);

on(['barcode-scanned' => function ($code) {
    if (!$this->show) return;

    $code = trim($code);
    if (empty($code)) return;

    $label = ItemLabel::with('item')->where('label_code', $code)->first();

    if (!$label) {
        \Flux::toast('Barcode tidak terdaftar dalam sistem.', variant: 'danger');
        return;
    }

    if ($label->status !== 'in_stock') {
        \Flux::toast("Barcode {$code} tidak tersedia (status: {$label->status}).", variant: 'danger');
        return;
    }

    // Cari item ini di BOM
    $itemIndex = null;
    foreach ($this->items as $idx => $material) {
        if ($material['item_id'] === $label->item_id) {
            $itemIndex = $idx;
            break;
        }
    }

    if ($itemIndex === null) {
        \Flux::toast("Bahan {$label->item->name} tidak ada dalam resep produk ini.", variant: 'danger');
        return;
    }

    $material = $this->items[$itemIndex];

    if (!$material['requires_label']) {
        \Flux::toast("Bahan {$label->item->name} tidak membutuhkan scan barcode.", variant: 'warning');
        return;
    }

    // Cek apakah sudah di-scan
    $alreadyScanned = false;
    foreach ($material['scanned_labels'] as $sl) {
        if ($sl['id'] === $label->id) {
            $alreadyScanned = true;
            break;
        }
    }

    if ($alreadyScanned) {
        \Flux::toast("Barcode {$code} sudah di-scan sebelumnya.", variant: 'warning');
        return;
    }

    // Cek apakah kuota sudah terpenuhi
    if ($material['input_qty'] >= $material['needed']) {
        \Flux::toast("Kebutuhan bahan {$label->item->name} sudah terpenuhi.", variant: 'warning');
        return;
    }

    // Tambahkan ke scanned labels
    $this->items[$itemIndex]['scanned_labels'][] = [
        'id' => $label->id,
        'code' => $label->label_code,
        'warehouse_id' => $label->warehouse_id
    ];
    $this->items[$itemIndex]['input_qty']++;

    \Flux::toast("Berhasil scan: {$label->item->name} ({$code})", variant: 'success');
}]);

$removeScannedLabel = function($itemIndex, $labelIndex) {
    unset($this->items[$itemIndex]['scanned_labels'][$labelIndex]);
    $this->items[$itemIndex]['scanned_labels'] = array_values($this->items[$itemIndex]['scanned_labels']);
    $this->items[$itemIndex]['input_qty']--;
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    if ($this->order) {
        if (empty($this->items)) {
            \Flux::toast('Produk ini belum memiliki resep (BOM). Harap buat resep terlebih dahulu.', variant: 'danger');
            return;
        }

        // Validasi ketat (Backend Protection)
        foreach ($this->items as $material) {
            $inputQty = (int) $material['input_qty'];
            
            if ($inputQty < 0) {
                \Flux::toast("Input kuantitas untuk {$material['name']} tidak valid.", variant: 'danger');
                return;
            }

            if ($inputQty > $material['remaining_needed']) {
                \Flux::toast("Kuantitas {$material['name']} melebihi sisa kekurangan yang dibutuhkan ({$material['remaining_needed']}).", variant: 'danger');
                return;
            }

            if ($inputQty > $material['stock']) {
                \Flux::toast("Stok {$material['name']} di gudang tidak mencukupi untuk memenuhi input ({$inputQty} > {$material['stock']}).", variant: 'danger');
                return;
            }
            
            if ($material['requires_label'] && count($material['scanned_labels']) !== $inputQty) {
                \Flux::toast("Jumlah scan barcode untuk {$material['name']} tidak sesuai dengan perhitungan.", variant: 'danger');
                return;
            }
        }

        $hasDeficit = false;
        
        DB::transaction(function () use (&$hasDeficit) {
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
                            $labelModel->status = 'consumed';
                            $labelModel->notes = 'Dikonsumsi untuk Produksi: ' . $this->order->order_number;
                            $labelModel->save();
                            StockMovement::create([
                                'item_id' => $material['item_id'],
                                'warehouse_id' => $sl['warehouse_id'],
                                'type' => 'out',
                                'quantity' => -1,
                                'reference_number' => $this->order->order_number,
                                'date' => now()->toDateString(),
                                'notes' => 'Fulfillment bahan produksi. Label: ' . $sl['code'],
                                'user_id' => auth()->id(),
                            ]);
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
                            throw new \Exception("Stok aktual {$material['name']} tidak mencukupi. Kemungkinan stok baru saja diambil pengguna lain. Harap tutup dan buka kembali tiket ini.");
                        }

                        foreach ($warehouses as $wh) {
                            if ($remainingToDeduct <= 0) break;

                            $deduct = min($wh->stock, $remainingToDeduct);
                            StockMovement::create([
                                'item_id' => $material['item_id'],
                                'warehouse_id' => $wh->warehouse_id,
                                'type' => 'out',
                                'quantity' => -$deduct,
                                'reference_number' => $this->order->order_number,
                                'date' => now()->toDateString(),
                                'notes' => 'Fulfillment bahan produksi.',
                                'user_id' => auth()->id(),
                            ]);

                            $remainingToDeduct -= $deduct;
                        }
                    }
                }
            }
            
            if ($hasDeficit) {
                $this->order->status = 'waiting_material';
                if ($this->notes) {
                    $this->order->notes = $this->order->notes . "\n[Material Shortage]: " . $this->notes;
                }
                $this->order->save();
            } else {
                if ($this->production_mode === 'maklon') {
                    $this->order->status = 'waiting_vendor';
                    if ($this->notes) {
                        $this->order->notes = $this->order->notes . "\n[Fulfillment]: " . $this->notes;
                    }
                    $this->order->save();
                } else {
                    $this->order->status = 'in_production';
                    if ($this->notes) {
                        $this->order->notes = $this->order->notes . "\n[Fulfillment]: " . $this->notes;
                    }
                    $this->order->save();
                }
            }
        });

        if ($hasDeficit) {
            \Flux::toast('Pesanan masuk ke status Menunggu Bahan. Tiket permintaan pembelian sudah diterbitkan sebelumnya.', variant: 'warning');
        } else {
            if ($this->production_mode === 'maklon') {
                \Flux::toast('Semua bahan disiapkan. Pesanan masuk ke Antre Maklon.', variant: 'success');
            } else {
                \Flux::toast('Semua bahan disiapkan. Status menjadi Sedang Diproduksi (Internal).', variant: 'success');
            }
        }

        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \App\Events\InventoryUpdated::safeDispatch('Fulfillment bahan produksi PO ' . $this->order->order_number . ' diproses');
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
                                @if($item['input_qty'] >= $item['remaining_needed'])
                                    <div class="inline-block ml-2"><flux:badge size="sm" color="green">Lengkap</flux:badge></div>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 flex flex-wrap gap-3 items-center">
                                <div>Sisa Kurang: <span class="font-bold text-rose-600 dark:text-rose-500">{{ $item['remaining_needed'] }}</span> <span class="text-xs text-zinc-400">(Telah Diambil: {{ $item['already_consumed'] }} / Total Butuh: {{ $item['needed'] }})</span></div>
                                <div class="border-l border-zinc-300 dark:border-zinc-600 pl-3">Stok Fisik: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['stock'] }}</span></div>
                                @if($item['request_status'] && $item['remaining_needed'] > 0)
                                    <div class="border-l border-zinc-300 dark:border-zinc-600 pl-3">
                                        Status Purchasing: 
                                        @if($item['request_status'] === 'draft')
                                            <span class="text-orange-500 font-medium">Dalam Antrean (Draft) &middot; {{ $item['request_qty'] }} dipesan</span>
                                        @elseif($item['request_status'] === 'ordered')
                                            <span class="text-blue-500 font-medium">Sedang Diperjalanan (Ordered) &middot; {{ $item['request_qty'] }} dipesan</span>
                                        @elseif($item['request_status'] === 'completed')
                                            <span class="text-green-500 font-medium">Selesai (Completed) &middot; {{ $item['request_qty'] }} diterima</span>
                                        @else
                                            <span class="text-zinc-500">{{ $item['request_status'] }} &middot; {{ $item['request_qty'] }} dipesan</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3 self-end sm:self-auto shrink-0 w-full sm:w-32 justify-between sm:justify-end mt-2 sm:mt-0 pt-2 sm:pt-0 border-t sm:border-0 border-zinc-200 dark:border-zinc-700">
                            <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400 sm:hidden">Siap Diberikan:</span>
                            <div class="w-24 sm:w-full">
                                @if($item['requires_label'])
                                    <div class="text-center font-bold text-xl text-indigo-600 dark:text-indigo-400">
                                        {{ $item['input_qty'] }} <span class="text-xs text-zinc-500 font-normal">Scan</span>
                                    </div>
                                @else
                                    @if($item['stock'] <= 0)
                                        <div class="text-center text-sm font-bold text-red-500 bg-red-50 dark:bg-red-900/20 py-2 rounded-lg border border-red-100 dark:border-red-800">Kosong</div>
                                    @else
                                        <flux:input type="number" wire:model.live="items.{{ $index }}.input_qty" min="0" max="{{ min($item['remaining_needed'], $item['stock']) }}" class="w-full text-center" />
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
                @endforeach
            @else
                <div class="p-5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded-xl flex flex-col items-center justify-center text-center">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mb-3">
                        <flux:icon.exclamation-triangle class="w-6 h-6" />
                    </div>
                    <flux:heading size="md" class="text-red-800 dark:text-red-300 mb-1">Resep (BOM) Tidak Ditemukan</flux:heading>
                    <p class="text-sm text-red-600 dark:text-red-400 max-w-sm mx-auto mb-4">
                        Sistem menolak produksi karena resep bahan baku kosong. Produksi buta tanpa resep akan merusak pelacakan stok.
                    </p>
                    <flux:button href="{{ route('production.recipes') }}" wire:navigate variant="danger" icon="document-plus">
                        Buka Menu Resep (BOM)
                    </flux:button>
                </div>
            @endif
        </div>

        @if(count($items) > 0)
        <div class="mt-6 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:heading size="md" class="mb-3">Metode Produksi</flux:heading>
            <flux:radio.group wire:model="production_mode" class="flex flex-col sm:flex-row gap-4">
                <flux:radio value="internal" label="Produksi Internal (In-House)" />
                <flux:radio value="maklon" label="Kirim ke Vendor (Maklon / Eksternal)" />
            </flux:radio.group>
        </div>

        <div class="mt-4">
            <flux:textarea wire:model="notes" label="Catatan (Opsional)" placeholder="Misal: Bahan baku kurang..." />
        </div>

        @php
            $hasIncompleteStockUsage = false;
            $hasStockDeficit = false;
            
            foreach($items as $item) {
                if ($item['remaining_needed'] > 0) {
                    $maxCanFulfill = min($item['remaining_needed'], $item['stock']);
                    
                    if ($item['input_qty'] < $maxCanFulfill) {
                        $hasIncompleteStockUsage = true;
                    }
                    
                    if ($item['remaining_needed'] > $item['stock']) {
                        $hasStockDeficit = true;
                    }
                }
            }
        @endphp

        <div class="mt-6 flex flex-col-reverse sm:flex-row justify-between items-center gap-4">
            <span class="text-xs text-zinc-500 text-center sm:text-left w-full sm:w-auto">
                <flux:icon.information-circle class="inline w-4 h-4 mr-1 text-blue-500" />
                @if($hasIncompleteStockUsage)
                    Harap lengkapi input/scan bahan baku yang <strong class="text-zinc-700 dark:text-zinc-300">tersedia di gudang</strong> sebelum melanjutkan.
                @elseif($hasStockDeficit)
                    Pesanan akan tetap di status <strong class="text-amber-600 dark:text-amber-500">Menunggu Bahan</strong> (Tiket pembelian telah diterbitkan).
                @else
                    Pesanan akan diteruskan ke proses produksi.
                @endif
            </span>
            <div class="flex gap-2 sm:gap-3 w-full sm:w-auto">
                <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 sm:flex-none">Batal</flux:button>
                @if($hasIncompleteStockUsage)
                    <flux:button variant="primary" disabled class="flex-1 sm:flex-none opacity-50 cursor-not-allowed">Lengkapi Bahan</flux:button>
                @elseif($hasStockDeficit)
                    <flux:button variant="warning" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="exclamation-triangle" class="flex-1 sm:flex-none">Simpan & Tunggu Bahan</flux:button>
                @else
                    <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="check" class="flex-1 sm:flex-none">Lanjut Produksi</flux:button>
                @endif
            </div>
        </div>
        @else
        <div class="mt-6 flex justify-end">
            <flux:button variant="ghost" wire:click="$set('show', false)">Tutup</flux:button>
        </div>
        @endif
    </div>
    @endif
</flux:modal>

<div x-data @barcode-scanned.window="$wire.dispatch('barcode-scanned', { code: $event.detail.code })"></div>
<x-camera-scanner />
</div>
