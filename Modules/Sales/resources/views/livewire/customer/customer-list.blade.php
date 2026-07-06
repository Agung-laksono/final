<?php
use function Livewire\Volt\{state, computed, on, usesPagination, layout};
use Modules\Sales\Models\Customer;

layout('layouts.app');

usesPagination();
state(['search' => '']);

$customers = computed(function () {
    $query = Customer::with(['salesOrders'])->latest();
    
    if ($this->search) {
        $query->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('company', 'like', '%' . $this->search . '%')
              ->orWhere('email', 'like', '%' . $this->search . '%');
    }
    return $query->paginate(10);
});

$analytics = computed(function () {
    $totalCustomers = Customer::count();
    
    // Total Penjualan
    $totalSales = \Modules\Sales\Models\SalesOrder::whereNotIn('status', ['rejected'])->sum('total_amount');
    
    // Pelanggan Teratas berdasarkan jumlah SO
    $topCustomerResult = \Modules\Sales\Models\SalesOrder::select('customer_id', \Illuminate\Support\Facades\DB::raw('count(*) as total_orders'))
        ->groupBy('customer_id')
        ->orderByDesc('total_orders')
        ->first();
        
    $topCustomerName = 'Belum Ada';
    if ($topCustomerResult && $topCustomerResult->customer_id) {
        $customer = Customer::find($topCustomerResult->customer_id);
        if ($customer) {
            $topCustomerName = $customer->name . ' (' . $topCustomerResult->total_orders . ' SO)';
        }
    }

    return [
        'total_customers' => $totalCustomers,
        'total_sales' => $totalSales,
        'top_customer' => $topCustomerName,
    ];
});

on([
    'customer-saved' => function () {
        $this->resetPage();
    }
]);

$delete = function ($id) {
    abort_unless(auth()->user()->can('sales.customer.delete'), 403, 'Tidak ada akses menghapus pelanggan.');
    Customer::find($id)?->delete();
};
?>

<div>
    <x-sticky-header class="flex flex-col sm:flex-row justify-end md:justify-between items-start sm:items-center mb-6 gap-4">
        <div class="hidden md:block w-max">
            <flux:heading size="lg">{{ __('Data Pelanggan') }}</flux:heading>
            <flux:subheading>{{ __('Kelola daftar pelanggan dan riwayat transaksi penjualan mereka.') }}</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3 w-full sm:w-auto">
            {{-- Search Bar --}}
            <div class="flex-1 sm:flex-none sm:w-72 relative">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama, perusahaan, atau email..." />
            </div>

            @can('sales.customer.create')
                <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-customer-modal')" class="px-3 sm:px-4 shrink-0">
                    <span class="hidden sm:inline">Tambah Pelanggan</span>
                </flux:button>
            @endcan
        </div>
    </x-sticky-header>

    {{-- Analytics Dashboard --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-cyan-100 dark:bg-cyan-900/40 text-cyan-600 dark:text-cyan-400 flex items-center justify-center shrink-0">
                <flux:icon.users class="w-6 h-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Pelanggan</p>
                <h3 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->analytics['total_customers'] }}</h3>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <flux:icon.banknotes class="w-6 h-6" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 truncate">Total Penjualan</p>
                <h3 class="text-2xl font-bold text-zinc-900 dark:text-white truncate" title="Rp {{ number_format($this->analytics['total_sales'], 0, ',', '.') }}">Rp {{ number_format($this->analytics['total_sales'], 0, ',', '.') }}</h3>
            </div>
        </flux:card>

        <flux:card class="flex items-center gap-4 p-4 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <flux:icon.star class="w-6 h-6" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pelanggan Teraktif</p>
                <h3 class="text-base font-bold text-zinc-900 dark:text-white truncate" title="{{ $this->analytics['top_customer'] }}">{{ $this->analytics['top_customer'] }}</h3>
            </div>
        </flux:card>
    </div>

    {{-- Unified Grid View --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
        @forelse ($this->customers as $customer)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-cyan-500 hover:ring-1 hover:ring-cyan-500 transition-all flex flex-col h-full group relative overflow-hidden">
                
                {{-- Badge Status/Type --}}
                <div class="absolute top-4 right-4">
                    <flux:badge size="sm" :color="match($customer->type) {
                        'Reguler' => 'zinc',
                        'VIP' => 'blue',
                        'Grosir' => 'amber',
                        'Distributor' => 'green',
                        default => 'zinc',
                    }">
                        {{ $customer->type }}
                    </flux:badge>
                </div>

                {{-- Avatar & Name --}}
                <div class="flex flex-col items-center mt-2 mb-4 text-center">
                    <flux:avatar src="{{ $customer->image ? Storage::url($customer->image) : '' }}" fallback="{{ substr($customer->name, 0, 2) }}" size="xl" class="mb-3 ring-4 ring-zinc-50 dark:ring-zinc-900 group-hover:ring-cyan-50 dark:group-hover:ring-cyan-900/20 transition-all" />
                    <h4 class="font-bold text-zinc-900 dark:text-white text-lg leading-tight group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">{{ $customer->name }}</h4>
                    <p class="text-xs text-zinc-500 mt-1 flex items-center justify-center gap-1">
                        <flux:icon.map-pin class="w-3.5 h-3.5" />
                        {{ implode(', ', array_filter([$customer->city, $customer->province])) ?: '-' }}
                    </p>
                </div>

                {{-- Contact Info --}}
                <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-lg p-3 mb-auto flex flex-col gap-2">
                    <div class="flex items-center gap-2">
                        <flux:icon.phone class="w-4 h-4 text-zinc-400" />
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $customer->phone ?? '-' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:icon.envelope class="w-4 h-4 text-zinc-400" />
                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $customer->email ?? '-' }}</span>
                    </div>
                </div>
                
                {{-- Compact Analytics --}}
                @php
                    $sos = $customer->salesOrders;
                    $orderFreq = $sos->count();
                    $totalTrans = $sos->sum('total_amount');
                @endphp
                <div class="grid grid-cols-2 gap-2 mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800 text-center">
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
                    <div class="flex flex-col border-l border-zinc-100 dark:border-zinc-800">
                        <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Order</span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">{{ $orderFreq }} <span class="font-normal text-zinc-500">SO</span></span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-1 w-full justify-end mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    @can('sales.customer.update')
                        <flux:button variant="ghost" size="sm" icon="pencil-square" class="text-zinc-500 hover:text-zinc-900" wire:click="$dispatch('open-customer-modal', { id: {{ $customer->id }} })" title="Edit Pelanggan" />
                    @endcan
                    @can('sales.customer.delete')
                        <flux:button variant="ghost" size="sm" icon="trash" class="text-red-500 hover:bg-red-50" wire:click="delete({{ $customer->id }})" wire:confirm="Yakin ingin menghapus pelanggan ini?" title="Hapus Pelanggan" />
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 flex flex-col items-center justify-center text-center bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl border-dashed">
                <flux:icon.users class="w-12 h-12 text-zinc-300 mb-3" />
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Belum Ada Pelanggan</h3>
                <p class="text-zinc-500 max-w-sm mt-1">Anda belum memiliki data pelanggan. Silakan tambah pelanggan baru untuk mulai berjualan.</p>
                @can('sales.customer.create')
                    <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-customer-modal')" class="mt-4">
                        Tambah Pelanggan Baru
                    </flux:button>
                @endcan
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->customers->links() }}
    </div>

    {{-- Modal Tambah/Edit Customer --}}
    <livewire:customer.customer-form-modal />
</div>
