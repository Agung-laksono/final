<?php

use function Livewire\Volt\{state, on, computed};
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockMovement;
use Flux\Flux;

state([
    'item' => null,
    'tab' => 'info',
    'in_this_month' => 0,
    'out_this_month' => 0,
    'avg_out_per_day' => 0,
    'movements' => [],
    'initial_stock_warehouse_id' => '',
    'initial_stock_qty' => 1,
    'initial_stock_notes' => 'Saldo Awal',
    'production' => 0,
    'purchase_queue' => 0,
    'purchase_order' => 0,
    'sales_committed' => 0,
    'atp' => 0,
    'groupVariants' => false,
    'custom_variants_count' => 0, // Disimpan terpisah agar tidak hilang saat Livewire rehydration
]);

$customVariantsList = computed(function () {
    if (!$this->item) return collect();
    
    return \Modules\Sales\Models\SalesOrderItem::with('salesOrder.customer')
        ->where('item_id', $this->item->id)
        ->whereNotNull('custom_attributes')
        ->latest('id')
        ->limit(100)
        ->get();
});

$fetchData = function ($id) {
    $this->item = Item::with([
        'category', 'subCategory', 'unit', 'type', 'warehouses'
    ])
        ->withCount('customVariants')
        ->findOrFail($id);
    
    // Simpan count secara terpisah sebagai integer — integer tidak kehilangan nilai saat Livewire rehydration
    $this->custom_variants_count = (int) $this->item->custom_variants_count;
    
    // Ambil data pergerakan stok
    $allMovements = clone StockMovement::with(['warehouse', 'user'])
        ->where('item_id', $id)
        ->latest('created_at')
        ->get();
        
    $startOfMonth = now()->startOfMonth();
    $this->in_this_month = $allMovements->where('quantity', '>', 0)->where('created_at', '>=', $startOfMonth)->sum('quantity');
    $this->out_this_month = abs($allMovements->where('quantity', '<', 0)->where('created_at', '>=', $startOfMonth)->sum('quantity'));
    $this->avg_out_per_day = round($this->out_this_month / max(1, now()->day), 1);
    
    $this->movements = $allMovements->take(50);
    
    // Fetch pipeline stats
    $stats = $this->item->getInventoryStats();
    $this->production = $stats['production'] ?? 0;
    $this->purchase_queue = $stats['purchase_queue'] ?? 0;
    $this->purchase_order = $stats['purchase_order'] ?? 0;
    $this->sales_committed = $stats['sales_committed'] ?? 0;
    $this->atp = $this->item->getATP();
};

$switchTab = function ($newTab) {
    // Simulasi delay kecil untuk dev environment (hapus jika sudah jalan di production)
    // sleep(1); 
    $this->tab = $newTab;
};

$openModal = function ($id, $tab = null) {
    // Jika item yang sama sudah terbuka dan tab tidak dieksplisit, pertahankan tab saat ini
    if ($this->item && $this->item->id == $id && $tab === null) {
        $tab = $this->tab;
    } else {
        $tab = $tab ?? 'info';
    }
    
    $this->fetchData($id);
    
    $this->initial_stock_warehouse_id = '';
    $this->initial_stock_qty = 1;
    $this->initial_stock_notes = 'Saldo Awal';
    $this->tab = $tab; 
    
    $this->dispatch('item-detail-modal-opened', ['tab' => $tab]);
    // Dispatch setelah 3x queueMicrotask — Alpine punya waktu inisialisasi sebelum modal dibuka
    $this->dispatch('do-open-item-detail');
};

on(['open-item-detail' => function (...$args) {
    // Tangani parameter dinamis dari $dispatch Alpine maupun $wire.dispatch
    if (count($args) > 0 && is_array($args[0])) {
        $id = $args[0]['id'] ?? null;
        // Jika tab tidak dieksplisit dalam event, kirim null agar tab saat ini dipertahankan
        $tab = array_key_exists('tab', $args[0]) ? $args[0]['tab'] : null;
    } else if (isset($args['id'])) {
        $id = $args['id'];
        $tab = $args['tab'] ?? null;
    } else {
        $id = $args[0] ?? null;
        $tab = $args[1] ?? null;
    }
    
    if ($id) {
        $this->openModal($id, $tab);
    }
}]);


$deleteItem = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.item.delete');
    
    if ($this->item->image) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($this->item->image);
    }
    
    $this->item->delete();
    Flux::modal('item-detail-modal')->close();
    $this->dispatch('item-deleted');
    Flux::toast('Barang berhasil dihapus.', variant: 'success');
};

$toggleActive = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.item.update');
    $this->item->update([
        'is_active' => !$this->item->is_active
    ]);
    
    $this->dispatch('item-updated'); // Beritahu list user ini untuk refresh
    
    // Beritahu user lain secara realtime via Reverb
    \App\Events\InventoryUpdated::safeDispatch('Status barang ' . $this->item->code . ' diperbarui');
    
    // Kirim notifikasi global bahwa status aktif diubah
    $recipients = \App\Models\User::permission('inventory.notifikasi.view')
        ->orWhereHas('roles', fn($q) => $q->where('name', 'Super Admin'))
        ->get();
    \Illuminate\Support\Facades\Notification::send($recipients, new \App\Notifications\ItemStatusChangedNotification($this->item, auth()->user()));
    
    $status = $this->item->is_active ? 'diaktifkan' : 'dinonaktifkan';
    Flux::toast("Barang berhasil $status.", variant: 'success');
};

