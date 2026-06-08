<?php

use function Livewire\Volt\{state, on};
use Modules\Inventory\Models\StockTransfer;
use Flux\Flux;

state([
    'transfer' => null,
]);

$openModal = function ($id) {
    $this->transfer = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item.category', 'items.item.unit', 'user'])->findOrFail($id);
    Flux::modal('transfer-detail-modal')->show();
};

$deleteTransfer = function () {
    \Illuminate\Support\Facades\Gate::authorize('inventory.transfer.delete');
    
    if ($this->transfer->status !== 'pending') {
        Flux::toast('Hanya transfer berstatus pending yang bisa dibatalkan/dihapus.', variant: 'danger');
        return;
    }
    
    $this->transfer->delete();
    Flux::toast('Data transfer berhasil dihapus.', variant: 'success');
    Flux::modal('transfer-detail-modal')->close();
    $this->dispatch('transfer-deleted');
};

on(['open-transfer-detail' => function ($id) {
    $this->openModal($id);
}]);

$printTransfer = function () {
    Flux::toast('Fitur cetak surat jalan sedang dalam pengembangan.', variant: 'warning');
};

?>

<flux:modal name="transfer-detail-modal" class="md:w-[90vw] lg:w-[60vw] max-w-5xl" style="max-width: 95vw;" scroll="body">
    @if($transfer)
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <div>
                    <flux:heading size="lg">Detail Transfer Barang</flux:heading>
                    <flux:subheading>{{ $transfer->reference_number }}</flux:subheading>
                </div>
                <div class="mr-5">
                    @if($transfer->status === 'completed')
                        <flux:badge color="success" size="lg">Selesai</flux:badge>
                    @elseif($transfer->status === 'pending')
                        <flux:badge color="warning" size="lg">Pending</flux:badge>
                    @else
                        <flux:badge color="zinc" size="lg">{{ ucfirst($transfer->status) }}</flux:badge>
                    @endif
                </div>
            </div>

            <!-- Informasi Utama -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-3">
                <!-- Gudang & Tanggal -->
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs text-zinc-500 font-medium uppercase tracking-wider mb-1">Gudang Asal</div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                <flux:icon.building-storefront class="w-4 h-4 text-zinc-400" />
                                {{ $transfer->fromWarehouse->name ?? '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-500 font-medium uppercase tracking-wider mb-1">Gudang Tujuan</div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                <flux:icon.building-storefront class="w-4 h-4 text-emerald-500" />
                                {{ $transfer->toWarehouse->name ?? '-' }}
                            </div>
                        </div>
                    </div>
                    
                    <flux:separator variant="subtle" />
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs text-zinc-500 font-medium uppercase tracking-wider mb-1">Tanggal Transfer</div>
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ \Carbon\Carbon::parse($transfer->transfer_date)->format('d F Y') }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-500 font-medium uppercase tracking-wider mb-1">Petugas</div>
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                {{ $transfer->user->name ?? 'Administrator' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Catatan -->
                <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex flex-col">
                    <div class="text-xs text-zinc-500 font-medium uppercase tracking-wider mb-2">Catatan Tambahan</div>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300 italic flex-1 bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-800">
                        {{ $transfer->notes ?: 'Tidak ada catatan untuk transfer ini.' }}
                    </div>
                </div>
            </div>

            <!-- Daftar Barang Transferred -->
            <div>
                <flux:heading size="md" class="mb-3">Daftar Barang yang Dipindahkan</flux:heading>
                <div class="bg-white border dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs uppercase text-zinc-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">Barang</th>
                                <th class="px-4 py-3 font-medium">Kode / SKU</th>
                                <th class="px-4 py-3 font-medium text-center">Kuantitas</th>
                                <th class="px-4 py-3 font-medium">Satuan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($transfer->items as $detail)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $detail->item->name }}</div>
                                        <div class="text-xs text-zinc-500">{{ $detail->item->category?->name ?? 'Tanpa Kategori' }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->item->code }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-md bg-indigo-50 text-indigo-700 font-bold dark:bg-indigo-500/10 dark:text-indigo-400">
                                            {{ $detail->quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $detail->item->unit?->name ?? '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-between items-center pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <div class="flex gap-2">
                    <flux:button wire:click="printTransfer" variant="subtle" icon="printer">Cetak</flux:button>
                    @if($transfer->status === 'pending')
                        @can('inventory.transfer.delete')
                            <flux:button wire:click="deleteTransfer" wire:confirm="Yakin ingin membatalkan dan menghapus transfer ini?" variant="danger" icon="trash">Batal & Hapus</flux:button>
                        @endcan
                    @endif
                </div>
                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" icon="x-mark">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </div>
    @else
        <div class="p-8 text-center text-zinc-500 flex flex-col items-center">
            <flux:icon.arrow-path class="w-8 h-8 animate-spin mb-4" />
            Memuat detail...
        </div>
    @endif
</flux:modal>
