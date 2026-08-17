<?php
use function Livewire\Volt\{state, layout, title, computed, on, usesPagination, mount};
use Modules\Inventory\Models\InventoryRequest;
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Str;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Pivot Permintaan Barang');

state([
    'viewMode' => 'kanban',
    'columns' => [
        'draft' => ['title' => 'Permintaan Baru', 'color' => 'amber'],
        'review' => ['title' => 'Sedang Ditinjau', 'color' => 'blue'],
        'routed' => ['title' => 'Telah Dialihkan', 'color' => 'emerald'],
        'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
    ],
    'search' => '',
    'promptItemId' => null,
    'transparent_columns' => false,
    'woTargetRequestId' => null,
    'woTargetItemName' => '',
    'woTargetQty' => 1,
    'woNotes' => '',
    'columnLimits' => [],
]);

mount(function () {
    $this->viewMode = session()->get('inventory_request_view_mode', 'kanban');
    $limits = [];
    foreach ($this->columns as $key => $col) {
        $limits[$key] = 24;
    }
    $this->columnLimits = $limits;
});

$loadMoreColumn = function ($status) {
    $limits = $this->columnLimits;
    if (!isset($limits[$status])) {
        $limits[$status] = 24;
    }
    $limits[$status] += 24;
    $this->columnLimits = $limits;
};

$updatedViewMode = function ($value) {
    session()->put('inventory_request_view_mode', $value);
};

$tableRequests = computed(function () {
    if ($this->viewMode !== 'table') {
        return null;
    }
    
    $query = InventoryRequest::with(['item', 'item.type', 'productionOrder', 'purchaseQueue'])->latest();
    if ($this->search) {
        $query->where('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query->paginate(15);
});

$getBaseQuery = function () {
    $query = InventoryRequest::query();
    if ($this->search) {
        $query->where('reference_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('item', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
              });
    }
    return $query;
};

$requests = computed(function () {
    if ($this->viewMode !== 'kanban') {
        return [];
    }
    
    $ids = [];
    foreach ($this->columns as $status => $col) {
        $limit = $this->columnLimits[$status] ?? 24;
        $q = clone $this->getBaseQuery();
        
        $ids = array_merge($ids, $q->where('status', $status)->latest('updated_at')->limit($limit)->pluck('id')->toArray());
    }
    
    if (empty($ids)) return collect();
    
    return InventoryRequest::with(['item', 'item.type', 'productionOrder', 'purchaseQueue'])
        ->whereIn('id', $ids)
        ->get()
        ->sortByDesc('updated_at')
        ->groupBy('status');
});

$routeToPurchase = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        $newQueue = PurchaseQueue::create([
            'item_id' => $req->item_id,
            'source_type' => 'inventory_request',
            'source_id' => $req->id,
            'requested_qty' => $req->requested_qty,
            'approved_qty' => $req->requested_qty,
            'status' => 'approved',
            'notes' => 'Dialihkan dari Pivot Gudang. Ref: ' . $req->reference_number . '. Notes: ' . $req->notes,
            'custom_attributes' => $req->custom_attributes,
            'custom_attachments' => $req->custom_attachments,
        ]);
        
        // Kirim notifikasi ke semua staf purchasing dan Super Admin
        $newQueue->load('item.unit');
        $recipients = \App\Models\User::permission('purchase.queue.view')
            ->orWhereHas('roles', function($q) { $q->where('name', 'Super Admin'); })
            ->get();
        
        if ($recipients->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new \App\Notifications\PurchaseQueueCreatedNotification($newQueue)
            );
        }
        
        $req->status = 'routed';
        $req->routed_to = 'purchase';
        $req->routed_by = auth()->id();
        $req->save();
        
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \App\Events\KanbanUpdated::safeDispatch('purchase_queue');
        \Flux::toast('Berhasil dialihkan ke Antrean Pembelian.', variant: 'success');
    }
};

