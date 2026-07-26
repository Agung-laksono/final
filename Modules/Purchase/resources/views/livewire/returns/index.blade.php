<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Purchase\Models\PurchaseReturn;

new class extends Component {
    use WithPagination;

    public $search = '';

    public function with(): array
    {
        return [
            'returns' => PurchaseReturn::with(['purchaseOrder', 'vendor'])
                ->where('return_number', 'like', '%' . $this->search . '%')
                ->orWhereHas('vendor', function($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ];
    }
};
?>

<div>
    <x-table.header searchModel="search" searchPlaceholder="Cari nomor retur atau supplier...">
        <flux:button href="{{ route('purchase.returns.create') }}" wire:navigate variant="primary" icon="plus" class="shrink-0">Buat Retur Ke Supplier</flux:button>
    </x-table.header>

    <x-table.wrapper>
        <flux:table class="table-mobile-cards">
            <flux:table.columns>
                <flux:table.column>No. Retur & Tanggal</flux:table.column>
                <flux:table.column>Vendor & No. Pembelian</flux:table.column>
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
                                    {{ strtoupper(substr($ret->vendor->name ?? '?', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-medium text-sm text-slate-900 dark:text-slate-100 line-clamp-1">{{ $ret->vendor->name ?? '-' }}</div>
                                    <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                        <flux:icon.document-text class="w-3 h-3" />
                                        <span>PO: {{ $ret->purchaseOrder->po_number ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="card-status-overlay">
                            <flux:badge color="{{ match($ret->status) {
                                'pending' => 'warning',
                                'shipped' => 'info',
                                'completed' => 'success',
                                'refunded' => 'success',
                                default => 'zinc'
                            } }}" size="sm">
                                {{ ucfirst($ret->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center justify-end gap-2 pr-2 sm:pr-4">
                                <flux:button size="sm" variant="subtle" icon="eye" class="h-8 p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/50" href="{{ route('purchase.returns.show', $ret->id) }}" wire:navigate />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <div class="flex flex-col items-center justify-center py-12 text-slate-500">
                                <flux:icon.inbox class="w-12 h-12 mb-3 text-slate-300" />
                                <p>Belum ada data retur pembelian.</p>
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