$approveItem = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.item.update');
    $this->item->update([
        'is_approved' => true,
        'is_active' => true
    ]);
    
    $this->dispatch('item-updated'); 
    
    \App\Events\InventoryUpdated::safeDispatch('Barang ' . $this->item->code . ' telah disetujui');
    
    // Kirim notifikasi ke user pembuat (staf sales/purchasing dll)
    if ($this->item->user_id) {
        $creator = \App\Models\User::find($this->item->user_id);
        if ($creator && $creator->id !== auth()->id()) {
            $creator->notify(new \App\Notifications\ItemApprovedNotification($this->item, auth()->user()));
        }
    }
    
    // Kirim event update ke sistem menu badge
    $this->dispatch('.MenuBadgesUpdated');
    
    Flux::toast("Barang berhasil disetujui dan diaktifkan.", variant: 'success');
};

$refreshItem = function () {
    if ($this->item) {
        $this->fetchData($this->item->id);
    }
};

$saveInitialStock = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.item.update'); // using update permission, or maybe create?
    
    $this->validate([
        'initial_stock_warehouse_id' => 'required|exists:warehouses,id',
        'initial_stock_qty' => 'required|integer|min:1',
        'initial_stock_notes' => 'nullable|string|max:255',
    ]);

    \Illuminate\Support\Facades\DB::beginTransaction();
    try {
        $warehouseId = $this->initial_stock_warehouse_id;
        $qty = $this->initial_stock_qty;
        
        $stockBefore = \Illuminate\Support\Facades\DB::table('item_warehouse')
            ->where('item_id', $this->item->id)
            ->where('warehouse_id', $warehouseId)
            ->value('stock') ?? 0;
            
        $stockAfter = $stockBefore + $qty;

        // 1. Catat Mutasi Stok (Stock Movement)
        $refNumber = 'SA-' . date('Ymd') . '-' . rand(1000, 9999);
        StockMovement::create([
            'item_id' => $this->item->id,
            'warehouse_id' => $warehouseId,
            'type' => 'in',
            'quantity' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_number' => $refNumber,
            'date' => now(),
            'notes' => $this->initial_stock_notes ?: 'Saldo Awal',
            'user_id' => auth()->id(),
        ]);

        // 2. Jika Butuh Label SN, Generate
        $generatedLabelIds = [];
        if ($this->item->requires_label) {
            for ($i = 0; $i < $qty; $i++) {
                do {
                    $code = strtoupper(\Illuminate\Support\Str::random(6));
                } while (\Modules\Inventory\Models\ItemLabel::where('label_code', $code)->exists());

                $label = \Modules\Inventory\Models\ItemLabel::create([
                    'item_id' => $this->item->id,
                    'label_code' => $code,
                    'status' => 'in_stock',
                    'warehouse_id' => $warehouseId,
                    'notes' => 'Saldo Awal: ' . $refNumber,
                ]);
                $generatedLabelIds[] = $label->id;
            }
        } else {
             // 3. Update Pivot Table (For non-label or just to keep it in sync)
             \Illuminate\Support\Facades\DB::table('item_warehouse')->updateOrInsert(
                 ['item_id' => $this->item->id, 'warehouse_id' => $warehouseId],
                 ['stock' => $stockAfter]
             );
        }

        \Illuminate\Support\Facades\DB::commit();

        Flux::modal('initial-stock-modal')->close();
        
        $msg = "Saldo Awal berhasil ditambahkan.";
        if (count($generatedLabelIds) > 0) {
            $msg .= " " . count($generatedLabelIds) . " Label SN berhasil di-generate.";
            $this->dispatch('open-print-labels', labelIds: $generatedLabelIds);
        }
        
        Flux::toast(heading: 'Berhasil', text: $msg, variant: 'success');
        
        // Refresh Dasbor
        $this->refreshItem();
        
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        \Illuminate\Support\Facades\Log::error('Saldo Awal Error: ' . $e->getMessage());
        Flux::toast(heading: 'Gagal', text: 'Terjadi kesalahan sistem: ' . $e->getMessage(), variant: 'danger');
    }
};

?>

<div
    x-data="{
        activeTab: 'info',
        init() {
            if (window.Echo) {
                window.Echo.channel('inventory')
                    .listen('InventoryUpdated', () => {
                        $wire.refreshItem();
                    });
            }
            $wire.on('item-detail-modal-opened', (data) => {
                this.activeTab = data && data.length > 0 && data[0].tab ? data[0].tab : 'info';
            });
        }
    }"
    x-on:do-open-item-detail.window="Flux.modal('item-detail-modal').show()"
