<?php
use function Livewire\Volt\{state, computed, on, usesPagination};
use Modules\Purchase\Models\Vendor;

usesPagination();
state(['search' => '']);

$vendors = computed(function () {
    $query = Vendor::with(['purchaseOrders' => function($q) {
        $q->whereNotIn('status', ['rejected', 'cancelled']);
    }, 'purchaseOrders.receipts'])->latest();
    
    if ($this->search) {
        $query->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('city', 'like', '%' . $this->search . '%')
              ->orWhere('type', 'like', '%' . $this->search . '%');
    }
    return $query->paginate(10);
});

$analytics = computed(function () {
    $totalVendors = Vendor::count();
    
    // Total Belanja (mengabaikan PO yang dibatalkan/ditolak)
    $totalSpending = \Modules\Purchase\Models\PurchaseOrder::whereNotIn('status', ['rejected', 'cancelled'])->sum('total_amount');
    
    // Vendor Teratas berdasarkan jumlah PO
    $topVendorResult = \Modules\Purchase\Models\PurchaseOrder::select('vendor_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_orders'))
        ->groupBy('vendor_id')
        ->orderByDesc('total_orders')
        ->first();
        
    $topVendorName = 'Belum Ada';
    if ($topVendorResult && $topVendorResult->vendor_id) {
        $vendor = Vendor::find($topVendorResult->vendor_id);
        if ($vendor) {
            $topVendorName = $vendor->name . ' (' . $topVendorResult->total_orders . ' PO)';
        }
    }

    return [
        'total_vendors' => $totalVendors,
        'total_spending' => $totalSpending,
        'top_vendor' => $topVendorName,
        'supplier_count' => Vendor::where('type', 'Supplier')->count(),
        'pengrajin_count' => Vendor::where('type', 'Pengrajin')->count(),
    ];
});

on([
    'vendor-saved' => function () {
        $this->resetPage();
    },
    'echo:purchase,VendorUpdated' => function () {
        $this->resetPage();
    }
]);

$delete = function ($id) {
    abort_unless(auth()->user()->can('purchase.delete'), 403, 'Tidak ada akses menghapus vendor.');
    Vendor::find($id)?->delete();
};
?>