$openCreateWoModal = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($requestId);
    if ($req && $req->status !== 'routed') {
        $this->woTargetRequestId = $req->id;
        $this->woTargetItemName = $req->item->name;
        $this->woTargetQty = $req->requested_qty;
        $this->woNotes = '';
        \Flux::modal('create-wo-modal')->show();
    }
};

$confirmRouteToProduction = function () {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    
    $req = InventoryRequest::with('item')->find($this->woTargetRequestId);
    if ($req && $req->status !== 'routed') {
        // Intercept Custom BOM for Make-To-Order
        if (str_contains($req->notes ?? '', '[CUSTOM]')) {
            \Flux::modal('create-wo-modal')->close();
            $this->dispatch('open-custom-bom-modal', requestId: $req->id, qty: $this->woTargetQty);
            return;
        }
        // Generate sequential PROD-0001 format
        $latestProd = ProductionOrder::orderBy('id', 'desc')->first();
        $nextId = $latestProd ? $latestProd->id + 1 : 1;
        $orderNumber = 'PROD-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        
        // --- BOM EXPLOSION ---
        $recipe = \Modules\Production\Models\ProductionRecipe::where('item_id', $req->item_id)
            ->where('is_active', true)
            ->first();
            
        if (!$recipe) {
            $this->promptItemId = $req->item_id;
            \Flux::modal('create-wo-modal')->close();
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
                $needed = $ri->qty * $this->woTargetQty; // Use modal qty
                $stock = \Illuminate\Support\Facades\DB::table('item_warehouse')
                    ->where('item_id', $ri->item_id)
                    ->sum('stock') ?? 0;
                $allocated = \Illuminate\Support\Facades\DB::table('item_warehouse')
                    ->where('item_id', $ri->item_id)
                    ->sum('allocated_qty') ?? 0;
                
                $atp = $stock - $allocated;
                $deficit = max(0, $needed - $atp);
                
                // Reservasi stok (alokasi)
                $updated = \Illuminate\Support\Facades\DB::table('item_warehouse')
                    ->where('item_id', $ri->item_id)
                    ->orderBy('stock', 'desc')
                    ->limit(1)
                    ->update(['allocated_qty' => \Illuminate\Support\Facades\DB::raw('allocated_qty + ' . $needed)]);

                if (!$updated) {
                    \Illuminate\Support\Facades\DB::table('item_warehouse')->insert([
                        'item_id' => $ri->item_id,
                        'warehouse_id' => 1, // Default warehouse
                        'stock' => 0,
                        'allocated_qty' => $needed,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                if ($deficit > 0) {
                    $hasDeficit = true;
                    // Auto-create material request in Warehouse Kanban
                    InventoryRequest::create([
                        'item_id' => $ri->item_id,
                        'source_type' => 'production',
                        'reference_number' => $orderNumber,
                        'requested_qty' => $deficit,
                        'notes' => "Auto-Generated: Defisit bahan baku untuk {$orderNumber} (Produksi {$req->item->name}). Butuh: {$needed}, ATP: {$atp} (Fisik: {$stock}, Dipesan: {$allocated}).",
                        'status' => 'draft',
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }
        // --- END BOM EXPLOSION ---
        
        // Jika material tidak lengkap -> 'waiting_material'.
        // Jika material lengkap -> cek require_production_approval.
        $prodStatus = $hasDeficit ? 'waiting_material' : (requires_production_approval() ? 'pending_approval' : 'material_fulfillment');
        
        ProductionOrder::create([
            'order_number' => $orderNumber,
            'item_id' => $req->item_id,
            'requested_qty' => $this->woTargetQty,
            'reference_number' => $req->reference_number,
            'notes' => 'Dialihkan dari Pivot Gudang. Notes: ' . $this->woNotes . ($req->notes ? " | Ref: " . $req->notes : ""),
            'status' => $prodStatus,
            'created_by' => auth()->id(),
        ]);
        
        $req->status = 'routed';
        $req->routed_to = 'production';
        $req->routed_by = auth()->id();
        $req->save();
        
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \App\Events\KanbanUpdated::safeDispatch('production_order');
        
        \Flux::modal('create-wo-modal')->close();

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

$archive = function ($requestId) {
    abort_unless(auth()->user()->can('inventory.request.update'), 403);
    $req = InventoryRequest::find($requestId);
    if ($req) {
        $req->status = 'archived';
        $req->save();
        \App\Events\KanbanUpdated::safeDispatch('inventory_request');
        \Flux::toast('Tiket diarsipkan.', variant: 'success');
    }
};

on([
    'echo:kanban,KanbanUpdated' => function () {},
    'status-updated' => function () {}
]);
?>

<div>
<x-kanban.board componentId="inventory_request" :viewMode="$viewMode" title="Pivot Permintaan Barang" subtitle="Hub pusat penentuan alur defisit barang (Beli vs Produksi).">
    
    <x-slot:header_actions>
        @can('inventory.request.create')
            <flux:button variant="primary" size="sm" icon="plus" wire:click="$dispatch('open-create-request-modal')" class="px-2 sm:px-4 shrink-0">
                <span class="hidden sm:inline">Buat Permintaan</span>
                <span class="sm:hidden text-xs">Buat</span>
            </flux:button>
        @endcan
    </x-slot:header_actions>
    

            @foreach($columns as $statusKey => $column)
                @php
                    $defaultCollapsed = in_array($statusKey, ['archived', 'rejected']);
                @endphp
                <x-kanban.column 
                    :statusKey="$statusKey" 
                    :column="$column" 
                    :componentId="'inv'" 
                    :count="count($this->requests[$statusKey] ?? [])"
                    :defaultCollapsed="$defaultCollapsed"
                >
                        @forelse($this->requests[$statusKey] ?? [] as $req)
                            @php
                                $isCustom = str_contains($req->notes ?? '', '[CUSTOM]');
                                $displayNotes = str_replace(' [CUSTOM]', '', $req->notes);
                            @endphp
                            <div wire:key="req-{{ $req->id }}" @click="activeId = '{{ $req->id }}'" :class="{ 'opacity-50 pointer-events-none animate-pulse grayscale': processingId === '{{ $req->id }}' }" class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border transition-all duration-300 {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:border-amber-500 hover:shadow-amber-500/30' : 'border-zinc-200 dark:border-zinc-700 hover:border-blue-400 dark:hover:border-blue-500' }} relative overflow-hidden">
                                @if($isCustom)
                                    <div class="absolute top-0 right-0 w-24 h-24 pointer-events-none opacity-40 dark:opacity-20">
                                        <div class="absolute inset-0 bg-gradient-to-bl from-amber-400 to-transparent"></div>
                                    </div>
                                @endif
                                <div class="flex justify-between items-start mb-2 relative z-10">
                                    <div class="flex flex-col gap-1.5">
                                        <span wire:click="$dispatch('open-request-detail-modal', { requestId: {{ $req->id }} })" class="cursor-pointer text-xs font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded hover:bg-blue-100 hover:text-blue-700 transition-colors w-max" title="Lihat Detail">{{ $req->reference_number }}</span>
                                        @if($isCustom)
                                            <span class="text-[10px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-amber-500 to-orange-500 px-2 py-0.5 rounded shadow-sm shadow-amber-500/40 flex items-center gap-1 w-max" title="Pesanan dengan permintaan spesifikasi khusus">
                                                <flux:icon.sparkles class="w-3 h-3" /> Custom
                                            </span>
                                        @endif
                                    </div>
                                    <flux:badge size="sm" color="{{ $req->item->type->name === 'Produk Jadi' ? 'purple' : 'blue' }}">{{ $req->item->type->name ?? 'Unknown' }}</flux:badge>
                                </div>
                                <div class="font-bold text-sm text-zinc-900 dark:text-white mb-1 relative z-10">{{ $req->item->name }}</div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-3 relative z-10">Butuh: <span class="font-bold text-red-600">{{ $req->requested_qty }}</span> {{ $req->item->unit->name ?? 'pcs' }}</div>
                                
                                @if($displayNotes)
                                    <div class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded mb-3 relative z-10">{{ $displayNotes }}</div>
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
                                                    <flux:button size="sm" variant="filled" icon="cog-8-tooth" wire:click="openCreateWoModal({{ $req->id }})" class="flex-1 !bg-purple-600 hover:!bg-purple-700 !text-white text-[11px]" title="Rekomendasi Utama">Produksi</flux:button>
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
                                                            <flux:menu.item icon="cog-8-tooth" wire:click="openCreateWoModal({{ $req->id }})">Produksi (Pengecualian)</flux:menu.item>
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
                                    <div class="mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800 w-full">
                                        <flux:button size="xs" variant="subtle" wire:click="archive({{ $req->id }})" class="w-full !text-[10px]">Pindahkan ke Arsip</flux:button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                                Kosong
                            </div>
                        @endforelse
                </x-kanban.column>
            @endforeach


    <x-slot:table_layout>
        <div class="p-6">
            <flux:table class="table-mobile-cards">
                <flux:table.columns>
                    <flux:table.column>Referensi & Barang</flux:table.column>
                    <flux:table.column>Kebutuhan</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Tindakan</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @if($this->tableRequests)
                        @forelse($this->tableRequests as $req)
                            <flux:table.row wire:key="row-{{ $req->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <flux:table.cell>
                                    <div class="flex items-start gap-3 mt-1 sm:mt-0">
                                        <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                            @if ($req->item?->image)
                                                <img src="{{ asset('storage/' . $req->item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                    <flux:icon.photo class="w-5 h-5" />
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-medium text-sm text-zinc-900 dark:text-white line-clamp-1">
                                                {{ $req->item->name }}
                                            </div>
                                            <div class="text-[11px] text-zinc-500 flex items-center gap-1.5 mt-0.5">
                                                <span wire:click="$dispatch('open-request-detail-modal', { requestId: {{ $req->id }} })" class="cursor-pointer font-mono bg-zinc-100 dark:bg-zinc-800 px-1 rounded hover:bg-blue-100 hover:text-blue-700 transition-colors" title="Lihat Detail">{{ $req->reference_number }}</span>
                                                <flux:badge size="sm" color="{{ $req->item->type->name === 'Produk Jadi' ? 'purple' : 'blue' }}">{{ $req->item->type->name ?? 'Unknown' }}</flux:badge>
                                            </div>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-1.5 mt-1 sm:mt-0">
                                        <span class="text-[11px] text-zinc-500 sm:hidden">Kebutuhan:</span>
                                        <span class="font-bold text-red-600">{{ $req->requested_qty }}</span> <span class="text-[11px] text-zinc-500">{{ $req->item->unit->name ?? 'pcs' }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="card-status-overlay">
                                    @php
                                        $col = $columns[$req->status] ?? null;
                                        $color = $col ? $col['color'] : 'zinc';
                                    @endphp
                                    <flux:badge size="sm" color="{{ $color }}">{{ $col['title'] ?? ucfirst($req->status) }}</flux:badge>
                                    
                                    @if($req->status === 'routed')
                                        <div class="text-[10px] text-emerald-600 mt-1 hidden sm:block">Dialihkan: {{ strtoupper($req->routed_to) }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                        @if($req->status === 'draft')
                                            <flux:button size="sm" variant="subtle" wire:click="review({{ $req->id }})">Tinjau</flux:button>
                                        @elseif($req->status === 'review')
                                            @php
                                                $isProdukJadi = strtolower($req->item->type->name ?? '') === 'produk jadi';
                                            @endphp
                                            @if($isProdukJadi)
                                                <flux:button size="sm" variant="filled" class="!bg-purple-600 hover:!bg-purple-700 !text-white text-[11px]" wire:click="openCreateWoModal({{ $req->id }})">Produksi</flux:button>
                                            @else
                                                <flux:button size="sm" variant="primary" class="text-[11px]" wire:click="routeToPurchase({{ $req->id }})">Beli</flux:button>
                                            @endif
                                            
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="subtle" class="px-2"><flux:icon.ellipsis-vertical class="w-4 h-4" /></flux:button>
                                                <flux:menu>
                                                    @if($isProdukJadi)
                                                        <flux:menu.item icon="shopping-cart" wire:click="routeToPurchase({{ $req->id }})">Beli (Pengecualian)</flux:menu.item>
                                                    @else
                                                        <flux:menu.item icon="cog-8-tooth" wire:click="openCreateWoModal({{ $req->id }})">Produksi (Pengecualian)</flux:menu.item>
                                                    @endif
                                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="reject({{ $req->id }})">Tolak</flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @elseif($req->status === 'routed')
                                            <flux:button size="sm" variant="subtle" wire:click="archive({{ $req->id }})">Arsipkan</flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="5" class="text-center text-zinc-500 py-8">Tidak ada data permintaan barang.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    @endif
                </flux:table.rows>
            </flux:table>
            
            @if($this->tableRequests && $this->tableRequests->hasPages())
                <div class="mt-4">
                    {{ $this->tableRequests->links() }}
                </div>
            @endif
        </div>
    </x-slot:table_layout>
</x-kanban.board>

<div>
    <flux:modal name="recipe-prompt" class="md:w-[28rem]">
            <div class="p-6">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 rounded-full flex items-center justify-center mb-4 mx-auto">
                    <flux:icon.exclamation-triangle class="w-8 h-8" />
                </div>
                <flux:heading size="xl" class="text-red-600 dark:text-red-400 mb-2 text-center">Resep (BOM) Belum Dibuat!</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-8 text-center">Barang ini belum memiliki resep (BOM) aktif di sistem. Sistem tidak bisa memecah dan menghitung bahan baku yang dibutuhkan tanpa adanya resep.</p>
                <div class="flex gap-3">
                    <flux:button variant="ghost" x-on:click="$flux.modal('recipe-prompt').close()" class="flex-1"> Batal </flux:button>
                    <flux:button variant="primary" icon="document-plus" x-on:click="$wire.dispatch('open-recipe-modal', { itemId: $wire.promptItemId }); $flux.modal('recipe-prompt').close()" class="flex-[2]"> Buat Resep Sekarang </flux:button>
                </div>
            </div>
        </flux:modal>
    </div>
    
    <flux:modal name="create-wo-modal" class="md:w-[32rem]">
        <div class="p-6">
            <flux:heading size="xl" class="mb-4">Buat Perintah Kerja (WO)</flux:heading>
            <p class="text-sm text-zinc-500 mb-6">Tentukan target produksi dan rute untuk <span class="font-bold text-zinc-900 dark:text-white">{{ $woTargetItemName }}</span>.</p>
            
            <div class="space-y-5">
                <flux:input wire:model="woTargetQty" type="number" inputmode="numeric" pattern="[0-9]*" min="1" label="Target Kuantitas" />
                

                
                <flux:textarea wire:model="woNotes" label="Catatan Tambahan (Opsional)" placeholder="Catatan untuk tim produksi/gudang..." />
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <flux:button variant="ghost" x-on:click="$flux.modal('create-wo-modal').close()"> Batal </flux:button>
                <flux:button variant="primary" wire:click="confirmRouteToProduction">Terbitkan WO</flux:button>
            </div>
        </div>
    </flux:modal>
    
<div x-data="{ items: [] }" @update-request-items.window="items = $event.detail.items">
    <livewire:recipe.form-modal />
    <livewire:request.create-modal />
    <livewire:request.request-detail-modal />
    <livewire:request.custom-bom-modal />
    <livewire:global.item-gallery-modal context="inventory" />
    <livewire:global.item-form-modal wire:key="kanban-item-form-modal" />
</div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
    }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: #334155;
    }
    .vertical-text {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
    }
</style>
</div>
