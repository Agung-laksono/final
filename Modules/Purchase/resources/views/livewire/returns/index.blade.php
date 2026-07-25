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
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-200">Retur Pembelian (RTV)</h1>
        <flux:button href="{{ route('purchase.returns.create') }}" variant="primary" icon="plus">Buat Retur Ke Supplier</flux:button>
    </div>

    <flux:card>
        <div class="mb-4">
            <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Cari nomor retur atau supplier..." />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>No. Retur</flux:table.column>
                <flux:table.column>Tgl Retur</flux:table.column>
                <flux:table.column>Vendor/Supplier</flux:table.column>
                <flux:table.column>No. Pembelian (PO)</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($returns as $ret)
                    <flux:table.row>
                        <flux:table.cell>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $ret->return_number }}</span>
                        </flux:table.cell>
                        <flux:table.cell>{{ \Carbon\Carbon::parse($ret->return_date)->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $ret->vendor->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>{{ $ret->purchaseOrder->po_number ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
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
                            <flux:button size="sm" variant="ghost" icon="eye" href="{{ route('purchase.returns.show', $ret->id) }}" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-slate-500 py-4">Belum ada data retur pembelian.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        
        <div class="mt-4">
            {{ $returns->links() }}
        </div>
    </flux:card>
</div>
