<?php
use function Livewire\Volt\{state, on};
use Modules\Production\Models\ProductionOrder;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemLabel;
use Illuminate\Support\Facades\DB;

state([
    'show' => false,
    'itemId' => null,
    'wos' => [],
    'totalNeeded' => 0,
    'totalFulfilled' => 0,
    'stock' => 0,
    'allocations' => [],
    'manualBarcode' => '',
    'targetOrderId' => null,
]);

$refreshData = function() {
    if (!$this->itemId) return;
    
    $item = Item::with('unit')->find($this->itemId);
    if (!$item) return;
    
    $activeOrders = ProductionOrder::whereIn('status', ['waiting_material', 'material_fulfillment'])->get();
        
    $matchingWOs = [];
    $totalNeeded = 0;
    $totalFulfilled = 0;
    
    foreach ($activeOrders as $order) {
        $recipeItems = [];
        if (!empty($order->custom_bom)) {
            $customItems = json_decode($order->custom_bom, true) ?? [];
            foreach ($customItems as $c) {
                if ($c['item_id'] == $this->itemId) {
                    $recipeItems[] = (object)['qty' => $c['qty']];
                }
            }
        } else {
            $recipe = \Modules\Production\Models\ProductionRecipe::where('item_id', $order->item_id)->where('is_active', true)->first();
            if ($recipe) {
                $recipeItems = \Modules\Production\Models\ProductionRecipeItem::where('production_recipe_id', $recipe->id)
                    ->where('item_id', $this->itemId)
                    ->get();
            }
        }
        
            foreach ($recipeItems as $ri) {
            $neededQty = $ri->qty * $order->requested_qty;
            $alreadyConsumed = abs(DB::table('stock_movements')
                ->where('reference_number', $order->order_number)
                ->where('item_id', $this->itemId)
                ->where('type', 'out')
                ->sum('quantity') ?? 0);
            
            $remainingNeeded = max(0, $neededQty - $alreadyConsumed);
            
            if ($remainingNeeded > 0 || $alreadyConsumed > 0) {
                $totalNeeded += $neededQty;
                $totalFulfilled += $alreadyConsumed;
                
                $matchingWOs[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'needed' => $neededQty,
                    'already_consumed' => $alreadyConsumed,
                    'remaining_needed' => $remainingNeeded,
                ];
            }
        }
    }
    
    $this->wos = $matchingWOs;
    $this->totalNeeded = $totalNeeded;
    $this->totalFulfilled = $totalFulfilled;
    $this->stock = DB::table('item_warehouse')->where('item_id', $this->itemId)->sum('stock') ?? 0;
    
    $newAllocations = [];
    foreach ($this->wos as $wo) {
        $existing = $this->allocations[$wo['order_id']] ?? null;
        $newAllocations[$wo['order_id']] = [
            'input_qty' => $existing ? $existing['input_qty'] : 0,
            'scanned_labels' => $existing ? $existing['scanned_labels'] : [],
        ];
    }
    $this->allocations = $newAllocations;
};

on(['open-material-fulfillment-modal' => function ($itemId) {
    \Illuminate\Support\Facades\Log::info('Triggered material modal', ['itemId' => $itemId]);
    $this->itemId = $itemId;
    $this->allocations = [];
    $this->manualBarcode = '';
    $this->refreshData();
    $this->show = true;
}]);

$autoAllocate = function () {
    $item = Item::find($this->itemId);
    if ($item && $item->requires_label) {
        \Flux::toast('Auto-Allocate hanya dapat digunakan untuk produk tanpa label serial.', variant: 'warning');
        return;
    }
    
    $remainingStock = $this->stock;
    foreach ($this->wos as $wo) {
        $toFill = min($wo['remaining_needed'], $remainingStock);
        $this->allocations[$wo['order_id']]['input_qty'] = $toFill;
        $remainingStock -= $toFill;
    }
    
    \Flux::toast('Alokasi stok otomatis berhasil dipetakan.', variant: 'success');
};

