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
    'items' => [], // Array of BOM items with inputs
    'manualBarcode' => '',
]);

$refreshItems = function() {
    if (!$this->order) return;
    
    $inventoryService = app(\App\Services\InventoryService::class);
    
    $newItems = [];
    $collisionDetected = false;

    // Use Custom BOM if exists, else fallback to standard recipe
    $recipeItems = collect();
    
    if (!empty($this->order->custom_bom)) {
        $customItems = json_decode($this->order->custom_bom, true) ?? [];
        foreach ($customItems as $c) {
            $itemModel = \Modules\Inventory\Models\Item::find($c['item_id']);
            if ($itemModel) {
                // Mock an object to match the recipeItem structure
                $recipeItems->push((object)[
                    'item_id' => $c['item_id'],
                    'qty' => $c['qty'], // Already unit qty
                    'item' => $itemModel
                ]);
            }
        }
    } else {
        $recipe = ProductionRecipe::with('items.item')->where('item_id', $this->order->item_id)->where('is_active', true)->first();
        if ($recipe) {
            $recipeItems = collect($recipe->items);
        }
    }

    if ($recipeItems->isNotEmpty()) {
        foreach ($recipeItems as $recipeItem) {
            $existing = null;
            foreach ($this->items as $oldItem) {
                if ($oldItem['item_id'] === $recipeItem->item_id) {
                    $existing = $oldItem;
                    break;
                }
            }

            $neededQty = $recipeItem->qty * $this->order->requested_qty;
            $stock = DB::table('item_warehouse')->where('item_id', $recipeItem->item_id)->sum('stock') ?? 0;
            
            // We use ABS here just in case old data has negative values from the previous bug.
            $alreadyConsumed = abs(DB::table('stock_movements')
                ->where('reference_number', $this->order->order_number)
                ->where('item_id', $recipeItem->item_id)
                ->where('type', 'out')
                ->sum('quantity') ?? 0);
            
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
                'code' => $recipeItem->item->code,
                'unit' => $recipeItem->item->unit->name ?? 'pcs',
                'image' => $recipeItem->item->image ?? null,
                'custom_attachments' => $recipeItem->custom_attachments ?? [],
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
                            $labelModel->status = 'consumed';
                            $labelModel->notes = 'Dikonsumsi untuk Produksi: ' . $this->order->order_number;
                            $labelModel->save();
                            
                            $inventoryService->adjustStock(
                                $material['item_id'],
                                $sl['warehouse_id'],
                                1, // Quantity
                                'out', // Type
                                $this->order->order_number, // Ref
                                'Fulfillment bahan produksi. Label: ' . $sl['code'] // Notes
                            );
                            
                            // Kurangi alokasi
                            \Illuminate\Support\Facades\DB::table('item_warehouse')
                                ->where('item_id', $material['item_id'])
                                ->where('allocated_qty', '>', 0)
                                ->orderBy('allocated_qty', 'desc')
                                ->limit(1)
                                ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('MAX(0, allocated_qty - 1)')]);
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
                            
                            $inventoryService->adjustStock(
                                $material['item_id'],
                                $wh->warehouse_id,
                                $deduct, // Quantity
                                'out', // Type
                                $this->order->order_number, // Ref
                                'Fulfillment bahan produksi.' // Notes
                            );
                            
                            // Kurangi alokasi
                            \Illuminate\Support\Facades\DB::table('item_warehouse')
                                ->where('item_id', $material['item_id'])
                                ->where('allocated_qty', '>', 0)
                                ->orderBy('allocated_qty', 'desc')
                                ->limit(1)
                                ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('MAX(0, allocated_qty - ' . $deduct . ')')]);

                            $remainingToDeduct -= $deduct;
                        }
                    }
                }
            }
            
            // Hitung ekuivalen barang jadi berdasarkan suplai bahan terendah
            $minPercent = 100;
            foreach ($this->items as $material) {
                $totalNeeded = (int) $material['needed'];
                $alreadyConsumed = (int) $material['already_consumed'];
                $inputQty = (int) $material['input_qty'];
                
                if ($totalNeeded > 0) {
                    $percent = (($alreadyConsumed + $inputQty) / $totalNeeded) * 100;
                    if ($percent < $minPercent) {
                        $minPercent = $percent;
                    }
                }
            }
            
            $equivalentItems = round(($minPercent / 100) * $this->order->requested_qty);
            $existingNotes = $this->order->notes ?? '';
            if (preg_match('/\[MaterialProgress:\s*\d+\]/', $existingNotes)) {
                $existingNotes = preg_replace('/\[MaterialProgress:\s*\d+\]/', "[MaterialProgress: {$equivalentItems}]", $existingNotes);
            } else {
                $existingNotes .= "\n[MaterialProgress: {$equivalentItems}]";
            }
            $this->order->notes = trim($existingNotes);
            
            if ($hasDeficit) {
                $this->order->status = 'waiting_material';
                if ($this->notes) {
                    $this->order->notes = $this->order->notes . "\n[Material Shortage]: " . $this->notes;
                }
                $this->order->save();
            } else {
                $this->order->status = 'material_issued';
                if ($this->notes) {
                    $this->order->notes = $this->order->notes . "\n[Fulfillment]: " . $this->notes;
                }
                $this->order->save();
            }
        });

        if ($hasDeficit) {
            \Flux::toast('Pesanan masuk ke status Menunggu Bahan. Tiket permintaan pembelian sudah diterbitkan sebelumnya.', variant: 'warning');
        } else {
            \Flux::toast('Semua bahan disiapkan. Menunggu Validasi Produksi.', variant: 'success');
        }

        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        \App\Events\InventoryUpdated::safeDispatch('Fulfillment bahan produksi PO ' . $this->order->order_number . ' diproses');
        $this->show = false;
    }
};
?>

