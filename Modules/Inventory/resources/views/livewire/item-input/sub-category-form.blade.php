<?php

use function Livewire\Volt\{state, rules, on};
use Modules\Inventory\Models\SubCategory;
use Modules\Inventory\Models\Category;
use Flux\Flux;

state([
    'subcategory_id' => null,
    'category_id' => '',
    'name' => '',
    'subcategories' => fn () => SubCategory::with('category')->latest()->get(),
    'categories' => fn () => Category::orderBy('name')->get(),
    'show' => fn () => request()->routeIs('inventory') || request()->is('inventory')
]);

rules([
    'category_id' => 'required|exists:categories,id',
    'name' => 'required|string|max:255',
]);

$openModal = function ($id = null) {
    $this->resetValidation();
    // Refresh kategori tiap kali modal dibuka agar mendapat data terbaru dari tabel kategori
    $this->categories = Category::orderBy('name')->get();
    
    if ($id) {
        $sub = SubCategory::findOrFail($id);
        $this->subcategory_id = $sub->id;
        $this->category_id = $sub->category_id;
        $this->name = $sub->name;
    } else {
        $this->subcategory_id = null;
        $this->category_id = '';
        $this->name = '';
    }
    Flux::modal('subcategory-modal')->show();
};

on(['open-subcategory-modal' => function ($id = null) {
    $this->openModal($id);
}]);

// Dengarkan juga event update dari category agar list dropdown selalu terupdate otomatis
on(['category-updated' => function () {
    $this->categories = Category::orderBy('name')->get();
}]);

$save = function () {
    $this->validate();
    if ($this->subcategory_id) {
        $savedSub = SubCategory::findOrFail($this->subcategory_id);
        $savedSub->update([
            'category_id' => $this->category_id,
            'name' => $this->name,
        ]);
    } else {
        $savedSub = SubCategory::create([
            'category_id' => $this->category_id,
            'name' => $this->name,
        ]);
    }
    $this->subcategories = SubCategory::with('category')->latest()->get();
    $this->dispatch('subcategory-updated', id: $savedSub->id);
    Flux::modal('subcategory-modal')->close();
};

$delete = function (SubCategory $subCategory) {
    $subCategory->delete();
    $this->subcategories = SubCategory::with('category')->latest()->get();
    $this->dispatch('subcategory-updated');
};
?>

<div>
    <div class="flex justify-between items-center mb-6">
        @if ($show)
        <flux:heading size="lg">Pengelolaan Sub Kategori</flux:heading>
        @endif
        <flux:button wire:click="openModal" variant="primary" icon="plus">Tambah Sub Kategori</flux:button>
    </div>

    @if ($show)
    <div class="mt-4">
        <div class="space-y-2">
            @forelse($subcategories as $sub)
                <div class="flex items-center justify-between p-4 bg-white/50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl backdrop-blur-sm">
                    <div class="flex flex-col">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $sub->name }}</span>
                        <span class="text-sm text-zinc-500">Kategori Induk: <flux:badge size="sm" color="zinc">{{ optional($sub->category)->name ?? 'Tidak Ada' }}</flux:badge></span>
                    </div>
                    <div class="flex gap-2">
                        <flux:button wire:click="openModal({{ $sub->id }})" variant="ghost" size="sm" icon="pencil" class="text-blue-500 hover:text-blue-700" />
                        <flux:button wire:click="delete({{ $sub->id }})" wire:confirm="Yakin menghapus sub kategori {{ $sub->name }}?" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                    </div>
                </div>
            @empty
                <div class="text-sm text-zinc-500 text-center p-6 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl">
                    Belum ada sub kategori yang ditambahkan.
                </div>
            @endforelse
        </div>
    </div>
    @endif

    <flux:modal name="subcategory-modal" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ $subcategory_id ? 'Edit Sub Kategori' : 'Tambah Sub Kategori Baru' }}</flux:heading>
        </div>
        <form wire:submit="save" class="space-y-4">
            <flux:select wire:model="category_id" label="Kategori Induk" placeholder="Pilih Kategori..." required>
                @foreach($categories as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <flux:input wire:model="name" label="Nama Sub Kategori" placeholder="Contoh: Laptop, Mouse" required />
            <div class="flex justify-end gap-2 mt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $subcategory_id ? 'Simpan Perubahan' : 'Tambahkan' }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