$handleScan = function ($code, $targetOrderId = null) {
    $code = trim($code);
    if (empty($code)) return;
    
    $label = ItemLabel::with('item')->where('label_code', $code)->first();
    if (!$label) {
        \Flux::toast('Barcode tidak terdaftar dalam sistem.', variant: 'danger');
        return;
    }
    
    if ($label->item_id != $this->itemId) {
        $item = Item::find($this->itemId);
        $itemName = $item ? $item->name : 'Item ini';
        \Flux::toast("Barcode {$code} adalah barang {$label->item->name}, bukan {$itemName}.", variant: 'danger');
        return;
    }
    
    if ($label->status !== 'in_stock') {
        \Flux::toast("Barcode {$code} tidak tersedia (status: {$label->status}).", variant: 'danger');
        return;
    }
    
    foreach ($this->allocations as $oId => $alloc) {
        foreach ($alloc['scanned_labels'] as $sl) {
            if ($sl['id'] === $label->id) {
                \Flux::toast("Barcode {$code} sudah di-scan di modal ini.", variant: 'warning');
                return;
            }
        }
    }
    
    if ($targetOrderId) {
        $targetWO = collect($this->wos)->firstWhere('order_id', $targetOrderId);
        if (!$targetWO) return;
        
        $alloc = &$this->allocations[$targetOrderId];
        if ($alloc['input_qty'] >= $targetWO['remaining_needed']) {
            \Flux::toast("Kebutuhan bahan untuk {$targetWO['order_number']} sudah terpenuhi.", variant: 'warning');
            return;
        }
        
        $alloc['scanned_labels'][] = [
            'id' => $label->id,
            'code' => $label->label_code,
            'warehouse_id' => $label->warehouse_id
        ];
        $alloc['input_qty']++;
        \Flux::toast("Scan berhasil dimasukkan ke {$targetWO['order_number']}.", variant: 'success');
    } else {
        foreach ($this->wos as $wo) {
            $alloc = &$this->allocations[$wo['order_id']];
            if ($alloc['input_qty'] < $wo['remaining_needed']) {
                $alloc['scanned_labels'][] = [
                    'id' => $label->id,
                    'code' => $label->label_code,
                    'warehouse_id' => $label->warehouse_id
                ];
                $alloc['input_qty']++;
                \Flux::toast("Scan dialokasikan otomatis ke {$wo['order_number']}.", variant: 'success');
                return;
            }
        }
        \Flux::toast("Seluruh SPK dalam antrean bahan ini sudah terpenuhi.", variant: 'warning');
    }
};
on([
    'barcode-scanned' => function ($code) {
        if (!$this->itemId || !$this->show) return;
        $this->handleScan($code, $this->targetOrderId);
    },
    'material-barcode-scanned' => function ($code, $targetOrderId = null) {
        if (!$this->itemId) return;
        $this->handleScan($code, $targetOrderId);
    }
]);

$removeScannedLabel = function($orderId, $labelIndex) {
    unset($this->allocations[$orderId]['scanned_labels'][$labelIndex]);
    $this->allocations[$orderId]['scanned_labels'] = array_values($this->allocations[$orderId]['scanned_labels']);
    $this->allocations[$orderId]['input_qty']--;
};

