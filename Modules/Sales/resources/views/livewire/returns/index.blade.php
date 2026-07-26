<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Sales\Models\SalesReturn;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function with(): array
    {
        return [
            'returns' => SalesReturn::with(['salesOrder', 'customer'])
                ->where('return_number', 'like', '%' . $this->search . '%')
                ->orWhereHas('customer', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div>
    <x-table.header searchModel="search" searchPlaceholder="Cari nomor retur atau pelanggan...">
        <flux:button href="{{ route('sales.returns.create') }}" wire:navigate variant="primary" icon="plus" class="shrink-0">Buat Retur Baru</flux:button>
    </x-table.header>

    <x-table.wrapper>
        <flux:table class="table-mobile-cards">
            <flux:table.columns>
                <flux:table.column>No. Retur & Tanggal</flux:table.column>
                <flux:table.column>Customer & No. Pesanan</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($returns as $ret)
                    <flux:table.row :key="$ret->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <div class="font-medium text-sm text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ $ret->return_number }}</div>
                                <div class="text-xs text-slate-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($ret->return_date)->format('d M Y') }}</div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($ret->customer->name ?? '?', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-medium text-sm text-slate-900 dark:text-slate-100 line-clamp-1">{{ $ret->customer->name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                        <flux:icon.document-text class="w-3 h-3" />
                                        <span>SO: {{ $ret->salesOrder->so_number ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="card-status-overlay">
                            <flux:badge color="{{ match($ret->status) {
                                'pending' => 'warning',
                                'approved' => 'info',
                                'completed' => 'success',
                                'rejected' => 'danger',
                                default => 'zinc'
                            } }}" size="sm">
                                {{ ucfirst($ret->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                <flux:button size="sm" variant="subtle" icon="eye" class="h-8 p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" href="{{ route('sales.returns.show', $ret->id) }}" wire:navigate />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                                <flux:icon.inbox class="w-12 h-12 mb-3 text-slate-300" />
                                <p>Belum ada data retur penjualan.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        
        @if($returns->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $returns->links() }}
            </div>
        @endif
    </x-table.wrapper>
</div>
