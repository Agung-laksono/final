<?php

use function Livewire\Volt\{state, on};
use Modules\Inventory\Models\Item;

state([
    'show' => fn () => request()->routeIs('inventory-settings'),
    'items' => fn () => Item::with(['category', 'unit', 'type'])->latest()->get(),
]);

$delete = function (Item $item) {
    // Bersihkan file gambar dari storage sebelum datanya dihapus dari database
    if ($item->image) {
        \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image);
    }
    
    $item->delete();
    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
    $this->dispatch('item-deleted');
    
    // Beritahu user lain secara realtime via Reverb
    \App\Events\InventoryUpdated::safeDispatch("Data barang {$item->code} berhasil dihapus");
};

// Listen to the universal event emitted by the global modal
on(['item-saved' => function () {
    $this->items = Item::with(['category', 'unit', 'type'])->latest()->get();
}]);

$openModal = function ($id = null) {
    $this->dispatch('open-item-modal', $id);
};

?>

<div> @if ($show)
    <div x-on:trigger-add-subcategory.window="$wire.dispatch('open-subcategory-modal', { category_id: $wire.category_id })"></div>

    <div class="flex justify-between items-center mb-6">
        <flux:heading size="lg">Pengelolaan Barang</flux:heading>
        @can('inventory.item.create')
            <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Barang Baru</flux:button>
        @endcan
    </div>

    {{-- Tabel Daftar Barang --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden mb-6">
        <flux:table class="pl-5">
            <flux:table.columns>
                <flux:table.column>Info Barang</flux:table.column>
                <flux:table.column>Klasifikasi</flux:table.column>
                <flux:table.column>Batas Stok</flux:table.column>
                <flux:table.column>Harga Dasar</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($items as $i)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                @if($i->image)
                                    <img src="{{ asset('storage/' . $i->image) }}" class="w-auto h-10 rounded-lg  ring-1 ring-zinc-200 dark:ring-zinc-700">
                                @else
                                    <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center text-zinc-400">
                                        <flux:icon.photo class="w-5 h-5" />
                                    </div>
                                @endif
                                <div class="flex flex-col">
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $i->name }}</span>
                                    <span class="text-[11px] font-mono text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded-md mt-1 w-fit">{{ $i->code }}</span>
                                </div>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex flex-col gap-1.5">
                                <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $i->category?->name ?? '-' }}
                                </span>
                                <div class="flex gap-1">
                                    <flux:badge size="sm" color="zinc" variant="outline">{{ $i->type?->name ?? '-' }}</flux:badge>
                                    <flux:badge size="sm" color="zinc">{{ $i->unit?->name ?? '-' }}</flux:badge>
                                </div>
                            </div>
                        </flux:table.cell>
                        
                        <flux:table.cell>
                            <div class="flex items-center gap-1.5">
                                <div class="flex flex-col items-center justify-center bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 px-2 py-1 rounded border border-rose-100 dark:border-rose-500/20 min-w-[40px]">
                                    <span class="text-[9px] font-bold uppercase tracking-widest opacity-80 mb-0.5">Min</span>
                                    <span class="text-xs font-bold">{{ $i->min_stock }}</span>
                                </div>
                                <span class="text-zinc-300 dark:text-zinc-600">-</span>
                                <div class="flex flex-col items-center justify-center bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 px-2 py-1 rounded border border-blue-100 dark:border-blue-500/20 min-w-[40px]">
                                    <span class="text-[9px] font-bold uppercase tracking-widest opacity-80 mb-0.5">Max</span>
                                    <span class="text-xs font-bold">{{ $i->max_stock }}</span>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col gap-1">
                                <span class="text-[11px] text-zinc-500">Beli: Rp {{ number_format($i->purchase_price, 0, ',', '.') }}</span>
                                <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($i->selling_price, 0, ',', '.') }}</span>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex flex-col gap-1.5 items-start">
                                @if($i->is_active)
                                    <flux:badge color="green" size="sm" icon="check-circle">Aktif</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm" icon="x-circle">Non-aktif</flux:badge>
                                @endif
                                
                                @if($i->requires_label)
                                    <flux:badge color="orange" size="sm" icon="qr-code">SN</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>
                            <div class="flex gap-1">
                                @can('inventory.item.update')
                                    <flux:button wire:click="openModal({{ $i->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-600" />
                                @endcan
                                @can('inventory.item.delete')
                                    <flux:button wire:click="delete({{ $i->id }})" wire:confirm="Yakin menghapus barang {{ $i->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-600" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="text-center py-10 text-zinc-500">
                                <flux:icon.cube class="w-12 h-12 mx-auto mb-3 opacity-20" />
                                <p>Belum ada barang yang ditambahkan.</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    @endif
    
    <livewire:global.item-form-modal />
</div>
