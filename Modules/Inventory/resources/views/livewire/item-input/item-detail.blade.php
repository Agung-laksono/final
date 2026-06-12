<?php

use function Livewire\Volt\{state, on};
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
]);

$openModal = function ($id) {
    $this->item = Item::with(['category', 'subCategory', 'unit', 'type', 'warehouses'])->findOrFail($id);
    
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
    
    $this->initial_stock_warehouse_id = '';
    $this->initial_stock_qty = 1;
    $this->initial_stock_notes = 'Saldo Awal';
    $this->tab = 'info'; // This is fine to keep, doesn't hurt
    
    $this->dispatch('item-detail-modal-opened');
    Flux::modal('item-detail-modal')->show();
};

on(['open-item-detail' => function ($id) {
    $this->openModal($id);
}]);

$editItem = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.item.update');
    Flux::modal('item-detail-modal')->close();
    $this->dispatch('open-item-modal', id: $this->item->id);
};

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

$refreshItem = function () {
    if ($this->item) {
        $this->item->refresh();
        $this->openModal($this->item->id);
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

<div x-data="{
    tab: 'info',
    init() {
        if (window.Echo) {
            window.Echo.channel('inventory')
                .listen('InventoryUpdated', () => {
                    $wire.refreshItem();
                });
        }
        $wire.on('item-detail-modal-opened', () => {
            this.tab = 'info';
        });
    }
}">
    <flux:modal name="item-detail-modal" class="w-full" style="width: 1200px; max-width: 90vw;" scroll="body">
        @if($item)
            <div class="flex flex-col gap-6 min-h-[400px]">
                
                {{-- Header Modal --}}
                <div class="mb-2 flex items-center gap-3">
                    <flux:heading size="lg">Detail Barang</flux:heading>
                    @can('inventory.item.update')
                        <flux:button wire:click="editItem" variant="outline" size="sm" icon="pencil-square" class="px-2 md:px-3">
                            <span class="hidden md:inline">Edit Data</span>
                        </flux:button>
                    @endcan
                </div>

                {{-- Konten Utama dengan Tabs --}}
                <div>
                    <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700 mb-6">
                        <button type="button" @click="tab = 'info'" :class="tab === 'info' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'" class="pb-3 text-sm transition-colors">
                            Dasbor
                        </button>
                        @if($item->requires_label)
                        <button type="button" @click="tab = 'labels'" :class="tab === 'labels' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'" class="pb-3 text-sm transition-colors">
                            Serial
                        </button>
                        @endif
                        <button type="button" @click="tab = 'history'" :class="tab === 'history' ? 'border-b-2 border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300'" class="pb-3 text-sm transition-colors">
                            Riwayat
                        </button>
                    </div>

                    <div x-show="tab === 'info'" x-cloak>
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
                                        <div>
                                            agung
                                        </div>
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

                                {{-- Dasbor Analitik (Mockup) --}}
                                <div>
                                    <h3 class="text-sm font-bold mb-3 text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                        <flux:icon.chart-bar class="w-4 h-4 text-blue-500"/>
                                        Statistik Pergerakan (Mockup)
                                    </h3>
                                    
                                    <div class="grid grid-cols-3 gap-3 mb-4">
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Masuk (Bln)</div>
                                            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">+{{ $in_this_month }}</div>
                                        </div>
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Keluar (Bln)</div>
                                            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">-{{ $out_this_month }}</div>
                                        </div>
                                        <div class="bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                            <div class="text-[10px] text-zinc-500 uppercase tracking-wider font-bold mb-1">Rata2 Keluar</div>
                                            <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $avg_out_per_day }}/hr</div>
                                        </div>
                                    </div>

                                    <!-- Mockup Bar Chart -->
                                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 shadow-sm">
                                        <div class="h-32 w-full flex items-end gap-1 px-1 relative border-b border-l border-zinc-200 dark:border-zinc-700 pb-1">
                                            <div class="w-full bg-blue-500/30 hover:bg-blue-500/50 rounded-t-sm h-[30%] transition-colors"></div>
                                            <div class="w-full bg-blue-500/40 hover:bg-blue-500/60 rounded-t-sm h-[45%] transition-colors"></div>
                                            <div class="w-full bg-blue-500/50 hover:bg-blue-500/70 rounded-t-sm h-[20%] transition-colors"></div>
                                            <div class="w-full bg-blue-500/60 hover:bg-blue-500/80 rounded-t-sm h-[60%] transition-colors"></div>
                                            <div class="w-full bg-blue-500/70 hover:bg-blue-500/90 rounded-t-sm h-[80%] transition-colors"></div>
                                            <div class="w-full bg-blue-500/80 hover:bg-blue-500 rounded-t-sm h-[55%] transition-colors"></div>
                                            <div class="w-full bg-blue-600 hover:bg-blue-700 rounded-t-sm h-[90%] transition-colors"></div>
                                        </div>
                                        <div class="flex justify-between mt-2 text-[9px] font-medium text-zinc-400">
                                            <span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Tren Harga (Mockup) --}}
                                <div class="mt-2">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                                            <flux:icon.currency-dollar class="w-4 h-4 text-emerald-500"/>
                                            Tren Harga (Mockup)
                                        </h3>
                                        <div class="flex items-center gap-3 text-[10px] font-medium uppercase tracking-wider">
                                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-orange-500"></span> Beli</span>
                                            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Jual</span>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 shadow-sm mb-4">
                                        <!-- Mockup SVG Line Chart (Dual Line) -->
                                        <div class="h-28 w-full relative">
                                            <svg class="w-full h-full" viewBox="0 0 100 40" preserveAspectRatio="none">
                                                <!-- Harga Beli (Orange) -->
                                                <path d="M0,35 L15,30 L30,32 L45,20 L60,25 L75,10 L90,15 L100,5" fill="none" stroke="currentColor" class="text-orange-500" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                                                
                                                <!-- Harga Jual (Emerald) - Higher than purchase -->
                                                <path d="M0,25 L15,20 L30,22 L45,10 L60,15 L75,2 L90,5 L100,0" fill="none" stroke="currentColor" class="text-emerald-500" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                                                <path d="M0,25 L15,20 L30,22 L45,10 L60,15 L75,2 L90,5 L100,0 L100,40 L0,40 Z" fill="url(#emerald-gradient)" class="opacity-10"/>
                                                
                                                <defs>
                                                    <linearGradient id="emerald-gradient" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stop-color="#10b981" />
                                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                                                    </linearGradient>
                                                </defs>
                                            </svg>
                                        </div>
                                        <div class="flex justify-between mt-2 text-[9px] font-medium text-zinc-400">
                                            <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mei</span><span>Jun</span>
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

                    <div x-show="tab === 'labels'" x-cloak>
                        <livewire:item-input.item-label-list :item-id="$item->id" :wire:key="'label-list-' . $item->id" />
                    </div>
                    <!-- Tab Riwayat Mutasi -->
                    <div x-show="tab === 'history'" x-cloak>
                        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 flex justify-between items-center">
                                <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Riwayat Mutasi Barang (50 Terakhir)</h3>
                                <span class="text-xs text-zinc-500">Urut berdasarkan terbaru</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 uppercase border-b border-zinc-200 dark:border-zinc-700">
                                        <tr>
                                            <th class="px-4 py-3 font-medium">Tanggal</th>
                                            <th class="px-4 py-3 font-medium">Referensi</th>
                                            <th class="px-4 py-3 font-medium">Tipe</th>
                                            <th class="px-4 py-3 font-medium">Gudang</th>
                                            <th class="px-4 py-3 font-medium text-right">Kuantitas</th>
                                            <th class="px-4 py-3 font-medium text-center">Sisa Stok</th>
                                            <th class="px-4 py-3 font-medium">Petugas & Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @forelse($movements as $m)
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                                <td class="px-4 py-3 whitespace-nowrap text-zinc-600 dark:text-zinc-400">
                                                    {{ $m->created_at->format('d M Y H:i') }}
                                                </td>
                                                <td class="px-4 py-3 font-mono text-xs">
                                                    {{ $m->reference_number ?: '-' }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if(str_contains($m->type, 'in'))
                                                        <flux:badge color="emerald" size="sm" class="uppercase text-[10px]">Masuk</flux:badge>
                                                    @elseif(str_contains($m->type, 'out'))
                                                        <flux:badge color="rose" size="sm" class="uppercase text-[10px]">Keluar</flux:badge>
                                                    @else
                                                        <flux:badge color="zinc" size="sm" class="uppercase text-[10px]">{{ str_replace('_', ' ', $m->type) }}</flux:badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $m->warehouse?->name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-right font-bold {{ $m->quantity > 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                                    {{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}
                                                </td>
                                                <td class="px-4 py-3 text-center text-zinc-500 font-mono">
                                                    {{ $m->stock_after }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $m->user?->name ?? 'Sistem' }}</div>
                                                    @if($m->notes)
                                                        <div class="text-xs text-zinc-500 mt-0.5 truncate max-w-xs" title="{{ $m->notes }}">{{ $m->notes }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                                    Belum ada riwayat pergerakan stok untuk barang ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">Simpan Saldo</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