<div>
<flux:modal wire:model="show" x-on:close="$dispatch('modal-closed')" class="w-full md:w-[40rem] md:max-w-2xl">
    @if($order)
    <div class="p-3 sm:p-5">
        {{-- Header --}}
        <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3">
            <div class="flex-shrink-0 w-8 h-8 bg-orange-100 dark:bg-orange-950/30 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center">
                <flux:icon.cube class="w-4 h-4" />
            </div>
            <div class="min-w-0 flex-1">
                <flux:heading size="md" class="text-zinc-950 dark:text-white font-bold leading-tight">Fulfillment Bahan (Gudang)</flux:heading>
                <p class="text-[11px] sm:text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                    PO <strong>{{ $order->order_number }}</strong> &bull; <strong>{{ $order->item->name }}</strong>
                </p>
            </div>
        </div>
        
        {{-- Scanner Section --}}
        <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-2 bg-zinc-50 dark:bg-zinc-800/20 p-2.5 rounded-xl border border-zinc-200/50 dark:border-zinc-700/50">
            <div class="flex-1 w-full" x-data="{ hasText: false }">
                <flux:input 
                    wire:model="manualBarcode" 
                    @input="hasText = $event.target.value.length > 0"
                    x-on:keydown.enter="$dispatch('barcode-scanned', { code: $wire.manualBarcode }); $wire.manualBarcode = ''; hasText = false" 
                    placeholder="Scan / ketik barcode..." 
                    icon="qr-code" 
                    class="!h-9 text-xs"
                >
                    <x-slot:iconTrailing>
                        <button x-show="hasText" x-transition type="button" x-on:click="$dispatch('barcode-scanned', { code: $wire.manualBarcode }); $wire.manualBarcode = ''; hasText = false" class="p-1 rounded-md text-blue-600 hover:bg-blue-100 hover:text-blue-700 transition-colors pointer-events-auto" title="Input Barcode">
                            <flux:icon.paper-airplane class="w-4 h-4" />
                        </button>
                    </x-slot:iconTrailing>
                </flux:input>
            </div>
            <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new CustomEvent('camera-scanner-modal-opened', { detail: { mode: 'continuous' } }))" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none text-xs h-9" tooltip="Gunakan Kamera HP">
                Scanner Kamera
            </flux:button>
        </div>

        {{-- Material List --}}
        <div class="mt-4 space-y-2">
            @if(count($items) > 0)
                @foreach($items as $index => $item)
                    <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col gap-2 {{ $item['input_qty'] >= $item['needed'] ? 'bg-green-50/20 dark:bg-green-950/10 border-green-200 dark:border-green-900/50' : 'bg-white dark:bg-zinc-800' }}">
                        <div class="flex gap-2.5 items-start">
                            @if(!empty($item['custom_attachments']))
                                <img src="{{ asset('storage/' . $item['custom_attachments'][0]) }}" class="w-10 h-10 rounded-lg object-cover bg-amber-100 ring-1 ring-amber-400 shrink-0" loading="lazy">
                            @elseif(!empty($item['image']))
                                <img src="{{ Storage::url($item['image']) }}" class="w-10 h-10 rounded-lg object-cover bg-zinc-100 shrink-0" loading="lazy">
                            @else
                                <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center text-zinc-400 shrink-0">
                                    <flux:icon.cube class="w-5 h-5" />
                                </div>
                            @endif
                            
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-bold text-zinc-950 dark:text-white truncate leading-tight">{{ $item['name'] }}</span>
                                    @if(!empty($item['custom_attachments']))
                                        <span class="text-[8px] bg-amber-100 text-amber-700 px-1 py-0.5 rounded font-semibold uppercase">Custom</span>
                                    @endif
                                    @if($item['input_qty'] >= $item['remaining_needed'])
                                        <flux:badge size="sm" color="green" class="!py-0 !px-1.5 text-[9px]">Lengkap</flux:badge>
                                    @endif
                                </div>
                                <div class="text-[10px] text-zinc-500 dark:text-zinc-400 mt-0.5 leading-snug">
                                    <span class="font-mono">{{ $item['code'] ?? '-' }}</span> &bull; 
                                    Stok: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $item['stock'] }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Details & Input Section --}}
                        <div class="flex items-center justify-between border-t border-zinc-100 dark:border-zinc-700/50 pt-2 text-[11px] gap-2">
                            <div class="text-zinc-500 leading-tight">
                                Sisa Kurang: <strong class="text-rose-600 dark:text-rose-400 font-bold">{{ $item['remaining_needed'] }}</strong> 
                                <span class="text-[9px] text-zinc-400">(Butuh: {{ $item['needed'] }} | Diambil: {{ $item['already_consumed'] }})</span>
                            </div>
                            
                            <div class="flex items-center gap-1.5 shrink-0">
                                <div class="w-20">
                                    @if($item['requires_label'])
                                         <div class="text-center font-bold text-xs py-1 rounded-lg border transition-all duration-300 {{ $item['input_qty'] > 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50 shadow-[0_0_8px_rgba(16,185,129,0.25)]' : 'text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-900/30 border-zinc-200 dark:border-zinc-800' }}">
                                             {{ $item['input_qty'] }} <span class="text-[8px] {{ $item['input_qty'] > 0 ? 'text-emerald-500' : 'text-zinc-400 dark:text-zinc-500' }} font-normal uppercase">Scan</span>
                                         </div>
                                    @else
                                        @if($item['stock'] <= 0)
                                            <div class="text-center text-[10px] font-bold text-red-500 bg-red-50 dark:bg-red-900/20 py-1 rounded border border-red-100 dark:border-red-900/50">Kosong</div>
                                        @else
                                            <flux:input type="number" 
                                                wire:model.blur="items.{{ $index }}.input_qty" 
                                                min="0" 
                                                max="{{ min($item['remaining_needed'], $item['stock']) }}" 
                                                x-on:input="
                                                    let maxVal = {{ min($item['remaining_needed'], $item['stock']) }};
                                                    if(Number($el.value) > maxVal) { 
                                                        $el.value = maxVal; 
                                                        $el.dispatchEvent(new Event('input')); 
                                                    }
                                                "
                                                class="w-full text-center !h-7 text-xs" />
                                        @endif
                                    @endif
                                </div>
                                <span class="text-[10px] text-zinc-500 font-medium w-8 truncate uppercase">{{ $item['unit'] ?? 'pcs' }}</span>
                            </div>
                        </div>

                        @if($item['request_status'] && $item['remaining_needed'] > 0)
                            <div class="text-[9px] bg-zinc-50 dark:bg-zinc-900/30 p-1.5 rounded border border-zinc-100 dark:border-zinc-800 text-zinc-500">
                                <strong>Purchasing:</strong> 
                                @if($item['request_status'] === 'draft')
                                    <span class="text-orange-500 font-medium">Antrean (Draft) &middot; {{ $item['request_qty'] }} {{ $item['unit'] }}</span>
                                @elseif($item['request_status'] === 'ordered')
                                    <span class="text-blue-500 font-medium">Diperjalanan (Ordered) &middot; {{ $item['request_qty'] }} {{ $item['unit'] }}</span>
                                @elseif($item['request_status'] === 'completed')
                                    <span class="text-green-500 font-medium">Diterima (Completed) &middot; {{ $item['request_qty'] }} {{ $item['unit'] }}</span>
                                @else
                                    <span class="text-zinc-500">{{ $item['request_status'] }} &middot; {{ $item['request_qty'] }} {{ $item['unit'] }}</span>
                                @endif
                            </div>
                        @endif

                        @if($item['requires_label'] && count($item['scanned_labels']) > 0)
                            <div class="pl-2 border-l-2 border-indigo-200 dark:border-indigo-900/50 flex flex-wrap gap-1 mt-1">
                                @foreach($item['scanned_labels'] as $labelIndex => $sl)
                                    <div class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-[10px] px-1.5 py-0.5 rounded border border-indigo-100 dark:border-indigo-800/50">
                                        <flux:icon.qr-code class="w-2.5 h-2.5" />
                                        {{ $sl['code'] }}
                                        <button type="button" wire:click="removeScannedLabel({{ $index }}, {{ $labelIndex }})" class="ml-1 text-indigo-400 hover:text-red-500"><flux:icon.x-mark class="w-2.5 h-2.5" /></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 rounded-xl flex flex-col items-center justify-center text-center">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mb-2">
                        <flux:icon.exclamation-triangle class="w-5 h-5" />
                    </div>
                    <flux:heading size="sm" class="text-red-800 dark:text-red-300 mb-1">Resep (BOM) Tidak Ditemukan</flux:heading>
                    <p class="text-xs text-red-600 dark:text-red-400 max-w-sm mx-auto mb-3">
                        Sistem menolak produksi karena resep bahan baku kosong.
                    </p>
                    <flux:button href="{{ route('production.recipes') }}" wire:navigate variant="danger" size="sm" icon="document-plus">
                        Buka Menu Resep
                    </flux:button>
                </div>
            @endif
        </div>
        
        @if(count($items) > 0)
        <div class="mt-3">
            <flux:textarea wire:model="notes" label="Catatan (Opsional)" placeholder="Catatan tambahan..." rows="2" class="text-xs" />
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

        <div class="mt-4 flex flex-col gap-3">
            <div class="text-[10px] text-zinc-500 flex items-start gap-1.5 leading-tight">
                <flux:icon.information-circle class="w-4 h-4 text-blue-500 shrink-0" />
                <span>
                    @if($hasIncompleteStockUsage || $hasStockDeficit)
                        Pemenuhan dilakukan secara <strong class="text-amber-600 dark:text-amber-500">Parsial (Sebagian)</strong>. Pesanan akan berstatus Menunggu Bahan.
                    @else
                        Semua bahan lengkap siap diserahkan ke tim produksi.
                    @endif
                </span>
            </div>
            
            <div class="flex gap-2 w-full">
                <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 text-xs"> Batal </flux:button>
                @if($hasIncompleteStockUsage || $hasStockDeficit)
                    <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="exclamation-triangle" class="flex-1 text-xs !bg-amber-500 hover:!bg-amber-600 !text-white !border-none"> Simpan Parsial (Tunggu Sisa) </flux:button>
                @else
                    <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="check" class="flex-1 text-xs">Serahkan Bahan Lengkap</flux:button>
                @endif
            </div>
        </div>
        @else
        <div class="mt-4 flex justify-end">
            <flux:button variant="ghost" wire:click="$set('show', false)" class="w-full sm:w-auto text-xs"> Tutup </flux:button>
        </div>
        @endif
    </div>
    @endif
</flux:modal>
</div>