$save = function() {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $item = Item::find($this->itemId);
    if (!$item) return;
    
    $totalAllocated = 0;
    foreach ($this->wos as $wo) {
        $alloc = $this->allocations[$wo['order_id']] ?? null;
        if (!$alloc) continue;
        
        $inputQty = (int) $alloc['input_qty'];
        if ($inputQty < 0) {
            \Flux::toast("Kuantitas input untuk {$wo['order_number']} tidak valid.", variant: 'danger');
            return;
        }
        
        if ($inputQty > $wo['remaining_needed']) {
            \Flux::toast("Kuantitas input untuk {$wo['order_number']} melebihi sisa kekurangan ({$wo['remaining_needed']}).", variant: 'danger');
            return;
        }
        
        if ($item->requires_label && count($alloc['scanned_labels']) !== $inputQty) {
            \Flux::toast("Jumlah scan barcode untuk {$wo['order_number']} tidak sesuai dengan input kuantitas.", variant: 'danger');
            return;
        }
        
        $totalAllocated += $inputQty;
    }
    
    if ($totalAllocated > $this->stock) {
        \Flux::toast("Total alokasi bahan baku ({$totalAllocated}) melebihi stok gudang yang tersedia ({$this->stock}).", variant: 'danger');
        return;
    }
    
    if ($totalAllocated <= 0) {
        \Flux::toast("Harap isi alokasi penyerahan setidaknya pada salah satu WO.", variant: 'warning');
        return;
    }
    
    try {
        DB::transaction(function () use ($item) {
            $inventoryService = app(\App\Services\InventoryService::class);
            
            foreach ($this->wos as $wo) {
                $alloc = $this->allocations[$wo['order_id']];
                $inputQty = (int) $alloc['input_qty'];
                
                if ($inputQty <= 0) continue;
                
                $order = ProductionOrder::find($wo['order_id']);
                if (!$order) continue;
                
                if ($item->requires_label) {
                    foreach ($alloc['scanned_labels'] as $sl) {
                        $labelModel = ItemLabel::find($sl['id']);
                        $labelModel->status = 'consumed';
                        $labelModel->notes = 'Dikonsumsi untuk Produksi: ' . $order->order_number;
                        $labelModel->save();
                        
                        $inventoryService->adjustStock(
                            $this->itemId,
                            $sl['warehouse_id'],
                            1,
                            'out',
                            $order->order_number,
                            'Fulfillment bahan (terpusat). Label: ' . $sl['code']
                        );
                        
                        \Illuminate\Support\Facades\DB::table('item_warehouse')
                            ->where('item_id', $this->itemId)
                            ->where('allocated_qty', '>', 0)
                            ->orderBy('allocated_qty', 'desc')
                            ->limit(1)
                            ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('CASE WHEN allocated_qty - 1 > 0 THEN allocated_qty - 1 ELSE 0 END')]);
                    }
                } else {
                    $remainingToDeduct = $inputQty;
                    $warehouses = DB::table('item_warehouse')
                        ->where('item_id', $this->itemId)
                        ->where('stock', '>', 0)
                        ->orderBy('stock', 'desc')
                        ->lockForUpdate()
                        ->get();
                        
                    foreach ($warehouses as $wh) {
                        if ($remainingToDeduct <= 0) break;
                        
                        $deduct = min($wh->stock, $remainingToDeduct);
                        
                        $inventoryService->adjustStock(
                            $this->itemId,
                            $wh->warehouse_id,
                            $deduct,
                            'out',
                            $order->order_number,
                            'Fulfillment bahan (terpusat).'
                        );
                        
                        \Illuminate\Support\Facades\DB::table('item_warehouse')
                            ->where('item_id', $this->itemId)
                            ->where('allocated_qty', '>', 0)
                            ->orderBy('allocated_qty', 'desc')
                            ->limit(1)
                            ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('CASE WHEN allocated_qty - ' . $deduct . ' > 0 THEN allocated_qty - ' . $deduct . ' ELSE 0 END')]);
                            
                        $remainingToDeduct -= $deduct;
                    }
                }
                
                $hasDeficit = false;
                $recipeItems = [];
                if (!empty($order->custom_bom)) {
                    $customItems = json_decode($order->custom_bom, true) ?? [];
                    foreach ($customItems as $c) {
                        $recipeItems[] = (object)[
                            'item_id' => $c['item_id'],
                            'qty' => $c['qty'],
                        ];
                    }
                } else {
                    $recipe = \Modules\Production\Models\ProductionRecipe::where('item_id', $order->item_id)->where('is_active', true)->first();
                    if ($recipe) {
                        $recipeItems = \Modules\Production\Models\ProductionRecipeItem::where('production_recipe_id', $recipe->id)->get();
                    }
                }
                
                foreach ($recipeItems as $ri) {
                    $itemNeeded = $ri->qty * $order->requested_qty;
                    $itemConsumed = abs(DB::table('stock_movements')
                        ->where('reference_number', $order->order_number)
                        ->where('item_id', $ri->item_id)
                        ->where('type', 'out')
                        ->sum('quantity') ?? 0);
                    
                    if ($itemConsumed < $itemNeeded) {
                        $hasDeficit = true;
                        break;
                    }
                }
                
                $order->status = $hasDeficit ? 'waiting_material' : 'material_issued';
                $order->save();
            }
        });
        
        \Flux::toast('Penyerahan bahan baku terpusat berhasil diproses.', variant: 'success');
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        $this->show = false;
        $this->itemId = null;
        $this->wos = [];
        $this->allocations = [];
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fulfillment Terpusat Error: ' . $e->getMessage());
        \Flux::toast('Gagal memproses penyerahan: ' . $e->getMessage(), variant: 'danger');
    }
};
?>

