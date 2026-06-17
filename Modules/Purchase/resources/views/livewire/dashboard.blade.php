<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Purchase\Models\Vendor;
use Carbon\Carbon;

layout('layouts.app');
title('Dashboard Pembelian');

$kpis = computed(function () {
    $now = Carbon::now();
    
    // Total PO bulan ini (excluding cancelled/rejected)
    $totalPO = PurchaseOrder::whereNotIn('status', ['rejected', 'cancelled'])
        ->whereMonth('order_date', $now->month)
        ->whereYear('order_date', $now->year)
        ->count();
        
    // Pending Queue
    $pendingQueue = PurchaseQueue::where('status', 'approved')->count();
    
    // Total Spending bulan ini (completed/processing/partially_received)
    $totalSpending = PurchaseOrder::whereIn('status', ['completed', 'processing', 'partially_received'])
        ->whereMonth('order_date', $now->month)
        ->whereYear('order_date', $now->year)
        ->sum('total_amount');
        
    // Total Active Vendors
    $totalVendors = Vendor::count();

    return [
        'total_po' => $totalPO,
        'pending_queue' => $pendingQueue,
        'total_spending' => $totalSpending,
        'total_vendors' => $totalVendors,
    ];
});

$recentOrders = computed(function () {
    return PurchaseOrder::with('vendor')
        ->latest('created_at')
        ->take(5)
        ->get();
});

on(['echo:purchase,OrderUpdated' => function () {}, 'echo:purchase,QueueUpdated' => function () {}, 'echo:purchase,VendorUpdated' => function () {}]);
?>

<div>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Dashboard Pembelian</flux:heading>
            <div class="flex gap-2">
                @can('purchase.queue.view')
                    <flux:button variant="ghost" icon="queue-list" :href="route('purchase.queues.kanban')" wire:navigate>Lihat Antrean</flux:button>
                @endcan
                @can('purchase.order.create')
                    <flux:button variant="primary" icon="plus" :href="route('purchase.orders.create')" wire:navigate>Buat PO Baru</flux:button>
                @endcan
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-cyan-500 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider">Total PO (Bulan Ini)</h3>
                    <div class="rounded-md bg-blue-50 dark:bg-blue-500/10 p-1.5 text-blue-600 dark:text-blue-400">
                        <flux:icon.document-text class="w-4 h-4" />
                    </div>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->kpis['total_po'], 0, ',', '.') }}</p>
            </div>
            
            <a href="{{ route('purchase.queues.kanban') }}" wire:navigate class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-amber-500 hover:shadow-sm hover:-translate-y-0.5 transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider group-hover:text-amber-600 dark:group-hover:text-amber-500 transition-colors">Antrean Menunggu</h3>
                    <div class="rounded-md bg-amber-50 dark:bg-amber-500/10 p-1.5 text-amber-600 dark:text-amber-400 group-hover:bg-amber-100 dark:group-hover:bg-amber-500/20 transition-colors">
                        <flux:icon.clock class="w-4 h-4" />
                    </div>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->kpis['pending_queue'], 0, ',', '.') }}</p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-2 font-medium flex items-center gap-1">
                    Siap dibuatkan PO
                    <flux:icon.arrow-right class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                </p>
            </a>

            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-emerald-500 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider">Total Pembelanjaan</h3>
                    <div class="rounded-md bg-emerald-50 dark:bg-emerald-500/10 p-1.5 text-emerald-600 dark:text-emerald-400">
                        <flux:icon.banknotes class="w-4 h-4" />
                    </div>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white" title="Rp {{ number_format($this->kpis['total_spending'], 0, ',', '.') }}">
                    @if($this->kpis['total_spending'] >= 1000000)
                        Rp {{ round($this->kpis['total_spending']/1000000, 1) }}Jt
                    @else
                        Rp {{ number_format($this->kpis['total_spending'], 0, ',', '.') }}
                    @endif
                </p>
                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-2 font-medium">Bulan ini</p>
            </div>

            <a href="{{ route('purchase.vendors.index') }}" wire:navigate class="block bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-purple-500 hover:shadow-sm hover:-translate-y-0.5 transition-all group">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider group-hover:text-purple-600 dark:group-hover:text-purple-500 transition-colors">Total Vendor</h3>
                    <div class="rounded-md bg-purple-50 dark:bg-purple-500/10 p-1.5 text-purple-600 dark:text-purple-400 group-hover:bg-purple-100 dark:group-hover:bg-purple-500/20 transition-colors">
                        <flux:icon.building-storefront class="w-4 h-4" />
                    </div>
                </div>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->kpis['total_vendors'], 0, ',', '.') }}</p>
                <p class="text-xs text-purple-600 dark:text-purple-400 mt-2 font-medium flex items-center gap-1">
                    Lihat daftar vendor
                    <flux:icon.arrow-right class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                </p>
            </a>
        </div>

        {{-- Recent Orders Table --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Purchase Order Terbaru</h3>
                @can('purchase.order.view')
                    <a href="{{ route('purchase.orders.kanban') }}" wire:navigate class="text-sm font-medium text-cyan-600 hover:text-cyan-500">Lihat Semua</a>
                @endcan
            </div>
            
            @if($this->recentOrders->isEmpty())
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                    <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Belum Ada Transaksi</h3>
                    <p class="text-zinc-500 text-sm mt-1">Belum ada Purchase Order yang dibuat.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr class="text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider">
                                <th class="px-5 py-3 font-semibold">Nomor PO</th>
                                <th class="px-5 py-3 font-semibold">Tanggal</th>
                                <th class="px-5 py-3 font-semibold">Vendor</th>
                                <th class="px-5 py-3 font-semibold">Total</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-sm">
                            @foreach($this->recentOrders as $order)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-5 py-3 font-medium text-cyan-600 dark:text-cyan-400">
                                        {{ $order->po_number }}
                                    </td>
                                    <td class="px-5 py-3 text-zinc-600 dark:text-zinc-400">
                                        {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('d M Y') }}
                                    </td>
                                    <td class="px-5 py-3 text-zinc-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <flux:avatar src="{{ $order->vendor->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor->name, 0, 2) }}" size="xs" />
                                            {{ $order->vendor->name }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-zinc-900 dark:text-white">
                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <flux:badge size="sm" :color="match($order->status) {
                                            'draft' => 'zinc',
                                            'processing' => 'amber',
                                            'partially_received' => 'blue',
                                            'completed' => 'emerald',
                                            'cancelled' => 'red',
                                            default => 'zinc',
                                        }">
                                            {{ match($order->status) {
                                                'draft' => 'Draft',
                                                'processing' => 'Diproses',
                                                'partially_received' => 'Diterima Parsial',
                                                'completed' => 'Selesai',
                                                'cancelled' => 'Dibatalkan',
                                                default => 'Unknown',
                                            } }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
