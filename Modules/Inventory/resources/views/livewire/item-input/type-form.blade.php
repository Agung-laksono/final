<?php

use function Livewire\Volt\{state, rules, on};
use Modules\Inventory\Models\Type;
use Flux\Flux;

state([
    'type_id' => null,
    'name' => '',
    'description' => '',
    'types' => fn () => Type::latest()->get(),
    'show' => fn () => request()->routeIs('inventory-settings') || request()->is('inventory-settings')
]);

rules(fn () => [
    'name' => 'required|string|max:255|unique:types,name,' . $this->type_id,
    'description' => 'nullable|string',
]);

$openModal = function ($id = null) {
    $this->resetValidation();
    if ($id) {
        $type = Type::findOrFail($id);
        $this->type_id = $type->id;
        $this->name = $type->name;
        $this->description = $type->description;
    } else {
        $this->type_id = null;
        $this->name = '';
        $this->description = '';
    }
    Flux::modal('type-modal')->show();
};

on(['open-type-modal' => function ($id = null) {
    $this->openModal($id);
}]);

$save = function () {
    $this->validate();
    if ($this->type_id) {
        $savedType = Type::findOrFail($this->type_id);
        $savedType->update([
            'name' => $this->name,
            'description' => $this->description,
        ]);
    } else {
        $savedType = Type::create([
            'name' => $this->name,
            'description' => $this->description,
        ]);
    }
    $this->types = Type::latest()->get();
    $this->dispatch('type-updated', id: $savedType->id, options: Type::orderBy('name')->get());
    Flux::modal('type-modal')->close();
};

$delete = function (Type $type) {
    $type->delete();
    $this->types = Type::latest()->get();
    $this->dispatch('type-updated');
};
?>

<div     x-on:open-type-modal.window="$wire.openModal()">
    @if ($show)
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="lg">Pengelolaan Tipe Barang</flux:heading>
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Tipe</flux:button>
    </div>
    <div class="mt-4">
        <div class="space-y-2">
            @forelse($types as $type)
                <div class="flex items-center justify-between p-4 bg-white/50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl backdrop-blur-sm">
                    <div class="flex flex-col">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $type->name }}</span>
                        @if($type->description)
                            <span class="text-sm text-zinc-500">{{ $type->description }}</span>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button wire:click="openModal({{ $type->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-700" />
                        <flux:button wire:click="delete({{ $type->id }})" wire:confirm="Yakin ingin menghapus tipe {{ $type->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                    </div>
                </div>
            @empty
                <div class="text-sm text-zinc-500 text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                    Belum ada data tipe yang ditambahkan.
                </div>
            @endforelse
        </div>
    </div>
    @endif

    <flux:modal name="type-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $type_id ? 'Edit Tipe' : 'Tambah Tipe Baru' }}</flux:heading>
        </div>
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Nama Tipe" placeholder="Contoh: Barang Jadi, Bahan Baku" required />
            <flux:textarea wire:model="description" label="Deskripsi (Opsional)" placeholder="Penjelasan singkat tipe barang" />
            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $type_id ? 'Simpan Perubahan' : 'Tambahkan' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