<div>
    <x-sticky-header class="flex flex-col sm:flex-row justify-end md:justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden md:block w-max">
            <flux:heading size="lg">{{ __('Master Data Vendor') }}</flux:heading>
            <flux:subheading>{{ __('Kelola daftar supplier, pengrajin, dan ekspedisi.') }}</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3 w-full sm:w-auto">
            {{-- Search Bar --}}
            <div class="flex-1 sm:flex-none sm:w-72 relative">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama, kota, atau tipe..." />
            </div>

            @can('purchase.create')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-vendor-modal')" class="px-3 sm:px-4 shrink-0">
                    <span class="hidden sm:inline">Tambah Vendor</span>
                </flux:button>
            @endcan
        </div>
    </x-sticky-header>

    {{-- Analytics Dashboard --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400 flex items-center justify-center shrink-0">
                <flux:icon.building-storefront class="w-6 h-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Mitra</p>
                <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->analytics['total_vendors'] }}</h3>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.banknotes class="w-6 h-6" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">Total Belanja (All PO)</p>
                <h3 class="text-2xl font-bold text-zinc-900 dark:text-white truncate" title="Rp {{ number_format($this->analytics['total_spending'], 0, ',', '.') }}">Rp {{ number_format($this->analytics['total_spending'], 0, ',', '.') }}</h3>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <flux:icon.star class="w-6 h-6" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Vendor Teraktif</p>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white truncate" title="{{ $this->analytics['top_vendor'] }}">{{ $this->analytics['top_vendor'] }}</h3>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                <flux:icon.chart-pie class="w-6 h-6" />
            </div>
            <div class="w-full">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Distribusi Tipe</p>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->analytics['supplier_count'] }} <span class="font-normal text-zinc-500">Supplier</span></span>
                    <div class="w-1 h-1 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->analytics['pengrajin_count'] }} <span class="font-normal text-zinc-500">Pengrajin</span></span>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Unified Grid View --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
        @forelse ($this->vendors as $vendor)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-cyan-500 hover:ring-1 hover:ring-cyan-500 transition-all flex flex-col h-full group relative overflow-hidden">
                
                {{-- Badge Status/Type --}}
                <div class="absolute top-4 right-4">
                    <flux:badge size="sm" :color="match($vendor->type) {
                        'Supplier' => 'blue',
                        'Pengrajin' => 'amber',
                        'Ekspedisi' => 'green',
                        default => 'zinc',
                    }">
                        {{ $vendor->type }}
                    </flux:badge>
                </div>

                {{-- Avatar & Name --}}
                <div class="flex flex-col items-center mt-2 mb-4 text-center">
                    <flux:avatar src="{{ $vendor->image ? Storage::url($vendor->image) : '' }}" fallback="{{ substr($vendor->name, 0, 2) }}" size="xl" class="mb-3 ring-4 ring-zinc-50 dark:ring-zinc-900 group-hover:ring-cyan-50 dark:group-hover:ring-cyan-900/20 transition-all" />
                    <h4 class="font-bold text-zinc-900 dark:text-white text-lg leading-tight group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">{{ $vendor->name }}</h4>
                    <p class="text-xs text-zinc-500 mt-1 flex items-center justify-center gap-1">
                        <flux:icon.map-pin class="w-3.5 h-3.5" />
                        {{ implode(', ', array_filter([$vendor->city, $vendor->province])) ?: '-' }}
                    </p>
                </div>

                {{-- Contact Info --}}
                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-3 mb-auto flex items-center justify-center gap-2">
                    <flux:icon.phone class="w-4 h-4 text-zinc-400" />
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $vendor->phone ?? 'Belum ada nomor' }}</span>
                </div>
                
                {{-- Compact Analytics --}}
                @php
                    $pos = $vendor->purchaseOrders;
                    $orderFreq = $pos->count();
                    $totalTrans = $pos->sum('total_amount');
                    
                    $totalDays = 0;
                    $receiptCount = 0;
                    foreach ($pos as $po) {
                        $firstReceipt = $po->receipts->sortBy('receipt_date')->first();
                        if ($firstReceipt && $po->order_date) {
                            $diff = max(0, \Carbon\Carbon::parse($po->order_date)->diffInDays(\Carbon\Carbon::parse($firstReceipt->receipt_date)));
                            $totalDays += $diff;
                            $receiptCount++;
                        }
                    }
                    $leadTime = $receiptCount > 0 ? round($totalDays / $receiptCount, 1) : 0;
                @endphp
                <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Transaksi</span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate" title="Rp {{ number_format($totalTrans, 0, ',', '.') }}">
                            @if($totalTrans >= 1000000)
                                Rp {{ round($totalTrans/1000000, 1) }}Jt
                            @elseif($totalTrans >= 1000)
                                Rp {{ round($totalTrans/1000, 1) }}rb
                            @else
                                Rp {{ $totalTrans }}
                            @endif
                        </span>
                    </div>
                    <div class="flex flex-col border-x border-zinc-100 dark:border-zinc-800">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Order</span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $orderFreq }} <span class="font-normal text-zinc-500">PO</span></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Lead Time</span>
                        <span class="text-xs font-semibold {{ $leadTime > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500' }}">{{ number_format($leadTime, 1, ',', '.') }} <span class="font-normal text-zinc-500">Hari</span></span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="grid grid-cols-2 gap-2 mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button variant="subtle" size="sm" class="w-full" wire:click="$dispatch('open-vendor-detail', { id: {{ $vendor->id }} })">
                        Detail
                    </flux:button>
                    
                    <div class="flex items-center gap-1 w-full justify-end">
                        @can('purchase.vendor.update')
                            <flux:button variant="ghost" size="sm" icon="pencil-square" class="text-zinc-500 hover:text-zinc-900" wire:click="$dispatch('open-vendor-modal', { id: {{ $vendor->id }} })" title="Edit Vendor" />
                        @endcan
                        @can('purchase.vendor.delete')
                            <flux:button variant="ghost" size="sm" icon="trash" class="text-red-500 hover:bg-red-50" wire:click="delete({{ $vendor->id }})" wire:confirm="Yakin ingin menghapus vendor ini?" title="Hapus Vendor" />
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 flex flex-col items-center justify-center text-center bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl border-dashed">
                <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Belum Ada Vendor</h3>
                <p class="text-zinc-500 max-w-sm mt-1">Anda belum memiliki data vendor. Silakan tambah vendor baru untuk mulai bertransaksi.</p>
                @can('purchase.vendor.create')
                    <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-vendor-modal')" class="mt-4">
                        Tambah Vendor Baru
                    </flux:button>
                @endcan
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->vendors->links() }}
    </div>

    {{-- Global Vendor Form Modal --}}
    <livewire:global.vendor-form-modal />
    
    {{-- Vendor Detail Analytics Modal --}}
    <livewire:vendor.vendor-detail-modal />
</div>