>
    <flux:modal name="item-detail-modal" class="w-full" style="width: 1200px; max-width: 90vw;" scroll="body">
        @if($item)
            <div class="flex flex-col gap-6 min-h-[400px]">
                
                {{-- Header Modal --}}
                <div class="mb-2 flex items-center gap-3">
                    <flux:heading size="lg">Detail Barang</flux:heading>
                    
                    @if(!$item->is_approved)
                        @can('inventory.item.update')
                            <flux:button wire:click="approveItem" variant="primary" size="sm" icon="check" class="px-2 md:px-3">
                                <span class="hidden md:inline">Setujui Barang</span>
                            </flux:button>
                        @endcan
                    @endif
                    
                    @can('inventory.item.update')
                        <flux:button 
                            x-on:click="Flux.modal('item-detail-modal').close(); $wire.dispatch('open-item-modal', { id: {{ $item ? $item->id : 'null' }} })" 
                            variant="outline" size="sm" icon="pencil-square" class="px-2 md:px-3">
                            <span class="hidden md:inline">Edit Data</span>
                        </flux:button>
                    @endcan
                </div>

                {{-- Konten Utama dengan Tabs --}}
                <div>
                    <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700 mb-6 relative z-10">
                        <button type="button" wire:click="switchTab('info')" wire:loading.attr="disabled" class="{{ $tab === 'info' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }} pb-3 text-sm transition-colors flex items-center gap-1.5 disabled:opacity-75 disabled:cursor-not-allowed">
                            Dasbor
                            <flux:icon.arrow-path wire:loading wire:target="switchTab('info')" class="w-3.5 h-3.5 animate-spin text-zinc-400" />
                        </button>
                        @if($item->requires_label)
                        <button type="button" wire:click="switchTab('labels')" wire:loading.attr="disabled" class="{{ $tab === 'labels' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }} pb-3 text-sm transition-colors flex items-center gap-1.5 disabled:opacity-75 disabled:cursor-not-allowed">
                            Serial
                            <flux:icon.arrow-path wire:loading wire:target="switchTab('labels')" class="w-3.5 h-3.5 animate-spin text-zinc-400" />
                        </button>
                        @endif
                        <button type="button" wire:click="switchTab('history')" wire:loading.attr="disabled" class="{{ $tab === 'history' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }} pb-3 text-sm transition-colors flex items-center gap-1.5 disabled:opacity-75 disabled:cursor-not-allowed">
                            Riwayat
                            <flux:icon.arrow-path wire:loading wire:target="switchTab('history')" class="w-3.5 h-3.5 animate-spin text-zinc-400" />
                        </button>
                        @if($custom_variants_count > 0)
                        <button type="button" wire:click="switchTab('variants')" wire:loading.attr="disabled" class="{{ $tab === 'variants' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }} pb-3 text-sm transition-colors flex items-center gap-1.5 disabled:opacity-75 disabled:cursor-not-allowed">
                            Varian ({{ $custom_variants_count }})
                            <flux:icon.arrow-path wire:loading wire:target="switchTab('variants')" class="w-3.5 h-3.5 animate-spin text-zinc-400" />
                        </button>
                        @endif
                    </div>

                    @if($tab === 'info')
                    <div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            
                            {{-- Kolom Kiri: Gambar & Stok Info --}}
                            <div class="md:col-span-1 flex flex-col gap-6">
                                {{-- Foto & Status --}}
                                <div>
                                    <div class="relative w-full aspect-square bg-zinc-100 dark:bg-zinc-800 rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700">
                                        @if (!$item->is_active)
                                        <div class="absolute z-2 top-0 w-full h-full bg-[#000000ba] flex items-center justify-center">
                                            <span class="text-bold text-white">NON ACTIVE</span>
                                        </div>
                                        @endif
                                        @if($item->image)
                                            <img src="{{ asset('storage/' . $item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex flex-col items-center justify-center text-zinc-400">
                                                <flux:icon.photo class="w-12 h-12 mb-2" />
                                                <span class="text-xs">Tak Ada Foto</span>
                                            </div>
                                        @endif

                                        {{-- Mini Variants Carousel & Tooltip --}}
                                        @if ($item->custom_variants_count > 0)
                                            <div x-data="{ showTooltip: false }" 
                                                 class="absolute bottom-2 left-2 z-50 right-2 flex items-center justify-between cursor-pointer pointer-events-auto"
                                                 @mouseenter="showTooltip = true"
                                                 @mouseleave="showTooltip = false"
                                                 @click.stop="showTooltip = !showTooltip">
                                                 
                                                <div class="flex -space-x-1.5 overflow-hidden p-1 transition-transform hover:scale-105">
                                                    @foreach($item->customVariants as $variant)
                                                        @if(!empty($variant->custom_attachments))
                                                            <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white object-cover bg-white shadow-md" 
                                                                 src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" 
                                                                 alt="Varian">
                                                        @endif
                                                    @endforeach
                                                </div>
                                                @if($item->custom_variants_count > count($item->customVariants))
                                                    <span class="bg-black/50 text-white text-xs font-bold px-2 py-1 rounded backdrop-blur-sm shadow-sm transition-transform hover:scale-105">
                                                        +{{ $item->custom_variants_count - count($item->customVariants) }}
                                                    </span>
                                                @endif
                                                
                                                {{-- Popover Overlay --}}
                                                <div x-show="showTooltip" 
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                     x-transition:leave="transition ease-in duration-150"
                                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                     x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                                     class="absolute bottom-12 left-0 w-72 bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 overflow-hidden pointer-events-none"
                                                     style="display: none; transform-origin: bottom left;">
                                                     
                                                    <div class="bg-indigo-600 px-4 py-2.5 text-white flex justify-between items-center">
                                                        <div class="font-bold text-sm flex items-center gap-1.5">
                                                            <flux:icon.swatch class="w-4 h-4" /> 
                                                            Riwayat Varian
                                                        </div>
                                                        <div class="text-xs bg-indigo-800/50 px-2 py-0.5 rounded">{{ $item->custom_variants_count }} Total</div>
                                                    </div>
                                                    
                                                    <div class="p-2 flex flex-col gap-2 max-h-64 overflow-y-auto custom-scrollbar">
                                                        @foreach($item->customVariants as $variant)
                                                            <div class="flex gap-3 p-2 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-700/50 shadow-sm">
                                                                @if(!empty($variant->custom_attachments))
                                                                    <img src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" 
                                                                         class="w-12 h-12 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shadow-sm shrink-0 bg-white">
                                                                @else
                                                                    <div class="w-12 h-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0 border border-zinc-200 dark:border-zinc-700">
                                                                        <flux:icon.photo class="w-5 h-5 text-zinc-400" />
                                                                    </div>
                                                                @endif
                                                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                                                    <div class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate">
                                                                        {{ $variant->salesOrder?->customer?->name ?? 'Pelanggan Umum' }}
                                                                    </div>
                                                                    <div class="text-[10px] text-zinc-500 mb-1">
                                                                        {{ $variant->created_at?->format('d M Y') ?? 'Tidak diketahui' }}
                                                                    </div>
                                                                    @if(!empty($variant->custom_attributes))
                                                                        <div class="text-[10px] text-indigo-600 dark:text-indigo-400 truncate font-medium">
                                                                            {{ collect($variant->custom_attributes)->map(fn($v, $k) => $k . ': ' . (is_array($v) ? implode(', ', $v) : $v))->implode(' | ') }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    {{-- Info Identitas Barang --}}
                                    <div class="mt-4 flex flex-col gap-1 text-center items-center">
                                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-100 leading-tight">{{ $item->name }}</h2>
                                        <div class="flex items-center justify-center gap-2 mb-1 flex-wrap">
                                            <span class="font-mono text-[10px] bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                                                {{ $item->code }}
                                            </span>
                                            <span class="text-[10px] font-medium text-zinc-500 flex items-center gap-1">
                                                <flux:icon.tag class="w-3 h-3" />
                                                {{ $item->category?->name ?? 'Tanpa Kategori' }} 
                                                @if($item->subCategory) &rsaquo; {{ $item->subCategory->name }} @endif
                                            </span>
                                        </div>
                                        @if($item->description)
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ $item->description }}</p>
                                        @endif
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full {{ $item->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></div>
                                                <span class="text-sm font-semibold {{ $item->is_active ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500' }}">
                                                    {{ $item->is_active ? 'Status Aktif' : 'Non-aktif' }}
                                                </span>
                                            </div>
                                            @can('inventory.item.update')
                                                <flux:switch wire:key="switch-{{ $item->is_active ? 'on' : 'off' }}" wire:click="toggleActive" :checked="$item->is_active" wire:loading.attr="disabled" wire:target="toggleActive" wire:loading.class="opacity-50 cursor-wait" />
                                            @endcan
                                        </div>
                                        @if($item->requires_label)
                                            <flux:badge color="blue" icon="qr-code" class="w-full justify-center">Berlabel SN</flux:badge>
                                        @endif
                                    </div>
                                </div>

                                {{-- Stok per Gudang --}}
                                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex justify-between items-center mb-3">
                                        <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Ketersediaan Stok</h3>
                                        @can('inventory.item.update')
                                            <flux:button x-on:click="Flux.modal('initial-stock-modal').show()" variant="primary" size="xs" icon="plus" class="text-[10px] h-6 px-1.5 md:px-2">
                                                <span class="hidden md:inline">Input Saldo Awal</span>
                                            </flux:button>
                                        @endcan
                                    </div>
                                    <div class="space-y-2">
                                        @php $totalStock = 0; @endphp
                                        @forelse($item->warehouses as $warehouse)
                                            @php 
                                                $actualStock = $item->requires_label 
                                                    ? \Modules\Inventory\Models\ItemLabel::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->where('status', 'in_stock')->count()
                                                    : $warehouse->pivot->stock;
                                                $totalStock += $actualStock; 
                                            @endphp
                                            <div class="flex justify-between items-center text-sm">
                                                <span class="text-zinc-600 dark:text-zinc-400 flex items-center gap-2">
                                                    <flux:icon.building-storefront class="w-4 h-4" />
                                                    <span class="truncate max-w-[100px]" title="{{ $warehouse->name }}">{{ $warehouse->name }}</span>
                                                </span>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $actualStock }}</span>
                                            </div>
                                        @empty
                                            <div class="text-sm text-zinc-500 italic">Belum ada stok.</div>
                                        @endforelse
                                        
                                        @if(count($item->warehouses) > 0)
                                        <div class="flex justify-between items-center text-sm pt-2 border-t border-zinc-200 dark:border-zinc-700 mt-2">
                                            <span class="font-semibold text-zinc-900 dark:text-zinc-100">Total</span>
                                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $totalStock }} {{ $item->unit?->name ?? 'Unit' }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Tipe & Batas Stok --}}
                                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 border border-zinc-200 dark:border-zinc-700 space-y-3 text-sm">
                                    <div>
                                        <span class="text-zinc-500 block text-[10px] uppercase font-bold tracking-wider mb-1">Tipe Barang</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->type?->name ?? '-' }}</span>
                                    </div>
                                    <flux:separator variant="subtle" />
                                    <div>
                                        <span class="text-zinc-500 block text-[10px] uppercase font-bold tracking-wider mb-1">Batas Stok</span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">Min: {{ $item->min_stock }} / Max: {{ $item->max_stock }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Kolom Kanan: Informasi & Dasbor --}}
                            <div class="md:col-span-2 flex flex-col gap-6">
                                
                                {{-- Harga --}}
                                <div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800">
                                            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Harga Beli</div>
                                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Rp {{ number_format($item->purchase_price, 0, ',', '.') }}</div>
                                        </div>
                                        <div class="bg-emerald-50 dark:bg-emerald-500/10 p-3 rounded-lg border border-emerald-100 dark:border-emerald-500/20">
                                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 uppercase tracking-wider font-bold mb-1">Harga Jual</div>
                                            <div class="text-base font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($item->selling_price, 0, ',', '.') }}</div>
                                        </div>
                                    </div>
                                </div>

                                <flux:separator variant="subtle" />

                                {{-- Dasbor Analitik (Realtime Pipeline) --}}
                                <div>
                                    <h3 class="text-sm font-bold mb-3 text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                        <flux:icon.arrow-path-rounded-square class="w-4 h-4 text-blue-500"/>
                                        Status Pipeline (Realtime)
                                    </h3>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col justify-between">
                                            <div class="text-[9px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Dalam Pembelian</div>
                                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $purchase_order + $purchase_queue }} <span class="text-[10px] font-normal text-zinc-400 ml-0.5">{{ $item->unit?->name }}</span></div>
                                        </div>
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col justify-between">
                                            <div class="text-[9px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Dalam Produksi</div>
                                            <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $production }} <span class="text-[10px] font-normal text-zinc-400 ml-0.5">{{ $item->unit?->name }}</span></div>
                                        </div>
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm flex flex-col justify-between">
                                            <div class="text-[9px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Pesanan (Booking)</div>
                                            <div class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ $sales_committed }} <span class="text-[10px] font-normal text-zinc-400 ml-0.5">{{ $item->unit?->name }}</span></div>
                                        </div>
                                        <div class="bg-emerald-50 dark:bg-emerald-500/10 p-3 rounded-lg border border-emerald-200 dark:border-emerald-500/30 shadow-sm flex flex-col justify-between">
                                            <div class="text-[9px] text-emerald-700 dark:text-emerald-400 uppercase tracking-wider font-bold mb-1" title="Available to Promise (Siap Jual)">Siap Jual (ATP)</div>
                                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $atp }} <span class="text-[10px] font-normal text-emerald-500/70 ml-0.5">{{ $item->unit?->name }}</span></div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-[10px] text-zinc-500 flex items-center gap-1.5 mb-6">
                                        <flux:icon.information-circle class="w-3.5 h-3.5" />
                                        <span><strong>ATP (Available to Promise)</strong> = Stok Fisik + Barang Masuk (Beli/Produksi) - Pesanan Keluar.</span>
                                    </div>
                                </div>

                                {{-- Tren Harga (Real Data) --}}
                                @php
                                    // Fetch real price history
                                    $histories = $item->priceHistories()->orderBy('created_at', 'asc')->get();
                                    $dataPoints = [];
                                    foreach ($histories as $h) {
                                        $dataPoints[] = [
                                            'purchase' => (float)$h->purchase_price,
                                            'selling' => (float)$h->selling_price,
                                            'date' => $h->created_at->format('d M y')
                                        ];
                                    }
                                    // Append current state
                                    $dataPoints[] = [
                                        'purchase' => (float)$item->purchase_price,
                                        'selling' => (float)$item->selling_price,
                                        'date' => 'Saat Ini'
                                    ];
                                    
                                    // Calculate SVG Coordinates
                                    $count = count($dataPoints);
                                    
                                    // Find min/max for scaling
                                    $allPrices = [];
                                    foreach ($dataPoints as $dp) {
                                        $allPrices[] = $dp['purchase'];
                                        $allPrices[] = $dp['selling'];
                                    }
                                    $maxPrice = !empty($allPrices) ? max($allPrices) : 1;
                                    $minPrice = !empty($allPrices) ? min($allPrices) : 0;
                                    $padding = ($maxPrice - $minPrice) * 0.1; // 10% padding
                                    $maxPrice += $padding;
                                    $minPrice -= $padding;
                                    if ($minPrice < 0) $minPrice = 0;
                                    if ($maxPrice == $minPrice) $maxPrice = $minPrice + 1; // avoid div by 0
                                    
                                    $width = 100;
                                    $height = 40;
                                    
                                    $purchasePath = '';
                                    $sellingPath = '';
                                    
                                    if ($count > 1) {
                                        foreach ($dataPoints as $i => $dp) {
                                            $x = ($i / ($count - 1)) * $width;
                                            // Y is inverted (0 is top, 40 is bottom)
                                            $yP = $height - ((($dp['purchase'] - $minPrice) / ($maxPrice - $minPrice)) * $height);
                                            $yS = $height - ((($dp['selling'] - $minPrice) / ($maxPrice - $minPrice)) * $height);
                                            
                                            $cmd = $i === 0 ? 'M' : 'L';
                                            $purchasePath .= "$cmd$x,$yP ";
                                            $sellingPath .= "$cmd$x,$yS ";
                                        }
                                    } else {
                                        // Flat line if only 1 data point
                                        $yP = $height - ((($dataPoints[0]['purchase'] - $minPrice) / ($maxPrice - $minPrice)) * $height);
                                        $yS = $height - ((($dataPoints[0]['selling'] - $minPrice) / ($maxPrice - $minPrice)) * $height);
                                        $purchasePath = "M0,$yP L100,$yP";
                                        $sellingPath = "M0,$yS L100,$yS";
                                    }
                                    
                                    // Polygon for selling gradient (fill under the line)
                                    $sellingFill = str_starts_with($sellingPath, 'M') ? $sellingPath . " L100,$height L0,$height Z" : "M0,$height L100,$height Z";
                                @endphp

                                <div class="mt-2">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                            <flux:icon.currency-dollar class="w-4 h-4 text-emerald-500"/>
                                            Tren Harga
                                        </h3>
                                        <div class="flex items-center gap-3 text-[10px] font-medium uppercase tracking-wider">
                                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-500"></span> Beli</span>
                                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Jual</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 shadow-sm mb-4 relative group">
                                        <!-- Real SVG Line Chart -->
                                        <div class="h-28 w-full relative">
                                            <svg class="w-full h-full overflow-visible" viewBox="0 0 100 40" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="real-emerald-gradient" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.3"></stop>
                                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0"></stop>
                                                    </linearGradient>
                                                </defs>
                                                
                                                <!-- Grid Line (Midpoint) -->
                                                <line x1="0" y1="20" x2="100" y2="20" stroke="currentColor" stroke-dasharray="2,2" class="text-zinc-200 dark:text-zinc-700" stroke-width="0.3"/>
                                                
                                                <!-- Harga Beli (Orange) -->
                                                <path d="{{ $purchasePath }}" fill="none" stroke="currentColor" class="text-orange-500 transition-all duration-1000" stroke-width="1" stroke-linejoin="round" stroke-linecap="round"/>
                                                
                                                <!-- Harga Jual (Emerald) -->
                                                <path d="{{ $sellingPath }}" fill="none" stroke="currentColor" class="text-emerald-500 transition-all duration-1000" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                                                
                                                @if($count > 1)
                                                <path d="{{ $sellingFill }}" fill="url(#real-emerald-gradient)" class="opacity-100 transition-all duration-1000"/>
                                                @endif
                                            </svg>
                                            <!-- Data Points Hover Tooltips -->
                                            <div class="absolute inset-0 flex justify-between items-stretch">
                                                @foreach($dataPoints as $i => $dp)
                                                    <div class="flex-1 relative group/point cursor-crosshair">
                                                        <!-- Invisible hover area -->
                                                        <div class="absolute inset-0 z-10 hover:bg-zinc-100/30 dark:hover:bg-zinc-800/30 border-r border-zinc-100/50 dark:border-zinc-800/50 {{ $loop->last ? 'border-r-0' : '' }}"></div>
                                                        <!-- Tooltip -->
                                                        <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-zinc-800 text-white text-[9px] px-2 py-1 rounded shadow-lg opacity-0 group-hover/point:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-20">
                                                            <div class="font-bold text-zinc-300 border-b border-zinc-600 pb-0.5 mb-0.5">{{ $dp['date'] }}</div>
                                                            <div class="text-emerald-400">Jual: Rp {{ number_format($dp['selling'], 0, ',', '.') }}</div>
                                                            <div class="text-orange-400">Beli: Rp {{ number_format($dp['purchase'], 0, ',', '.') }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="flex justify-between mt-2 text-[9px] font-medium text-zinc-400">
                                            <span>{{ $dataPoints[0]['date'] }}</span>
                                            <span>{{ end($dataPoints)['date'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Promo & Diskon (Mockup) --}}
                                <div class="mt-2 mb-4">
                                    <h3 class="text-sm font-bold mb-3 text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                        <flux:icon.receipt-percent class="w-4 h-4 text-rose-500"/>
                                        Riwayat Promo & Diskon (Mockup)
                                    </h3>
                                    
                                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-0 shadow-sm overflow-hidden">
                                        <table class="w-full text-sm text-left">
                                            <thead class="text-[10px] text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 uppercase tracking-wider">
                                                <tr>
                                                    <th class="px-4 py-2 font-semibold">Periode</th>
                                                    <th class="px-4 py-2 font-semibold">Diskon</th>
                                                    <th class="px-4 py-2 font-semibold">Event</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 text-xs">
                                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                                    <td class="px-4 py-2 text-zinc-900 dark:text-zinc-100">01 - 07 Jun 2026</td>
                                                    <td class="px-4 py-2 text-rose-600 dark:text-rose-400 font-medium">-15%</td>
                                                    <td class="px-4 py-2 text-zinc-500">Flash Sale Mid-Year</td>
                                                </tr>
                                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                                    <td class="px-4 py-2 text-zinc-900 dark:text-zinc-100">14 - 15 Feb 2026</td>
                                                    <td class="px-4 py-2 text-rose-600 dark:text-rose-400 font-medium">Pot. Rp 50.000</td>
                                                    <td class="px-4 py-2 text-zinc-500">Valentine Promo</td>
                                                </tr>
                                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                                    <td class="px-4 py-2 text-zinc-900 dark:text-zinc-100">25 Des 2025</td>
                                                    <td class="px-4 py-2 text-rose-600 dark:text-rose-400 font-medium">Beli 2 Gratis 1</td>
                                                    <td class="px-4 py-2 text-zinc-500">Year End Clearance</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($tab === 'labels')
                    <div>
                        <livewire:item-input.item-label-list :item-id="$item->id" :wire:key="'label-list-' . $item->id" />
                    </div>
                    @endif
                    
                    <!-- Tab Riwayat Mutasi -->
                    @if($tab === 'history')
                    <div>
                        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex justify-between items-center">
                                <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Riwayat Mutasi Barang (50 Terakhir)</h3>
                                <span class="text-xs text-zinc-500">Urut berdasarkan terbaru</span>
                            </div>
                            <x-table.wrapper>
                                <flux:table class="table-mobile-cards">
                                    <flux:table.columns>
                                        <flux:table.column>Tanggal & Referensi</flux:table.column>
                                        <flux:table.column>Mutasi</flux:table.column>
                                        <flux:table.column>Gudang & Sisa Stok</flux:table.column>
                                        <flux:table.column>Petugas & Catatan</flux:table.column>
                                    </flux:table.columns>
                                    <flux:table.rows>
                                        @forelse($movements as $m)
                                            <flux:table.row class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                                <flux:table.cell>
                                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $m->created_at->format('d M Y H:i') }}</div>
                                                    <div class="text-[11px] text-zinc-500 font-mono mt-0.5">{{ $m->reference_number ?: '-' }}</div>
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    <div class="flex items-center gap-2 mt-1 sm:mt-0">
                                                        @if(str_contains($m->type, 'in'))
                                                            <flux:badge color="emerald" size="sm" class="uppercase text-[10px]">Masuk</flux:badge>
                                                        @elseif(str_contains($m->type, 'out'))
                                                            <flux:badge color="rose" size="sm" class="uppercase text-[10px]">Keluar</flux:badge>
                                                        @else
                                                            <flux:badge color="zinc" size="sm" class="uppercase text-[10px]">{{ str_replace('_', ' ', $m->type) }}</flux:badge>
                                                        @endif
                                                        <span class="font-bold {{ str_contains($m->type, 'in') ? 'text-emerald-500' : 'text-rose-500' }}">
                                                            {{ str_contains($m->type, 'in') ? '+' : '-' }}{{ abs($m->quantity) }}
                                                        </span>
                                                    </div>
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    <div class="flex flex-col mt-1 sm:mt-0">
                                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $m->warehouse?->name ?? '-' }}</span>
                                                        <span class="text-[11px] text-zinc-500">Sisa: <span class="font-mono text-zinc-600 dark:text-zinc-400 font-bold">{{ $m->stock_after }}</span></span>
                                                    </div>
                                                </flux:table.cell>
                                                <flux:table.cell>
                                                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mt-1 sm:mt-0">{{ $m->user?->name ?? 'Sistem' }}</div>
                                                    @if($m->notes)
                                                        <div class="text-[11px] text-zinc-500 mt-0.5 truncate max-w-xs" title="{{ $m->notes }}">{{ $m->notes }}</div>
                                                    @endif
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @empty
                                            <flux:table.row>
                                                <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500 italic">
                                                    Belum ada riwayat mutasi stok.
                                                </flux:table.cell>
                                            </flux:table.row>
                                        @endforelse
                                    </flux:table.rows>
                                </flux:table>
                            </x-table.wrapper>
                        </div>
                    </div>
                    @endif
                    
                    @if($tab === 'variants' && $custom_variants_count > 0)
                    <div>
                        <div class="flex justify-between items-center mb-4 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-zinc-100 dark:border-zinc-700/50">
                            <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200">Riwayat Varian Kustom</h3>
                            <flux:switch wire:model.live="groupVariants" label="Kelompokkan Duplikat" />
                        </div>
                        
                        @php
                            $displayVariants = $this->customVariantsList;
                            if ($groupVariants) {
                                $displayVariants = collect($this->customVariantsList)->groupBy(function($v) {
                                    return md5(json_encode($v->custom_attributes) . json_encode($v->custom_attachments));
                                })->map(function($group) {
                                    $first = $group->first();
                                    $first->duplicate_count = $group->count();
                                    return $first;
                                });
                            }
                        @endphp
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            @forelse($displayVariants as $variant)
                                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-all">
                                    <div class="aspect-square bg-zinc-100 dark:bg-zinc-800 relative">
                                        @if(!empty($variant->custom_attachments))
                                            <img src="{{ asset('storage/' . $variant->custom_attachments[0]) }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <flux:icon.photo class="w-8 h-8 text-zinc-300" />
                                            </div>
                                        @endif
                                        <div class="absolute top-2 right-2 bg-black/60 backdrop-blur-sm text-white text-[10px] font-mono px-2 py-0.5 rounded">
                                            {{ $variant->created_at ? $variant->created_at->format('d M Y') : '' }}
                                        </div>
                                        @if(isset($variant->duplicate_count) && $variant->duplicate_count > 1)
                                            <div class="absolute top-2 left-2 bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded shadow-sm">
                                                x{{ $variant->duplicate_count }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-3">
                                        <div class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate mb-1">
                                            {{ $variant->salesOrder?->customer?->name ?? 'Pelanggan Umum' }}
                                        </div>
                                        @if(!empty($variant->custom_attributes))
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 line-clamp-3">
                                                {{ collect($variant->custom_attributes)->map(fn($v, $k) => $k . ': ' . (is_array($v) ? implode(', ', $v) : $v))->implode(' | ') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full py-12 text-center text-zinc-500 bg-zinc-50 rounded-xl border border-dashed border-zinc-200">
                                    <flux:icon.cube class="w-8 h-8 mx-auto text-zinc-300 mb-3" />
                                    <p class="font-medium text-zinc-600">Tidak ada data riwayat varian.</p>
                                </div>
                            @endforelse
                        </div>
                        @if($custom_variants_count > count($this->customVariantsList) && count($this->customVariantsList) > 0)
                            <div class="mt-6 text-center text-sm text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 py-3 rounded-xl border border-zinc-100 dark:border-zinc-700/50">
                                Menampilkan {{ count($this->customVariantsList) }} varian terbaru dari total {{ $custom_variants_count }} varian.
                            </div>
                        @endif
                    </div>
                    @endif

                </div>

                {{-- Footer Aksi --}}
                <div class="flex justify-between items-center pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div>
                        @can('inventory.item.delete')
                            <flux:button wire:click="deleteItem" wire:confirm="Yakin ingin menghapus barang ini secara permanen?" variant="danger" icon="trash">
                                <span class="hidden md:inline">Hapus Data</span>
                            </flux:button>
                        @endcan
                    </div>
                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" icon="x-mark">
                                <span class="hidden md:inline">Tutup</span>
                            </flux:button>
                        </flux:modal.close>
                    </div>
                </div>

            </div>
        @else
            <div class="p-8 text-center text-zinc-500 flex flex-col items-center">
                <flux:icon.arrow-path class="w-8 h-8 animate-spin mb-4" />
                Memuat detail...
            </div>
        @endif
    </flux:modal>

    {{-- Modal Input Saldo Awal --}}
    <flux:modal name="initial-stock-modal" class="md:w-96">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">Input Saldo Awal</flux:heading>
            <flux:subheading>Masukkan jumlah stok awal untuk barang ini. Stok akan langsung ditambahkan ke gudang yang dipilih.</flux:subheading>
            
            <form wire:submit="saveInitialStock" class="flex flex-col gap-4 mt-2">
                <flux:select wire:model="initial_stock_warehouse_id" label="Gudang">
                    <flux:select.option value="" disabled selected>Pilih Gudang...</flux:select.option>
                    @if($item)
                        @foreach(\Modules\Inventory\Models\Warehouse::all() as $wh)
                            <flux:select.option value="{{ $wh->id }}">{{ $wh->name }}</flux:select.option>
                        @endforeach
                    @endif
                </flux:select>
                
                <flux:input wire:model="initial_stock_qty" type="number" min="1" label="Jumlah Fisik" required />
                
                <flux:input wire:model="initial_stock_notes" label="Catatan (Opsional)" />
                
                @if($item && $item->requires_label)
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-100 dark:border-blue-900/50 mt-2">
                        <div class="flex gap-2">
                            <flux:icon.qr-code class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                            <div class="text-xs text-blue-700 dark:text-blue-300">
                                Barang ini wajib berlabel. Sistem otomatis men-generate dan mencetak <strong>label SN baru</strong> setelah disimpan.
                            </div>
                        </div>
                    </div>
                @endif
                
                <div class="flex justify-end gap-2 mt-4">
                    <flux:modal.close>
                        <flux:button variant="ghost"> Batal </flux:button>
                    </flux:modal.close>
                    <flux:button icon="check" type="submit" variant="primary"> Simpan Sald </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
