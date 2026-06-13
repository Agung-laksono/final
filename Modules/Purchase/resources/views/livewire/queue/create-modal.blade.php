<?php

use function Livewire\Volt\{state, on, computed};
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Inventory\Models\Item;

state([
    'show' => false,
    'item_id' => '',
    'requested_qty' => 1,
    'notes' => '',
]);

$items = computed(function () {
    return Item::where('is_active', true)->orderBy('name')->get();
});

on(['open-create-queue-modal' => function () {
    $this->reset(['item_id', 'requested_qty', 'notes']);
    $this->requested_qty = 1;
    $this->show = true;
}]);

$selectItem = function ($id) {
    $this->item_id = $id;
};

$save = function () {
    $validated = $this->validate([
        'item_id' => 'required|exists:items,id',
        'requested_qty' => 'required|numeric|min:1',
        'notes' => 'nullable|string',
    ]);

    PurchaseQueue::create([
        'item_id' => $this->item_id,
        'source_type' => 'manual',
        'requested_qty' => $this->requested_qty,
        'notes' => $this->notes,
        'status' => 'pending_approval'
    ]);

    $this->dispatch('status-updated'); // Memanggil event agar kanban ter-refresh
    $this->show = false;
    \Flux::toast('Permintaan pembelian berhasil ditambahkan ke antrean!', 'success');
};

?>

<div>
    <flux:modal wire:model="show" class="md:w-[500px]" wire:ignore.self>
        <div class="border-b border-zinc-200 dark:border-zinc-700 pb-4 mb-5">
            <flux:heading size="lg">Buat Permintaan Pembelian (Manual)</flux:heading>
            <flux:subheading>Isi form ini untuk mengajukan kebutuhan stok barang ke tim pengadaan.</flux:subheading>
        </div>

        <form wire:submit="save" class="space-y-5" @item-selected.window="$wire.selectItem($event.detail.item.item_id); setTimeout(() => { $flux.modal('gallery-modal').close() }, 50)">
            <flux:field>
                <flux:label>Barang (Item) <span class="text-red-500">*</span></flux:label>
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <flux:select wire:model.live="item_id" placeholder="Pilih barang..." searchable>
                            @foreach($this->items as $item)
                                <flux:select.option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code }})</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:button variant="subtle" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading" tooltip="Buka Galeri Barang">
                        <flux:icon.squares-2x2 class="w-5 h-5 text-zinc-500" x-show="!loading" />
                        <svg x-show="loading" class="animate-spin w-5 h-5 text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </flux:button>
                </div>
                <flux:error name="item_id" />
            </flux:field>

            <flux:field>
                <flux:label>Jumlah Permintaan <span class="text-red-500">*</span></flux:label>
                <flux:input type="number" wire:model="requested_qty" min="1" placeholder="Contoh: 50" />
                <flux:error name="requested_qty" />
            </flux:field>

            <flux:field>
                <flux:label>Catatan (Opsional)</flux:label>
                <flux:textarea wire:model="notes" placeholder="Misal: Butuh cepat untuk acara minggu depan..." rows="3"></flux:textarea>
                <flux:error name="notes" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="$set('show', false)">Batal</flux:button>
                <flux:button variant="primary" type="submit">Buat Permintaan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
