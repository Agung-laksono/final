<?php

use function Livewire\Volt\{state, rules, on};
use Modules\Inventory\Models\Category;
use Flux\Flux;

state([
    'category_id' => null,
    'name' => '',
    'categories' => fn () => Category::withCount('items')->latest()->get(),
    'show' => fn () => request()->routeIs('inventory-settings') || request()->is('inventory-settings')
]);

rules(fn () => [
    'name' => 'required|string|max:255|unique:categories,name,' . $this->category_id,
]);

$openModal = function ($id = null) {
    $this->resetValidation();
    if ($id) {
        $category = Category::findOrFail($id);
        $this->category_id = $category->id;
        $this->name = $category->name;
    } else {
        $this->category_id = null;
        $this->name = '';
    }
    Flux::modal('category-modal')->show();
};

on(['open-category-modal' => function ($id = null) {
    $this->openModal($id);
}]);

$save = function () {
    $this->validate();
    if ($this->category_id) {
        $savedCategory = Category::findOrFail($this->category_id);
        $savedCategory->update(['name' => $this->name]);
    } else {
        $savedCategory = Category::create(['name' => $this->name]);
    }
    $this->categories = Category::withCount('items')->latest()->get();
    $this->dispatch('category-updated', id: $savedCategory->id, options: Category::orderBy('name')->get());
    Flux::modal('category-modal')->close();
};

$delete = function (Category $category) {
    $category->delete();
    $this->categories = Category::withCount('items')->latest()->get();
    $this->dispatch('category-updated');
};
?>

<div x-on:open-category-modal.window="$wire.openModal()" >
    @if ($show)
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="lg">Pengelolaan Kategori</flux:heading>
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Kategori</flux:button>
    </div>
    <div class="mt-4">
        <div class="space-y-2">
            @forelse($categories as $category)
                <div class="flex items-center justify-between p-4 bg-white/50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl backdrop-blur-sm group hover:border-blue-500/50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $category->name }}</span>
                        <flux:badge size="sm" variant="pill" color="blue" class="font-mono">{{ $category->items_count }} Barang</flux:badge>
                    </div>
                    <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <flux:button wire:click="openModal({{ $category->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-700" />
                        <flux:button wire:click="delete({{ $category->id }})" wire:confirm="Yakin menghapus kategori {{ $category->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                    </div>
                </div>
            @empty
                <div class="text-sm text-zinc-500 text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                    Belum ada kategori yang ditambahkan.
                </div>
            @endforelse
        </div>
    </div>
    @endif

    <flux:modal name="category-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $category_id ? 'Edit Kategori' : 'Tambah Kategori Baru' }}</flux:heading>
        </div>
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" label="Nama Kategori" placeholder="Contoh: Elektronik, Pakaian" required />
            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $category_id ? 'Simpan Perubahan' : 'Tambahkan' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
