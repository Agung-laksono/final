<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

layout('layouts.app');
title('Kanban Sales Order');

// Definisi Kolom Kanban untuk Sales Order
state([
    'columns' => function () {
        // Jika user HANYA memiliki role Gudang (bukan Admin/Manager/Sales)
        if (auth()->user()->hasRole('Gudang') && !auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales'])) {
            return [
                'processing' => ['title' => 'Diproses Gudang', 'color' => 'blue'],
                'packing' => ['title' => 'Packing', 'color' => 'purple'],
            ];
        }
        
        // Tampilan default untuk peran lain
        return [
            'pending_approval' => ['title' => 'Menunggu Persetujuan', 'color' => 'amber'],
            'processing' => ['title' => 'Diproses Gudang', 'color' => 'blue'],
            'packing' => ['title' => 'Packing', 'color' => 'purple'],
            'shipping' => ['title' => 'Pengiriman', 'color' => 'orange'],
            'arrived' => ['title' => 'Sampai', 'color' => 'teal'],
            'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
            'archived' => ['title' => 'Arsip', 'color' => 'zinc'],
        ];
    },
    'transparent_columns' => false,
    'search' => '',
    'setting_version' => 0, // bumped saat setting berubah → paksa re-render
]);

$orders = computed(function () {
    $query = SalesOrder::with(['customer', 'creator', 'items', 'fulfillments'])->latest();
    
    // Batasi: Staf Sales biasa hanya bisa melihat SO buatannya sendiri.
    // Peran lain (Kepala Sales, Gudang, Shipping, Super Admin) tetap melihat semua.
    if (auth()->user()->hasRole('Sales') && !auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager'])) {
        $query->where('created_by', auth()->id());
    }

    if ($this->search) {
        $query->where(function($q) {
            $q->where('so_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q2) {
                  $q2->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    return $query->get()->groupBy(function($so) {
        return $so->status ?? 'pending_approval';
    });
});

$updateStatus = function ($orderId, $newStatus) {
    // Nanti ditambahkan validasi permission sesuai role untuk setiap status
    abort_unless(auth()->user()->can('sales.order.update'), 403, 'Anda tidak memiliki izin untuk mengubah status SO.');
    
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = $newStatus;
        $so->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    }
};

$markAsArchived = function ($orderId) {
    abort_unless(auth()->user()->can('sales.order.update') || auth()->user()->hasRole('Finance'), 403);
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = 'archived';
        $so->save();
        
        \Flux::toast('Pesanan berhasil diarsipkan.', variant: 'success');
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('sales_order');
    }
};

// markAsCompleted closure has been moved to completed-modal.blade.php

on([
    'status-updated' => function () {
        // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
    },
    'echo:kanban.sales_order,KanbanUpdated' => function () {},
    'echo:settings,SettingUpdated' => function () {
        Cache::forget('setting_gudang_handles_shipping');
        $this->setting_version++;
    },
]);

?>

<div class="kanban-root" x-data="{ isFullscreen: false, showHeader: true }" 
     :class="isFullscreen ? 'fixed inset-0 z-[100] bg-zinc-50 dark:bg-zinc-950 p-0 lg:p-4 flex flex-col transition-all duration-300' : 'relative flex flex-col w-full'"
     x-bind:style="isFullscreen ? '' : 'height: 100vh;'">
    
    <style>
        /* Paksa hilangkan padding bawaan layout KHUSUS untuk halaman Kanban ini, 
           serta ubah menjadi flex container agar tinggi bisa mengisi penuh 100% */
        div:has(> .kanban-root) {
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        
        /* Menyembunyikan scrollbar tapi tetap bisa digulir */
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .hide-scroll {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
     
    {{-- Floating Show Header Button --}}
    <div class="absolute top-2 right-2 sm:top-4 sm:right-6 z-[110]" x-show="!showHeader" x-transition x-cloak>
        <flux:button variant="subtle" class="rounded-full shadow-lg bg-white/90 dark:bg-zinc-800/90 backdrop-blur border border-zinc-200 dark:border-zinc-700 w-10 h-10 p-0 flex items-center justify-center" @click="showHeader = true" title="Tampilkan Alat">
            <flux:icon.chevron-down class="w-5 h-5 text-zinc-500" />
        </flux:button>
    </div>

    {{-- Floating Controls (Full Width) --}}
    <div class="absolute top-2 left-2 right-2 sm:top-4 sm:left-4 sm:right-4 z-[60] flex items-center justify-between gap-2 sm:gap-4 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md px-2 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-sm border border-zinc-200/50 dark:border-zinc-800/50" x-show="showHeader" x-transition>
        
        <div class="flex-1 min-w-0 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari SO atau nama pelanggan..." />
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <div class="hidden sm:flex items-center mr-2" title="Mode Transparan">
                <flux:switch wire:model.live="transparent_columns" label="Transparan" />
            </div>

            <flux:button variant="subtle" class="px-2.5 sm:px-3 text-zinc-500 hover:text-indigo-600" title="Layar Penuh" @click="isFullscreen = !isFullscreen">
                <flux:icon.arrows-pointing-out class="w-5 h-5" x-show="!isFullscreen" />
                <flux:icon.arrows-pointing-in class="w-5 h-5 text-indigo-600" x-show="isFullscreen" x-cloak />
            </flux:button>

            <flux:button variant="subtle" class="px-2.5 sm:px-3 text-zinc-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" title="Sembunyikan Alat" @click="showHeader = false">
                <flux:icon.eye-slash class="w-5 h-5" />
            </flux:button>

            @can('sales.order.create')
                <div class="w-px h-6 bg-zinc-200 dark:bg-zinc-700 mx-1 sm:mx-2 hidden sm:block"></div>
                <flux:button variant="primary" icon="plus" href="{{ route('sales.orders.create') }}" wire:navigate class="px-2.5 sm:px-4">
                    <span class="hidden sm:inline">Buat Pesanan</span>
                    <span class="sm:hidden">Buat</span>
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex-1 min-h-0 flex flex-col px-0 lg:px-6 transition-all duration-300"
         :class="showHeader ? 'pt-16 sm:pt-20 lg:pt-24' : 'pt-2 lg:pt-6'">
        <div class="flex-1 min-h-0 overflow-x-auto pb-2 lg:pb-4 snap-x snap-mandatory scroll-smooth custom-scrollbar">
            <div class="flex justify-start gap-3 sm:gap-4 lg:gap-6 items-stretch min-w-max h-full px-2 lg:px-0">
            @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: $persist({{ in_array($statusKey, ['completed', 'archived']) ? 'true' : 'false' }}).as('kanban-col-sales-{{ $statusKey }}-user-{{ auth()->id() }}') }"
                 style="height: 100%; display: flex; flex-direction: column;"
                 class="flex-shrink-0 rounded-xl transition-all duration-300 snap-center {{ $transparent_columns ? '' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800' }}"
                 :class="collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80'"
                 @click="if(collapsed) collapsed = false"
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300 {{ $transparent_columns ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800' }}"
                     :class="collapsed ? 'flex-col gap-4 h-full pb-8' : ''">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        <button @click.stop="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-right-left class="w-4 h-4" x-bind:class="collapsed ? 'rotate-90' : ''" />
                        </button>
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms 
                     class="flex-1 p-3 overflow-y-auto space-y-3"
                     :class="$wire.transparent_columns ? 'hide-scroll' : 'custom-scrollbar'">
                    
                    {{-- Deskripsi Aktor --}}
                    <p class="text-[10px] text-zinc-400 uppercase tracking-wider font-semibold mb-2">
                        @if($statusKey === 'pending_approval') Kepala Penjualan
                        @elseif($statusKey === 'processing') Tim Gudang (Inventory)
                        @elseif($statusKey === 'packing') Tim Packing
                        @elseif($statusKey === 'shipping') Sales & Ekspedisi
                        @elseif($statusKey === 'completed') Selesai
                        @endif
                    </p>

                    @forelse($this->orders[$statusKey] ?? [] as $order)
                        <div wire:key="order-{{ $order->id }}"
                             class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative">
                            
                            {{-- Header Kartu (Customer & Pembayaran) --}}
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name, 0, 2) }}" size="xs" class="ring-2 ring-white dark:ring-zinc-900" />
                                    <div>
                                        <div class="font-bold text-sm text-zinc-900 dark:text-white leading-none hover:text-cyan-600 cursor-pointer">{{ $order->customer->name }}</div>
                                        <div class="text-[10px] text-zinc-500 mt-0.5">{{ $order->customer->city ?: 'Tanpa Kota' }}</div>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    {{-- Status Pembayaran --}}
                                    @if($order->payment_status === 'paid')
                                        <flux:badge size="sm" color="green" icon="check-circle" class="!px-1.5 !py-0.5 !text-[10px]">Lunas</flux:badge>
                                    @elseif($order->payment_status === 'partial')
                                        <flux:badge size="sm" color="amber" icon="clock" class="!px-1.5 !py-0.5 !text-[10px]">Parsial</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red" icon="x-circle" class="!px-1.5 !py-0.5 !text-[10px]">Belum Bayar</flux:badge>
                                    @endif
                                </div>
                            </div>
                            
                            {{-- Konten Utama Kartu --}}
                            <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-2.5 mb-3 border border-zinc-100 dark:border-zinc-800">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs font-mono font-medium text-zinc-600 dark:text-zinc-400 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">{{ $order->so_number }}</span>
                                    <span class="text-[10px] text-zinc-500">{{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</span>
                                </div>
                                <div class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mt-2">
                                    Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                </div>
                                @php
                                    $orderedQty = $order->items->sum('qty');
                                    $fulfilledQty = $order->fulfillments->sum('scanned_qty');
                                    $progressPercent = $orderedQty > 0 ? min(100, round(($fulfilledQty / $orderedQty) * 100)) : 0;
                                    
                                    $totalActualPacking = (float)$order->actual_packing_fee + $order->items->sum('actual_packing_fee');
                                    $totalActualShipping = (float)$order->actual_shipping_fee + $order->items->sum('actual_shipping_fee');
                                    
                                    $totalVerifiedPayment = $order->payments->where('status', 'verified')->sum('amount');
                                    $totalAmount = (float)$order->total_amount;
                                    $paymentPercent = $totalAmount > 0 ? ($totalVerifiedPayment >= $totalAmount ? 100 : floor(($totalVerifiedPayment / $totalAmount) * 100)) : 0;
                                @endphp
                                
                                <div class="mt-3 space-y-2">
                                    {{-- Progress Bar Gudang --}}
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-[10px] font-medium">
                                            <span class="text-zinc-500">Progress Gudang</span>
                                            <span class="{{ $progressPercent === 100 ? 'text-emerald-600' : 'text-zinc-700 dark:text-zinc-300' }}">{{ $fulfilledQty }} / {{ $orderedQty }} ({{ $progressPercent }}%)</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full {{ $progressPercent === 100 ? 'bg-emerald-500' : 'bg-blue-500' }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                                        </div>
                                    </div>
                                    
                                    {{-- Progress Bar Pembayaran --}}
                                    <div class="space-y-1 mt-2">
                                        <div class="flex justify-between text-[10px] font-medium">
                                            <span class="text-zinc-500">Pembayaran</span>
                                            <span class="{{ $paymentPercent === 100 ? 'text-emerald-600' : 'text-zinc-700 dark:text-zinc-300' }}">Rp {{ number_format($totalVerifiedPayment, 0, ',', '.') }} / {{ number_format($totalAmount, 0, ',', '.') }} ({{ $paymentPercent }}%)</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            <div class="h-full {{ $paymentPercent === 100 ? 'bg-emerald-500' : 'bg-amber-500' }} transition-all duration-500" style="width: {{ $paymentPercent }}%"></div>
                                        </div>
                                    </div>
                                    
                                    {{-- Packing Info (Hanya untuk Admin/Manager/Finance) --}}
                                    @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Finance']))
                                        @if(in_array($order->status, ['packing', 'shipping', 'completed']) && ($totalActualPacking > 0 || $order->packing_receipt_path || $order->packing_fee > 0))
                                            <div class="flex items-center justify-between text-[10px] bg-white dark:bg-zinc-900 p-1.5 rounded border border-zinc-200 dark:border-zinc-700">
                                                <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                    <flux:icon.archive-box class="w-3 h-3 text-purple-500 shrink-0" /> 
                                                    <span class="truncate">{{ $order->packing_receipt_path ? 'Nota Packing' : 'Packing' }}</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    @if($totalActualPacking > 0)
                                                        <span class="font-mono font-bold text-zinc-700 dark:text-zinc-300">{{ number_format($totalActualPacking, 0, ',', '.') }}</span>
                                                    @endif
                                                    @if($order->packing_receipt_path)
                                                        <a href="{{ Storage::url($order->packing_receipt_path) }}" target="_blank" class="text-purple-600 hover:text-purple-700 shrink-0" title="Lihat Nota Packing" wire:click.stop><flux:icon.document-check class="w-3 h-3" /></a>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endif

                                    {{-- Shipping Info --}}
                                    @if(in_array($order->status, ['shipping', 'arrived', 'completed', 'archived']) && ($order->courier_vendor || $totalActualShipping > 0 || $order->shipping_fee > 0))
                                        <div class="flex items-center justify-between text-[10px] bg-white dark:bg-zinc-900 p-1.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            <div class="flex items-center gap-1.5 text-zinc-600 dark:text-zinc-400">
                                                <flux:icon.truck class="w-3 h-3 text-orange-500 shrink-0" /> 
                                                <span class="truncate max-w-[100px]" title="{{ $order->courier_vendor }}">{{ $order->courier_vendor ?: 'Ekspedisi' }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5">
                                                @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Finance']) && $totalActualShipping > 0)
                                                    <span class="font-mono font-bold text-zinc-700 dark:text-zinc-300">{{ number_format($totalActualShipping, 0, ',', '.') }}</span>
                                                @endif
                                                @if($order->shipping_receipt_path)
                                                    <a href="{{ Storage::url($order->shipping_receipt_path) }}" target="_blank" class="text-orange-600 hover:text-orange-700 shrink-0" title="Lihat Resi" wire:click.stop><flux:icon.document-text class="w-3 h-3" /></a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Footer Kartu (Pembuat & Aksi) --}}
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-1.5" title="Dibuat oleh {{ $order->creator->name ?? 'Sistem' }}">
                                    <flux:icon.user-circle class="w-4 h-4 text-zinc-400" />
                                    <span class="text-[10px] font-medium text-zinc-500 truncate max-w-[100px]">
                                        {{ explode(' ', $order->creator->name ?? 'Sistem')[0] }}
                                    </span>
                                </div>
                                
                                <div class="flex gap-1 transition-opacity">
                                    <flux:button size="sm" variant="subtle" icon="eye" class="h-6 w-6 p-0" title="Detail SO" wire:click.stop="$dispatch('open-detail-modal', { orderId: {{ $order->id }} })" />
                                    
                                    {{-- Tombol Pembayaran --}}
                                    @if(!in_array($statusKey, ['completed', 'archived']))
                                        @canany(['sales.payment.create', 'sales.payment.validate'])
                                            <flux:button size="sm" variant="subtle" icon="banknotes" class="h-6 w-6 p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Pembayaran" wire:click.stop="$dispatch('open-payment-modal', { orderId: {{ $order->id }} })" />
                                        @endcanany
                                    @endif
                                    
                                    {{-- Tombol Aksi Spesifik --}}
                                    @if($statusKey === 'pending_approval')
                                        @can('sales.approve.update')
                                            <flux:button size="sm" variant="subtle" icon="check-circle" class="h-6 w-6 p-0 text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/50" title="Persetujuan" wire:click.stop="$dispatch('open-approval-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'packing')
                                        @php 
                                            // Membaca setting_version agar Livewire tahu bagian ini bergantung pada state
                                            $_sv = $setting_version;
                                            $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                            }) == '1';
                                        @endphp
                                        
                                        @if($order->is_packed)
                                            @if(!$gudangHandlesShipping)
                                                @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales', 'Kepala Sales']))
                                                    <flux:button size="sm" variant="subtle" icon="truck" class="h-6 w-6 p-0 text-orange-600 hover:text-orange-700 hover:bg-orange-50 dark:hover:bg-orange-900/50" title="Kirim via Ekspedisi" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })" />
                                                @endif
                                            @endif
                                        @endif
                                    @elseif($statusKey === 'shipping')
                                        @php 
                                            // Membaca setting_version agar Livewire tahu bagian ini bergantung pada state
                                            $_sv = $setting_version;
                                            $gudangHandlesShipping = \Illuminate\Support\Facades\Cache::remember('setting_gudang_handles_shipping', 3600, function () {
                                                return \App\Models\Setting::where('key', 'gudang_handles_shipping')->value('value');
                                            }) == '1';
                                        @endphp
                                        @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales', 'Kepala Sales']))
                                            <flux:button size="sm" variant="subtle" icon="flag" class="h-6 w-6 p-0 text-teal-600 hover:text-teal-700 hover:bg-teal-50 dark:hover:bg-teal-900/50" title="Tandai Barang Telah Sampai" wire:click.stop="$dispatch('open-arrived-modal', { orderId: {{ $order->id }} })" />
                                        @endif
                                        
                                        @if(!$gudangHandlesShipping)
                                            @if(auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Sales', 'Kepala Sales']))
                                                <flux:button size="sm" variant="subtle" icon="document-text" class="h-6 w-6 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Update Resi/Ekspedisi" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })" />
                                            @endif
                                        @endif
                                    @elseif($statusKey === 'arrived')
                                        @can('sales.order.complete')
                                            <flux:button size="sm" variant="subtle" icon="check-badge" class="h-6 w-6 p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Tandai Selesai" wire:click.stop="$dispatch('open-completed-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'completed')
                                        @can('sales.order.complete')
                                            <flux:button size="sm" variant="subtle" icon="inbox-arrow-down" class="h-6 w-6 p-0 text-zinc-600 hover:text-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800" title="Arsipkan Pesanan" wire:click.stop="markAsArchived({{ $order->id }})" />
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
            </div>
        </div>
    </div>

    <!-- Modals -->
    <livewire:sales-order.detail-modal />
    <livewire:sales-order.approval-modal />
    <livewire:sales-order.payment-modal />
    <livewire:sales-order.fulfillment-modal />
    <livewire:sales-order.packing-modal />
    <livewire:sales-order.shipping-modal />
    <livewire:sales-order.arrived-modal />
    <livewire:sales-order.completed-modal />
    <livewire:global.vendor-gallery-modal />
    <livewire:global.vendor-form-modal />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
            height: 4px;
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