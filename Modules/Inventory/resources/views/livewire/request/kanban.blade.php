<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Inventory\Models\InventoryRequest;
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Str;

layout('layouts.app');
title('Pivot Permintaan Barang');

state([
    'columns' => [
        'draft' => ['title' => 'Permintaan Baru', 'color' => 'amber'],
        'review' => ['title' => 'Sedang Ditinjau', 'color' => 'blue'],
        'routed' => ['title' => 'Telah Dialihkan', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
    ],
    'search' => '',
    'promptItemId' => null,
]);

$requests = computed(function () {
    $query = InventoryRequest::with(['item', 'item.type', 'productionOrder', 'purchaseQueue'])->latest();
    if ($this->search) {
        $query->where('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query->get()->groupBy('status');
});

$routeToPurchase = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        PurchaseQueue::create([
            'item_id' => $req->item_id,
            'source_type' => 'inventory_request',
            'source_id' => $req->id,
            'requested_qty' => $req->requested_qty,
            'approved_qty' => $req->requested_qty,
            'status' => 'approved',
            'notes' => 'Dialihkan dari Pivot Gudang. Ref: ' . $req->reference_number . '. Notes: ' . $req->notes,
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'purchase';
        $req->routed_by = auth()->id();
        $req->save();
        
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \App\Events\KanbanUpdated::safeDispatch('purchase_queue');
        \Flux::toast('Berhasil dialihkan ke Antrean Pembelian.', variant: 'success');
    }
};

$routeToProduction = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        // Generate sequential PROD-0001 format
        $latestProd = ProductionOrder::orderBy('id', 'desc')->first();
        $nextId = $latestProd ? $latestProd->id + 1 : 1000;
        $orderNumber = 'PROD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        
        // --- BOM EXPLOSION ---
        $recipe = \Modules\Production\Models\ProductionRecipe::where('item_id', $req->item_id)
            ->where('is_active', true)
            ->first();
            
        if (!$recipe) {
            $this->promptItemId = $req->item_id;
            \Flux::modal('recipe-prompt')->show();
            return;
        }
            
        $hasDeficit = false;
        
        if ($recipe) {
            $recipeItems = \Illuminate\Support\Facades\DB::table('production_recipe_items')
                ->join('items', 'production_recipe_items.item_id', '=', 'items.id')
                ->where('production_recipe_id', $recipe->id)
                ->select('production_recipe_items.*', 'items.name')
                ->get();
                
            foreach ($recipeItems as $ri) {
                $needed = $ri->qty * $req->requested_qty;
                $stock = \Illuminate\Support\Facades\DB::table('item_warehouse')
                    ->where('item_id', $ri->item_id)
                    ->sum('stock') ?? 0;
                    
                $deficit = max(0, $needed - $stock);
                
                if ($deficit > 0) {
                    $hasDeficit = true;
                    // Auto-create material request in Warehouse Kanban
                    InventoryRequest::create([
                        'item_id' => $ri->item_id,
                        'source_type' => 'production',
                        'reference_number' => $orderNumber,
                        'requested_qty' => $deficit,
                        'notes' => "Auto-Generated: Defisit bahan baku untuk {$orderNumber} (Produksi {$req->item->name}). Butuh: {$needed}, Fisik: {$stock}.",
                        'status' => 'draft',
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
        // --- END BOM EXPLOSION ---
        
        ProductionOrder::create([
            'order_number' => $orderNumber,
            'item_id' => $req->item_id,
            'requested_qty' => $req->requested_qty,
            'reference_number' => $req->reference_number,
            'notes' => 'Dialihkan dari Pivot Gudang. Notes: ' . $req->notes,
            'status' => $hasDeficit ? 'waiting_material' : 'material_fulfillment',
            'created_by' => auth()->id(),
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'production';
        $req->routed_by = auth()->id();
        $req->save();
        
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        
        if ($hasDeficit) {
            \Flux::toast('Dialihkan ke Produksi. PERINGATAN: Bahan baku kurang, tiket permohonan bahan telah diterbitkan otomatis!', variant: 'warning');
        } else {
            \Flux::toast('Berhasil dialihkan ke Antrean Produksi. Bahan baku terpantau aman.', variant: 'success');
        }
    }
};

$review = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    $req = InventoryRequest::find($requestId);
    if ($req) {
        $req->status = 'review';
        $req->save();
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \Flux::toast('Permintaan sedang ditinjau.', variant: 'success');
    }
};

$reject = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    $req = InventoryRequest::find($requestId);
    if ($req) {
        $req->status = 'rejected';
        $req->save();
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
    }
};

on([
    'echo:kanban.inventory_request,KanbanUpdated' => function () {}
]);
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Pivot Permintaan Barang</flux:heading>
            <flux:subheading>Hub pusat penentuan alur defisit barang (Beli vs Produksi).</flux:subheading>
        </div>
        <div class="w-full md:w-64">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari barang atau ref..." />
        </div>
    </div>

    <div class="flex justify-start gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)] snap-x">
        @foreach($columns as $statusKey => $column)
            <div class="w-80 flex-shrink-0 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col h-full snap-center">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900 rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $column['title'] }}</h3>
                    </div>
                    <flux:badge size="sm">{{ count($this->requests[$statusKey] ?? []) }}</flux:badge>
                </div>
                
                <div class="flex-1 p-3 overflow-y-auto space-y-3">
                    @forelse($this->requests[$statusKey] ?? [] as $req)
                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ $req->reference_number }}</span>
                                <flux:badge size="sm" color="{{ $req->item->type->name === 'Produk Jadi' ? 'purple' : 'blue' }}">{{ $req->item->type->name ?? 'Unknown' }}</flux:badge>
                            </div>
                            <div class="font-bold text-sm text-zinc-900 dark:text-white mb-1">{{ $req->item->name }}</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Butuh: <span class="font-bold text-red-600">{{ $req->requested_qty }}</span> {{ $req->item->unit->name ?? 'pcs' }}</div>
                            
                            @if($req->notes)
                                <div class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded mb-3">{{ $req->notes }}</div>
                            @endif

                            @if(in_array($statusKey, ['draft', 'review']))
                                {{-- Mini Dashboard (Pipeline Stock) --}}
                                @php
                                    $stats = $req->item->getInventoryStats();
                                @endphp
                                <div class="grid grid-cols-2 gap-2 mb-3 bg-zinc-50 dark:bg-zinc-800/50 p-2.5 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
                                    <div class="flex flex-col justify-start" title="Stok aktual di gudang saat ini">
                                        <span class="text-[9px] text-zinc-500 uppercase font-bold tracking-wider mb-0.5">Stok Fisik</span>
                                        <span class="text-sm font-black text-zinc-800 dark:text-zinc-200 leading-none mb-1">{{ $stats['physical'] }} <span class="text-[10px] font-medium text-zinc-400">{{ $req->item->unit->name ?? 'pcs' }}</span></span>
                                        @if(isset($stats['warehouse_details']) && count($stats['warehouse_details']) > 0)
                                            <div class="flex flex-col gap-0.5 mt-0.5">
                                                @foreach($stats['warehouse_details'] as $wh)
                                                    @if($wh['stock'] > 0)
                                                        <span class="text-[8px] text-zinc-500 leading-tight flex items-center gap-1">
                                                            <div class="w-1 h-1 rounded-full bg-zinc-300"></div>
                                                            <span class="truncate max-w-[60px]" title="{{ $wh['name'] }}">{{ $wh['name'] }}</span>: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $wh['stock'] }}</span>
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex flex-col" title="Jumlah yang sedang diproduksi (WIP)">
                                        <span class="text-[9px] text-purple-500 uppercase font-bold tracking-wider mb-0.5">Produksi</span>
                                        <span class="text-sm font-black text-purple-600 dark:text-purple-400">{{ $stats['production'] }} <span class="text-[10px] font-medium text-purple-400/70">{{ $req->item->unit->name ?? 'pcs' }}</span></span>
                                    </div>
                                    <div class="flex flex-col" title="Jumlah yang sedang antre untuk dibeli (belum PO)">
                                        <span class="text-[9px] text-amber-500 uppercase font-bold tracking-wider mb-0.5">Antre Beli</span>
                                        <span class="text-sm font-black text-amber-600 dark:text-amber-400">{{ $stats['purchase_queue'] }} <span class="text-[10px] font-medium text-amber-400/70">{{ $req->item->unit->name ?? 'pcs' }}</span></span>
                                    </div>
                                    <div class="flex flex-col" title="Jumlah yang sudah dipesan ke Vendor (sudah PO)">
                                        <span class="text-[9px] text-blue-500 uppercase font-bold tracking-wider mb-0.5">Sedang Beli</span>
                                        <span class="text-sm font-black text-blue-600 dark:text-blue-400">{{ $stats['purchase_order'] }} <span class="text-[10px] font-medium text-blue-400/70">{{ $req->item->unit->name ?? 'pcs' }}</span></span>
                                    </div>
                                    @if($stats['sales_committed'] > 0)
                                        <div class="flex flex-col col-span-2 pt-2 mt-1 border-t border-zinc-200 dark:border-zinc-700/50" title="Pesanan Pelanggan yang sedang menunggu barang ini">
                                            <span class="text-[9px] text-rose-500 uppercase font-bold tracking-wider mb-0.5">Kebutuhan / Pesanan Penjualan</span>
                                            <span class="text-sm font-black text-rose-600 dark:text-rose-400">{{ $stats['sales_committed'] }} <span class="text-[10px] font-medium text-rose-400/70">{{ $req->item->unit->name ?? 'pcs' }}</span></span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if($statusKey !== 'routed' && $statusKey !== 'rejected')
                                <div class="flex flex-col gap-2 mt-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                    @if($statusKey === 'draft')
                                        <div class="flex gap-2 w-full">
                                            <flux:button size="sm" variant="subtle" wire:click="review({{ $req->id }})" class="flex-1">Mulai Tinjau</flux:button>
                                            <flux:button size="sm" variant="danger" wire:click="reject({{ $req->id }})" class="flex-1">Tolak</flux:button>
                                        </div>
                                    @elseif($statusKey === 'review')
                                        @php
                                            $isProdukJadi = strtolower($req->item->type->name ?? '') === 'produk jadi';
                                        @endphp
                                        <div class="flex gap-2 w-full">
                                            @if($isProdukJadi)
                                                <flux:button size="sm" variant="filled" icon="cog-8-tooth" wire:click="routeToProduction({{ $req->id }})" class="flex-1 !bg-purple-600 hover:!bg-purple-700 !text-white text-[11px]" title="Rekomendasi Utama">Produksi</flux:button>
                                                <flux:dropdown>
                                                    <flux:button size="sm" variant="subtle" class="shrink-0 px-2" title="Opsi Lainnya"><flux:icon.ellipsis-vertical class="w-4 h-4" /></flux:button>
                                                    <flux:menu>
                                                        <flux:menu.item icon="shopping-cart" wire:click="routeToPurchase({{ $req->id }})">Beli (Pengecualian)</flux:menu.item>
                                                        <flux:menu.item icon="x-mark" variant="danger" wire:click="reject({{ $req->id }})">Tolak Permintaan</flux:menu.item>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            @else
                                                <flux:button size="sm" variant="primary" icon="shopping-cart" wire:click="routeToPurchase({{ $req->id }})" class="flex-1 text-[11px]" title="Rekomendasi Utama">Beli</flux:button>
                                                <flux:dropdown>
                                                    <flux:button size="sm" variant="subtle" class="shrink-0 px-2" title="Opsi Lainnya"><flux:icon.ellipsis-vertical class="w-4 h-4" /></flux:button>
                                                    <flux:menu>
                                                        <flux:menu.item icon="cog-8-tooth" wire:click="routeToProduction({{ $req->id }})">Produksi (Pengecualian)</flux:menu.item>
                                                        <flux:menu.item icon="x-mark" variant="danger" wire:click="reject({{ $req->id }})">Tolak Permintaan</flux:menu.item>
                                                    </flux:menu>
                                                </flux:dropdown>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @elseif($statusKey === 'routed')
                                <div class="text-xs font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 p-2 rounded text-center mt-2 flex flex-col gap-1">
                                    <span>Telah dialihkan ke: <strong>{{ strtoupper($req->routed_to) }}</strong></span>
                                    @if($req->routed_to === 'production' && $req->productionOrder)
                                        @php
                                            $prodStatusMap = [
                                                'waiting_material' => 'Antre Bahan',
                                                'material_fulfillment' => 'Pemenuhan Bahan',
                                                'waiting_vendor' => 'Antre Maklon',
                                                'in_production' => 'Sedang Diproduksi',
                                                'receiving' => 'Penerimaan Gudang',
                                                'completed' => 'Selesai',
                                                'archived' => 'Arsip'
                                            ];
                                            $liveStatus = $prodStatusMap[$req->productionOrder->status] ?? $req->productionOrder->status;
                                        @endphp
                                        <span class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 mt-0.5 border-t border-emerald-200/50 dark:border-emerald-800/50 pt-1">(Status Pabrik: {{ $liveStatus }})</span>
                                    @elseif($req->routed_to === 'purchase' && $req->purchaseQueue)
                                        @php
                                            $purchStatusMap = [
                                                'pending_approval' => 'Menunggu Persetujuan',
                                                'approved' => 'Antre PO',
                                                'ordered' => 'Dipesan (PO)',
                                                'rejected' => 'Ditolak'
                                            ];
                                            $liveStatus = $purchStatusMap[$req->purchaseQueue->status] ?? $req->purchaseQueue->status;
                                        @endphp
                                        <span class="text-[10px] text-emerald-700/80 dark:text-emerald-400/80 mt-0.5 border-t border-emerald-200/50 dark:border-emerald-800/50 pt-1">(Status Beli: {{ $liveStatus }})</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-sm text-zinc-400 py-4">Kosong</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <div>
        <flux:modal name="recipe-prompt" class="md:w-[28rem]">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mb-4 mx-auto">
                    <flux:icon.exclamation-triangle class="w-8 h-8" />
                </div>
                <flux:heading size="xl" class="text-red-600 dark:text-red-400 mb-2 text-center">Resep (BOM) Belum Dibuat!</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-8 text-center">Barang ini belum memiliki resep (BOM) aktif di sistem. Sistem tidak bisa memecah dan menghitung bahan baku yang dibutuhkan tanpa adanya resep.</p>
                <div class="flex gap-3">
                    <flux:button variant="ghost" x-on:click="$flux.modal('recipe-prompt').close()" class="flex-1">Batal</flux:button>
                    <flux:button variant="primary" icon="document-plus" x-on:click="$wire.dispatch('open-recipe-modal', { itemId: $wire.promptItemId }); $flux.modal('recipe-prompt').close()" class="flex-[2]">Buat Resep Sekarang</flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
    
    <livewire:recipe.form-modal />
    <livewire:global.item-gallery-modal context="inventory" />
</div>
