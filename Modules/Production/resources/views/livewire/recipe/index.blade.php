<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Production\Models\ProductionRecipe;
use Modules\Inventory\Models\Item;

layout('layouts.app');
title('Resep Produksi');

state([
    'search' => '',
]);

$recipes = computed(function () {
    return ProductionRecipe::with(['item', 'items.item'])
        ->when($this->search, function ($query) {
            $query->whereHas('item', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        })
        ->latest()
        ->get();
});

$deleteRecipe = function ($id) {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $recipe = ProductionRecipe::find($id);
    if ($recipe) {
        $recipe->items()->delete(); // Delete BOM items
        $recipe->delete(); // Delete Recipe
        \Flux::toast('Resep berhasil dihapus.', variant: 'success');
    }
};

$toggleActive = function ($id) {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $recipe = ProductionRecipe::find($id);
    if ($recipe) {
        $recipe->is_active = !$recipe->is_active;
        $recipe->save();
        \Flux::toast('Status resep diperbarui.', variant: 'success');
    }
};

on(['recipe-saved' => function () {
    // Refresh computed property
}]);

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Kelola Resep Produksi (BOM)</flux:heading>
            <flux:subheading>Tentukan bahan baku penyusun untuk setiap produk rakitan/bundling.</flux:subheading>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari Resep..." class="w-full md:w-64" />
            <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-recipe-modal')"> Buat R </flux:button>
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800 text-zinc-500">
                    <tr>
                        <th class="px-6 py-4 font-medium">Produk Jadi</th>
                        <th class="px-6 py-4 font-medium">Komposisi Bahan</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->recipes as $recipe)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $recipe->item->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $recipe->item->code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($recipe->items->take(3) as $bomItem)
                                        <flux:badge size="sm" color="zinc">{{ $bomItem->qty }}x {{ $bomItem->item->name }}</flux:badge>
                                    @endforeach
                                    @if($recipe->items->count() > 3)
                                        <flux:badge size="sm" color="blue">+{{ $recipe->items->count() - 3 }} lainnya</flux:badge>
                                    @endif
                                    @if($recipe->items->count() == 0)
                                        <span class="text-zinc-400 italic">Belum ada bahan</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <flux:switch wire:click="toggleActive({{ $recipe->id }})" :checked="$recipe->is_active" />
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="$dispatch('open-recipe-modal', { recipeId: {{ $recipe->id }} })" tooltip="Edit Resep" />
                                    <flux:button size="sm" variant="subtle" icon="trash" class="text-red-500 hover:text-red-600" wire:confirm="Hapus resep ini secara permanen?" wire:click="deleteRecipe({{ $recipe->id }})" tooltip="Hapus" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-zinc-500">
                                <flux:icon.document-text class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                                <p class="text-base font-medium text-zinc-900 dark:text-zinc-100">Belum ada resep</p>
                                <p class="mt-1">Buat resep baru untuk menentukan komposisi perakitan produk.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <livewire:recipe.form-modal />
    <livewire:global.item-gallery-modal context="inventory" />
</div>