<div>
<flux:modal wire:model="show" x-on:close="$dispatch('modal-closed')" class="w-full md:w-[46rem] md:max-w-3xl">
@php $item = $this->itemId ? \Modules\Inventory\Models\Item::with('unit')->find($this->itemId) : null; @endphp
    <div class="p-3 sm:p-5">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2.5 pb-3 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg flex items-center justify-center">
                    <flux:avatar src="{{ $item?->image ? Storage::url($item->image) : '' }}" fallback="{{ substr($item?->name ?? '?', 0, 2) }}" size="sm" class="rounded-lg shadow-sm" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="md" class="truncate font-bold leading-tight">{{ $item?->name ?? 'Memuat...' }}</flux:heading>
                    <p class="mt-0.5 text-[10px] sm:text-xs text-zinc-500 font-mono">
                        SKU: <strong>{{ $item?->code ?? '-' }}</strong> &bull; Antrean: <strong>{{ count($wos) }} SPK</strong>
                    </p>
                </div>
            </div>
            <div class="flex sm:flex-col justify-between sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-zinc-100 dark:border-zinc-850">
                <span class="text-[10px] text-zinc-500 uppercase tracking-wider font-semibold">Stok Live Gudang</span>
                <span class="text-sm sm:text-base font-black {{ $stock > 0 ? 'text-zinc-900 dark:text-white' : 'text-red-500' }}">
                    {{ $stock }} <span class="text-[10px] font-normal text-zinc-400 uppercase">{{ $item?->unit->name ?? 'pcs' }}</span>
                </span>
            </div>
        </div>

        {{-- Live ATP Alert / Top Actions --}}
        <div class="mt-3 flex flex-col sm:flex-row items-center justify-between gap-2 bg-zinc-50 dark:bg-zinc-850 p-2.5 rounded-xl border border-zinc-200/50 dark:border-zinc-750">
            <div class="text-[10px] sm:text-xs flex items-center gap-1.5 w-full sm:w-auto">
                <span class="w-2.5 h-2.5 rounded-full {{ $stock >= ($totalNeeded - $totalFulfilled) ? 'bg-emerald-500 shadow-emerald-500/20' : 'bg-red-500 shadow-red-500/20' }} shadow-[0_0_8px] shrink-0"></span>
                <span class="text-zinc-650 dark:text-zinc-400">
                    Sisa Antrean: <strong class="text-zinc-950 dark:text-zinc-200">{{ max(0, $totalNeeded - $totalFulfilled) }}</strong> {{ $item?->unit->name ?? 'pcs' }} 
                    @if($stock < ($totalNeeded - $totalFulfilled))
                        <span class="text-red-500 font-semibold">(Stok Kurang!)</span>
                    @else
                        <span class="text-emerald-600 dark:text-emerald-500 font-semibold">(Mencukupi)</span>
                    @endif
                </span>
            </div>
            
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                @if($item && !$item->requires_label)
                    <flux:button size="sm" variant="subtle" icon="bolt" wire:click="autoAllocate" class="text-indigo-650 hover:text-indigo-700 bg-indigo-50 dark:bg-indigo-950/30 w-full sm:w-auto text-xs py-1">
                        Alokasi Otomatis
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Global Scanner (Shown if labels are required) --}}
        @if($item && $item->requires_label)
            <div class="mt-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 p-2.5 rounded-xl">
                <div class="text-[10px] font-bold text-indigo-700 dark:text-indigo-400 mb-1.5 flex items-center gap-1">
                    <flux:icon.qr-code class="w-3.5 h-3.5" />
                    Global Scanner (Auto-Route ke SPK)
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center gap-2">
                    <div class="flex-1 w-full">
                        <flux:input 
                            wire:model="manualBarcode" 
                            x-on:keydown.enter="$dispatch('material-barcode-scanned', { code: $wire.manualBarcode }); $wire.manualBarcode = ''" 
                            placeholder="Scan / ketik barcode..." 
                            icon="qr-code" 
                            class="!h-9 text-xs"
                        />
                    </div>
                    <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new CustomEvent('camera-scanner-modal-opened', { detail: { mode: 'continuous' } })); $wire.set('targetOrderId', null)" variant="filled" icon="camera" class="w-full sm:w-auto shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white border-none text-xs h-9" tooltip="Scan berturut-turut">
                        Scanner Kamera
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- List of SPKs/WOs --}}
        <div class="mt-4 space-y-2">
            <h4 class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Antrean SPK Terkait</h4>
            @forelse($wos as $wo)
                @php
                    $alloc = $allocations[$wo['order_id']] ?? ['input_qty' => 0, 'scanned_labels' => []];
                    $isDone = $alloc['input_qty'] >= $wo['remaining_needed'];
                @endphp
                <div wire:key="wo-row-{{ $wo['order_id'] }}" class="p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col gap-2 {{ $isDone ? 'bg-green-50/20 dark:bg-green-950/10 border-green-200 dark:border-green-900/50' : 'bg-white dark:bg-zinc-800' }}">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-1.5 py-0.5 rounded shrink-0">{{ $wo['order_number'] }}</span>
                            <div class="text-[10px] text-zinc-500">
                                Butuh: <strong class="text-zinc-700 dark:text-zinc-350">{{ $wo['needed'] }}</strong> &bull; Sisa: <strong class="text-zinc-700 dark:text-zinc-350">{{ $wo['needed'] - $wo['already_consumed'] }}</strong>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between border-t border-zinc-100 dark:border-zinc-700/50 pt-2 text-[11px]">
                            <div class="text-zinc-505">
                                Siap Serahkan: <strong class="text-rose-600 dark:text-rose-450 font-bold">{{ $wo['remaining_needed'] }}</strong> <span class="uppercase text-[9px] text-zinc-400">{{ $item?->unit->name ?? 'pcs' }}</span>
                            </div>
                            
                            {{-- Input controls --}}
                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($item?->requires_label)
                                    <div class="flex items-center gap-1">
                                        <div class="text-center font-bold text-xs px-2 py-0.5 rounded-lg border transition-all duration-300 {{ $alloc['input_qty'] > 0 ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50 shadow-[0_0_8px_rgba(16,185,129,0.25)]' : 'text-zinc-400 dark:text-zinc-500 bg-zinc-50 dark:bg-zinc-900/30 border-zinc-200 dark:border-zinc-800' }}">
                                            {{ $alloc['input_qty'] }} <span class="text-[8px] {{ $alloc['input_qty'] > 0 ? 'text-emerald-500' : 'text-zinc-400 dark:text-zinc-500' }} font-normal uppercase">Scan</span>
                                        </div>
                                        
                                        <flux:button type="button" x-on:click="Flux.modal('camera-scanner-modal').show(); window.dispatchEvent(new CustomEvent('camera-scanner-modal-opened', { detail: { mode: 'continuous' } })); $wire.set('targetOrderId', {{ $wo['order_id'] }})" variant="subtle" size="sm" icon="camera" class="!px-2 h-7" title="Scan Khusus WO Ini" />
                                    </div>
                                @else
                                    <div class="w-20">
                                        @if($stock <= 0)
                                            <div class="text-center text-[10px] font-bold text-red-500 bg-red-50 dark:bg-red-900/20 py-1 rounded border border-red-100 dark:border-red-900/50">Kosong</div>
                                        @else
                                            <flux:input 
                                                type="number" inputmode="numeric" pattern="[0-9]*" wire:model.blur="allocations.{{ $wo['order_id'] }}.input_qty" 
                                                min="0" 
                                                max="{{ min($wo['remaining_needed'], $stock) }}" 
                                                class="w-full text-center !h-7 text-xs" 
                                            />
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Daftar label serial/barcode yang berhasil di-scan --}}
                        @if(!empty($alloc['scanned_labels']))
                            <div class="pl-2 border-l-2 border-indigo-200 dark:border-indigo-900/50 flex flex-wrap gap-1 mt-1">
                                @foreach($alloc['scanned_labels'] as $labelIndex => $sl)
                                    <div class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 text-[10px] px-1.5 py-0.5 rounded border border-indigo-100 dark:border-indigo-800/50">
                                        <flux:icon.qr-code class="w-2.5 h-2.5" />
                                        {{ $sl['code'] }}
                                        <button type="button" wire:click="removeScannedLabel({{ $wo['order_id'] }}, {{ $labelIndex }})" class="ml-1 text-indigo-400 hover:text-red-500">
                                            <flux:icon.x-mark class="w-2.5 h-2.5" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-6 border-2 border-dashed border-zinc-200 dark:border-zinc-700 rounded-lg text-center text-xs text-zinc-500">
                    Tidak ada antrean SPK yang membutuhkan bahan ini.
                </div>
            @endforelse
        </div>

        {{-- Bottom Actions --}}
        <div class="mt-4 flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" wire:click="$set('show', false)" class="flex-1 sm:flex-none text-xs">Batal</flux:button>
            <flux:button variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" icon="check" :disabled="count($wos) === 0" class="flex-1 sm:flex-none text-xs">
                Serahkan Semua
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
