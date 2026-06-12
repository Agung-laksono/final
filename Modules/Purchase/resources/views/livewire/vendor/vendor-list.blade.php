<?php
use function Livewire\Volt\{state, computed, on, usesPagination};
use Modules\Purchase\Models\Vendor;

usesPagination();

$vendors = computed(function () {
    return Vendor::latest()->paginate(10);
});

on(['vendor-saved' => function () {
    $this->resetPage();
}]);

$delete = function ($id) {
    abort_unless(auth()->user()->can('purchase.delete'), 403, 'Tidak ada akses menghapus vendor.');
    Vendor::find($id)?->delete();
};
?>

<div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
    <flux:table class="pl-3">
        <flux:table.columns>
            <flux:table.column>Nama Vendor</flux:table.column>
            <flux:table.column>Telepon</flux:table.column>
            <flux:table.column>Wilayah</flux:table.column>
            <flux:table.column>Tipe</flux:table.column>
            <flux:table.column>Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->vendors as $vendor)
                <flux:table.row>
                    <flux:table.cell class="font-medium">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="{{ $vendor->image ? Storage::url($vendor->image) : '' }}" fallback="{{ substr($vendor->name, 0, 2) }}" />
                            {{ $vendor->name }}
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $vendor->phone ?? '-' }}</flux:table.cell>
                    <flux:table.cell>{{ $vendor->city ?? '-' }}, {{ $vendor->province ?? '-' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match($vendor->type) {
                            'Supplier' => 'blue',
                            'Pengrajin' => 'amber',
                            'Ekspedisi' => 'green',
                            default => 'zinc',
                        }">
                            {{ $vendor->type }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @can('purchase.update')
                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="$dispatch('edit-vendor', { id: {{ $vendor->id }} })" />
                        @endcan
                        @can('purchase.delete')
                            <flux:button variant="ghost" size="sm" icon="trash" class="text-red-500!" wire:click="delete({{ $vendor->id }})" wire:confirm="Yakin ingin menghapus vendor ini?" />
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center py-8 text-zinc-400">
                        Belum ada data vendor. Silakan tambah vendor baru.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800">
        {{ $this->vendors->links() }}
    </div>
</div>
