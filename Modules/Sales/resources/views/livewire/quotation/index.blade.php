<?php
use function Livewire\Volt\{state, layout, title, computed, on, mount};
use Modules\Sales\Models\Quotation;

layout('layouts.app');
title('Penawaran Harga (Quotation)');

state([
        'columns' => [
        'draft' => ['title' => 'Draft / Konsep', 'color' => 'zinc'],
        'sent' => ['title' => 'Terkirim', 'color' => 'blue'],
        'accepted' => ['title' => 'Diterima', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
        'converted' => ['title' => 'Arsip (Selesai)', 'color' => 'amber'],
    ],
    'viewMode' => session('quotation_view_mode', 'table'),
    'search' => '',
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'perPage' => 10,
]);

$sort = function ($field) {
    if ($this->sortBy === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortBy = $field;
        $this->sortDirection = 'asc';
    }
};

$getBaseQuery = function () {
    $query = Quotation::with(['customer', 'creator', 'items'])
        ->select('quotations.*');
    
    if ($this->search) {
        $query->where(function($q) {
            $q->where('quotations.quotation_number', 'like', '%' . $this->search . '%')
              ->orWhereHas('customer', function($q2) {
                  $q2->where('name', 'like', '%' . $this->search . '%');
              });
        });
    }
    
    if ($this->sortBy === 'customer') {
        $query->leftJoin('customers', 'quotations.customer_id', '=', 'customers.id')
              ->orderBy('customers.name', $this->sortDirection);
    } elseif ($this->sortBy === 'creator') {
        $query->leftJoin('users', 'quotations.created_by', '=', 'users.id')
              ->orderBy('users.name', $this->sortDirection);
    } else {
        $query->orderBy('quotations.' . $this->sortBy, $this->sortDirection);
    }
    
    return $query;
};

$tableQuotations = computed(function () {
    return $this->getBaseQuery()->paginate($this->perPage);
});

$deleteQuotation = function ($id) {
    abort_unless(auth()->user()->can('sales.quotation.delete') || auth()->user()->hasRole('Super Admin'), 403);
    $quotation = Quotation::find($id);
    if ($quotation) {
        $quotation->items()->delete();
        $quotation->delete();
        \Flux::toast('Penawaran berhasil dihapus.', variant: 'success');
    }
};


$setViewMode = function ($mode) {
    $this->viewMode = $mode;
    session(['quotation_view_mode' => $mode]);
};

$kanbanQuotations = computed(function () {
    $results = Quotation::with(['customer', 'creator', 'items'])
        ->orderBy('created_at', 'desc')
        ->get();
    
    $grouped = [];
    foreach ($this->columns as $key => $col) {
        $grouped[$key] = [];
    }
    
    foreach ($results as $quote) {
        if (isset($grouped[$quote->status])) {
            $grouped[$quote->status][] = $quote;
        }
    }
    
    return collect($grouped);
});
?>

<div class="w-full bg-transparent relative">
    
      <div wire:key="view-kanban-wrapper" class="w-full pb-4 overflow-x-auto hide-scroll flex gap-4 {{ $this->viewMode === 'kanban' ? 'flex' : 'hidden' }}">
                <x-kanban.board componentId="quotations" searchModel="search" searchPlaceholder="Cari SQ atau Pelanggan...">
            <x-slot:actions>
                <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0 bg-white dark:bg-zinc-900">
                    <button type="button" wire:key="kanban-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                        <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                    </button>
                    <button type="button" wire:key="kanban-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                        <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                        <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                    </button>
                </div>
                @can('sales.quotation.create')
                    <flux:button variant="primary" icon="plus" href="{{ route('sales.quotations.create') }}" wire:navigate class="shrink-0">Buat Penawaran</flux:button>
                @endcan
            </x-slot:actions>
            @foreach($this->columns as $statusKey => $column)
                <x-kanban.column :statusKey="$statusKey" :column="$column" componentId="quotations" :count="count($this->kanbanQuotations->get($statusKey, []))">
                    @forelse($this->kanbanQuotations->get($statusKey, []) as $quote)
                        <div wire:key="kanban-quote-{{ $quote->id }}"
                             class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 rounded-xl p-3 shadow-sm hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group select-none relative"
                             wire:click="$dispatch('show-quotation-detail', { id: {{ $quote->id }} })" style="cursor: pointer;">
                            <div class="flex justify-between items-center relative z-10">
                                <span class="font-mono text-[10px] font-bold text-zinc-600 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 px-1 py-px rounded w-max">
                                    {{ $quote->quotation_number }}
                                </span>
                            </div>
                            <div class="mt-2 mb-2 relative z-10">
                                <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 leading-tight group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors">
                                    {{ $quote->customer->name ?? $quote->customer_name ?? '-' }}
                                </h4>
                                @php
                                    $kanbanPhone = $quote->customer->phone ?? $quote->customer_phone ?? null;
                                @endphp
                                @if($kanbanPhone)
                                <div class="flex items-center gap-1 mt-1 text-[11px] text-zinc-500">
                                    <flux:icon.phone class="w-3 h-3" />
                                    <span>{{ $kanbanPhone }}</span>
                                </div>
                                @endif
                            </div>
                              <div class="flex items-center justify-between mt-2 pt-2 border-t border-zinc-100 dark:border-zinc-800/80 relative z-10">
                                  <span class="text-[10px] font-medium text-zinc-500">{{ \Carbon\Carbon::parse($quote->quotation_date)->format('d M Y') }}</span>
                                  <div class="flex flex-col items-end">
                                      <span class="text-[10px] font-medium text-zinc-500 mb-0.5">{{ $quote->items->count() }} Varian</span>
                                      <span class="text-xs font-black text-amber-600 dark:text-amber-500">Rp {{ number_format($quote->total_amount, 0, ',', '.') }}</span>
                                  </div>
                              </div>
                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                </x-kanban.column>
            @endforeach
        </x-kanban.board>
    </div>
        <div wire:key="view-table-wrapper" class="w-full {{ $this->viewMode === 'table' ? 'block' : 'hidden' }}">
        <x-table.header searchModel="search" searchPlaceholder="Cari SQ atau Pelanggan...">
            <div class="flex border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden shrink-0 bg-white dark:bg-zinc-900">
                <button type="button" wire:key="table-sw-kanban" @click="$wire.setViewMode('kanban')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'kanban' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Kanban">
                    <flux:icon.view-columns wire:loading.remove wire:target="setViewMode('kanban')" class="w-4 h-4" />
                    <flux:icon.arrow-path wire:loading wire:target="setViewMode('kanban')" class="w-4 h-4 animate-spin" />
                </button>
                <button type="button" wire:key="table-sw-table" @click="$wire.setViewMode('table')" class="p-1.5 px-3 transition-colors {{ $this->viewMode === 'table' ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}" title="Tampilan Tabel">
                    <flux:icon.table-cells wire:loading.remove wire:target="setViewMode('table')" class="w-4 h-4" />
                    <flux:icon.arrow-path wire:loading wire:target="setViewMode('table')" class="w-4 h-4 animate-spin" />
                </button>
            </div>
            @can('sales.quotation.create')
                <flux:button variant="primary" icon="plus" href="{{ route('sales.quotations.create') }}" wire:navigate class="shrink-0">Buat Penawaran</flux:button>
            @endcan
        </x-table.header>
        
            
        <x-table.wrapper>
            <flux:table class="table-mobile-cards">
                <flux:table.columns>
                    <flux:table.column class="w-12">
                        <div class="pl-2 sm:pl-4 text-zinc-500 text-center">No</div>
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$this->sortBy === 'created_at'" :direction="$this->sortDirection" wire:click="sort('created_at')">
                        <div class="pl-2 sm:pl-4">No. SQ & Tanggal</div>
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$this->sortBy === 'customer'" :direction="$this->sortDirection" wire:click="sort('customer')">Pelanggan</flux:table.column>
                    <flux:table.column sortable :sorted="$this->sortBy === 'total_amount'" :direction="$this->sortDirection" wire:click="sort('total_amount')">Total Penawaran</flux:table.column>
                    <flux:table.column sortable :sorted="$this->sortBy === 'status'" :direction="$this->sortDirection" wire:click="sort('status')">Status</flux:table.column>
                    <flux:table.column>Aksi</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($this->tableQuotations as $index => $quotation)
                        <flux:table.row wire:key="table-quotation-{{ $quotation->id }}" 
                                        class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <flux:table.cell class="whitespace-nowrap">
                                <div class="pl-2 sm:pl-4 text-center font-medium text-zinc-500">
                                    {{ $this->tableQuotations->firstItem() + $index }}
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="pl-2 sm:pl-4">
                                    <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100"><a href="#" wire:click.prevent="$dispatch('show-quotation-detail', { id: {{ $quotation->id }} })" class="hover:underline">{{ $quotation->quotation_number }}</a></div>
                                    <div class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($quotation->created_at)->format('d M Y') }}</div>
                                </div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    @php
                                        $custName = $quotation->customer->name ?? $quotation->customer_name ?? 'Umum';
                                        $custPhone = $quotation->customer->phone ?? $quotation->customer_phone ?? null;
                                        $custImage = $quotation->customer->image ?? null;
                                    @endphp
                                    <flux:avatar src="{{ $custImage ? Storage::url($custImage) : '' }}" fallback="{{ substr($custName, 0, 2) }}" size="sm" />
                                    <div>
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $custName }}</div>
                                        @if($custPhone)
                                        <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-0.5">
                                            <flux:icon.phone class="w-3 h-3" />
                                            <span>{{ $custPhone }}</span>
                                        </div>
                                        @endif
                                        <div class="text-[11px] text-zinc-500 flex items-center gap-1 mt-1">
                                            <flux:avatar src="{{ $quotation->creator->avatar ? Storage::url($quotation->creator->avatar) : '' }}" fallback="{{ substr($quotation->creator->name ?? 'S', 0, 2) }}" class="w-3.5 h-3.5 text-[8px]" />
                                            <span>{{ explode(' ', $quotation->creator->name ?? '-')[0] }}</span>
                                        </div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($quotation->total_amount, 0, ',', '.') }}</div>
                                <div class="text-[10px] text-zinc-500">{{ $quotation->items->sum('qty') }} Item</div>
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                @if($quotation->status === 'draft')
                                    <flux:badge size="sm" color="zinc">Draft</flux:badge>
                                @elseif($quotation->status === 'sent')
                                    <flux:badge size="sm" color="blue">Terkirim</flux:badge>
                                @elseif($quotation->status === 'accepted')
                                    <flux:badge size="sm" color="emerald">Diterima</flux:badge>
                                @elseif($quotation->status === 'rejected')
                                    <flux:badge size="sm" color="red">Ditolak</flux:badge>
                                @elseif($quotation->status === 'converted')
                                    <flux:badge size="sm" color="purple" icon="check-badge">Jadi SO</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ ucfirst($quotation->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="subtle" icon="eye" wire:click="$dispatch('show-quotation-detail', { id: {{ $quotation->id }} })" title="Lihat Detail"></flux:button>
                                    @can('sales.quotation.delete')
                                        <flux:modal.trigger name="delete-quotation-{{ $quotation->id }}">
                                            <flux:button size="sm" variant="subtle" icon="trash" class="text-red-500 hover:text-red-700"></flux:button>
                                        </flux:modal.trigger>
                                        <flux:modal name="delete-quotation-{{ $quotation->id }}" class="min-w-[22rem]">
                                            <div class="space-y-6">
                                                <div>
                                                    <flux:heading size="lg">Hapus Penawaran?</flux:heading>
                                                    <flux:subheading>Tindakan ini tidak dapat dibatalkan. Apakah Anda yakin ingin menghapus <b>{{ $quotation->quotation_number }}</b>?</flux:subheading>
                                                </div>
                                                <div class="flex gap-2">
                                                    <flux:spacer />
                                                    <flux:modal.close>
                                                        <flux:button variant="ghost">Batal</flux:button>
                                                    </flux:modal.close>
                                                    <flux:button variant="danger" wire:click="deleteQuotation({{ $quotation->id }})">Hapus</flux:button>
                                                </div>
                                            </div>
                                        </flux:modal>
                                    @endcan
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500 py-8">
                                Belum ada data penawaran.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            
            <div class="mt-4">
                {{ $this->tableQuotations->links() }}
            </div>
        </x-table.wrapper>
    </div>
    <livewire:quotation.detail-modal />

    @if(session('new_quotation_number'))
        <div x-data x-init="setTimeout(() => { $flux.modal('new-quotation-success-modal')?.show() }, 500)">
            <flux:modal name="new-quotation-success-modal" class="min-w-[22rem]">
                <div class="p-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 mb-4">
                        <flux:icon.check-circle class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <flux:heading size="xl">Berhasil!</flux:heading>
                    <flux:subheading class="mt-2 text-sm">
                        Penawaran baru berhasil dibuat:
                    </flux:subheading>
                    <div class="mt-3 mb-6 bg-zinc-50 dark:bg-zinc-800/50 py-3 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-wider">{{ session('new_quotation_number') }}</span>
                    </div>
                    
                    <div class="mt-4 flex flex-col gap-2">
                        @if(session('new_quotation_id'))
                            <flux:button variant="primary" class="w-full" icon="printer" href="{{ route('sales.quotations.print', session('new_quotation_id')) }}" target="_blank">Cetak Penawaran</flux:button>
                        @endif
                        <flux:button variant="ghost" class="w-full" @click="$flux.modal('new-quotation-success-modal').close()">Tutup</flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    @endif
</div>
