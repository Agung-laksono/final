<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Sales\Models\SalesOrder;

layout('layouts.app');
title('Kanban Sales Order');

// Definisi Kolom Kanban untuk Sales Order
state([
    'columns' => [
        'pending_approval' => ['title' => 'Menunggu Persetujuan', 'color' => 'amber'],
        'processing' => ['title' => 'Diproses Gudang', 'color' => 'blue'],
        'packing' => ['title' => 'Packing', 'color' => 'purple'],
        'shipping' => ['title' => 'Pengiriman', 'color' => 'orange'],
        'completed' => ['title' => 'Selesai', 'color' => 'emerald'],
    ],
    'transparent_columns' => false,
    'search' => '',
]);

$orders = computed(function () {
    $query = SalesOrder::with(['customer', 'creator'])->latest();
    if ($this->search) {
        $query->where('so_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q) {
                  $q->where('name', 'like', '%' . $this->search . '%');
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
    }
};

$markAsArrived = function ($orderId) {
    abort_unless(auth()->user()->can('sales.order.update'), 403);
    
    $so = SalesOrder::find($orderId);
    if ($so) {
        $so->status = 'completed';
        $so->save();
        
        // Di sini bisa ditambahkan logika pengurangan stok otomatis (mutasi keluar)
        // secara formal di inventory system.
        
        \Flux::toast('Pesanan selesai! Barang sudah diterima pelanggan.', variant: 'success');
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $orders dijalankan ulang
}]);

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="hidden md:block">
            <flux:heading size="xl">Kanban Sales Order</flux:heading>
            <flux:subheading>Atur dan pantau progres pesanan penjualan secara visual.</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <div class="flex-1 min-w-0 md:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari SO atau nama pelanggan..." />
            </div>

            <div class="flex items-center gap-3 shrink-0">
                {{-- Toggle Transparan --}}
                <div class="hidden sm:flex" title="Mode Transparan">
                    <flux:switch wire:model.live="transparent_columns" label="Transparan" />
                </div>
                <div class="flex sm:hidden" title="Mode Transparan">
                    <flux:switch wire:model.live="transparent_columns" />
                </div>

                @can('sales.order.create')
                    {{-- Tombol Add --}}
                    <flux:button variant="primary" icon="plus" href="{{ route('sales.orders.create') }}" wire:navigate class="px-3 sm:px-4 shrink-0">
                        <span class="hidden sm:inline">Buat Pesanan Baru</span>
                    </flux:button>
                @endcan
            </div>
        </div>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex justify-start gap-6 overflow-x-auto pb-4 h-[calc(100vh-12rem)] -mx-4 px-4 md:mx-0 md:px-0 snap-x snap-mandatory scroll-smooth custom-scrollbar items-stretch">
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: {{ $statusKey === 'completed' ? 'true' : 'false' }} }"
                 class="flex-shrink-0 h-full max-h-full rounded-xl flex flex-col transition-all duration-300 snap-center {{ $transparent_columns ? '' : 'bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800' }}"
                 :class="collapsed ? 'w-16' : 'w-80'"
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300 {{ $transparent_columns ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800' }}"
                     :class="collapsed ? 'flex-col gap-4' : ''">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->orders[$statusKey] ?? []) }}</flux:badge>
                        @if($statusKey === 'completed')
                            <button @click="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" title="Toggle Kolom Selesai">
                                <flux:icon.arrows-right-left class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 p-3 overflow-y-auto space-y-3 custom-scrollbar">
                    
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
                                <div class="flex gap-2 mt-2">
                                    <div class="text-[10px] text-zinc-500 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 w-fit">
                                        {{ count($order->items ?? []) }} Barang
                                    </div>
                                    @if($order->courier_vendor)
                                    <div class="text-[10px] text-zinc-500 bg-white dark:bg-zinc-900 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700 w-fit flex items-center gap-1">
                                        <flux:icon.truck class="w-3 h-3" /> {{ $order->courier_vendor }}
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
                                
                                <div class="flex gap-1 opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity">
                                    <flux:button size="sm" variant="subtle" icon="eye" class="h-6 w-6 p-0" title="Detail SO" wire:click.stop="$dispatch('open-detail-modal', { orderId: {{ $order->id }} })" />
                                    
                                    {{-- Tombol Pembayaran --}}
                                    @canany(['sales.payment.create', 'sales.payment.validate'])
                                        <flux:button size="sm" variant="subtle" icon="banknotes" class="h-6 w-6 p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/50" title="Pembayaran" wire:click.stop="$dispatch('open-payment-modal', { orderId: {{ $order->id }} })" />
                                    @endcanany
                                    
                                    {{-- Tombol Aksi Spesifik --}}
                                    @if($statusKey === 'pending_approval')
                                        @can('sales.approve.update')
                                            <flux:button size="sm" variant="subtle" icon="check-circle" class="h-6 w-6 p-0 text-amber-600 hover:text-amber-700 hover:bg-amber-50 dark:hover:bg-amber-900/50" title="Persetujuan" wire:click.stop="$dispatch('open-approval-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'processing')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="qr-code" class="h-6 w-6 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" title="Fulfillment Gudang" wire:click.stop="$dispatch('open-fulfillment-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'packing')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="archive-box" class="h-6 w-6 p-0 text-purple-600 hover:text-purple-700 hover:bg-purple-50 dark:hover:bg-purple-900/50" title="Input Detail Packing" wire:click.stop="$dispatch('open-packing-modal', { orderId: {{ $order->id }} })" />
                                            <flux:button size="sm" variant="subtle" icon="truck" class="h-6 w-6 p-0 text-orange-600 hover:text-orange-700 hover:bg-orange-50 dark:hover:bg-orange-900/50" title="Kirim via Ekspedisi" wire:click.stop="$dispatch('open-shipping-modal', { orderId: {{ $order->id }} })" />
                                        @endcan
                                    @elseif($statusKey === 'shipping')
                                        @can('sales.order.update')
                                            <flux:button size="sm" variant="subtle" icon="truck" class="h-6 w-6 p-0 text-orange-600 hover:text-orange-700 hover:bg-orange-50 dark:hover:bg-orange-900/50" title="Tandai Sampai" wire:click.stop="markAsArrived({{ $order->id }})" />
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

    <livewire:sales-order.approval-modal />
    <livewire:sales-order.fulfillment-modal />
    <livewire:sales-order.packing-modal />
    <livewire:sales-order.shipping-modal />
    <livewire:sales-order.payment-modal />
    <livewire:sales-order.detail-modal />
    <livewire:global.vendor-gallery-modal />
</div>

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