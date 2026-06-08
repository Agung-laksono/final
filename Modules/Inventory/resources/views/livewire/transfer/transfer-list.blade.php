<?php

use function Livewire\Volt\{state, layout, title, with, usesPagination, on};
use Modules\Inventory\Models\StockTransfer;
use Flux\Flux;

usesPagination(theme: 'tailwind');

layout('layouts.app');
title('Transfer Barang');

state(['search' => '']);

$delete = function (StockTransfer $transfer) {
    if ($transfer->status !== 'pending') {
        Flux::toast('Hanya transfer berstatus pending yang bisa dibatalkan/dihapus.', variant: 'danger');
        return;
    }
    $transfer->delete();
    Flux::toast('Data transfer berhasil dihapus.');
};

on([
    'transfer-saved' => function () {},
    'echo:inventory,InventoryUpdated' => function ($event) {
        // Triggered via WebSockets when another user (or tab) saves a transfer
        \Flux\Flux::toast('Riwayat transfer diperbarui dari server.', variant: 'success');
    }
]);

with(fn () => [
    'transfers' => StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item'])
        ->when($this->search, function ($query) {
            $query->where('reference_number', 'like', '%' . $this->search . '%');
        })
        ->latest()
        ->paginate(10)
]);

?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Transfer Antar Gudang') }}</flux:heading>
            <flux:subheading>{{ __('Kelola perpindahan stok antar gudang.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="create-transfer-modal">
            <flux:button variant="primary" icon="plus">
                {{ __('Transfer Baru') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari No. Referensi..." class="max-w-md" />
    </div>

    <div class="bg-white border dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Tanggal') }}</flux:table.column>
                    <flux:table.column>{{ __('No. Ref') }}</flux:table.column>
                    <flux:table.column>{{ __('Dari Gudang') }}</flux:table.column>
                    <flux:table.column>{{ __('Ke Gudang') }}</flux:table.column>
                    <flux:table.column>{{ __('Total Barang') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Aksi') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($transfers as $transfer)
                        <flux:table.row :key="$transfer->id">
                            <flux:table.cell>{{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $transfer->reference_number }}</flux:table.cell>
                            <flux:table.cell>{{ $transfer->fromWarehouse->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $transfer->toWarehouse->name ?? '-' }}</flux:table.cell>
                            <flux:table.cell>{{ $transfer->items->count() }} Jenis</flux:table.cell>
                            <flux:table.cell>
                                @if($transfer->status === 'completed')
                                    <flux:badge color="success" size="sm">{{ __('Selesai') }}</flux:badge>
                                @elseif($transfer->status === 'pending')
                                    <flux:badge color="warning" size="sm">{{ __('Pending') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ ucfirst($transfer->status) }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        @if($transfer->status === 'pending')
                                            <flux:menu.item wire:click="delete({{ $transfer->id }})" wire:confirm="Yakin ingin membatalkan transfer ini?" icon="trash" variant="danger">
                                                {{ __('Batal & Hapus') }}
                                            </flux:menu.item>
                                        @else
                                            <flux:menu.item disabled icon="eye">{{ __('Lihat Detail') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">
                                {{ __('Belum ada data transfer.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        @if ($transfers->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Form Transfer -->
    <flux:modal name="create-transfer-modal" class="md:w-[90vw] lg:w-[70vw] max-w-7xl" style="max-width: 95vw;" scroll="body">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Buat Transfer Baru') }}</flux:heading>
                <flux:subheading>{{ __('Pindahkan stok dari satu gudang ke gudang lain.') }}</flux:subheading>
            </div>
            
            <livewire:transfer.transfer-form />
            
        </div>
    </flux:modal>
</div>
