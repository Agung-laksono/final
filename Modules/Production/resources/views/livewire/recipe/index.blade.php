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
    <x-table.header searchModel="search" searchPlaceholder="Cari Resep...">
        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-recipe-modal')" class="shrink-0"> Buat R </flux:button>
    </x-table.header>

    <x-table.wrapper>
        <flux:table class="table-mobile-cards">
            <flux:table.columns>
                <flux:table.column>Produk Jadi</flux:table.column>
                <flux:table.column>Komposisi Bahan</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Aksi</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                    @forelse($this->recipes as $recipe)
                        <flux:table.row :key="$recipe->id" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-md bg-zinc-100 overflow-hidden border border-zinc-200 shrink-0">
                                        @if ($recipe->item?->image)
                                            <img src="{{ asset('storage/' . $recipe->item->image) }}" loading="lazy" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                                <flux:icon.photo class="w-5 h-5" />
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 whitespace-nowrap">{{ $recipe->item->name }}</div>
                                        <div class="text-xs text-zinc-500 whitespace-nowrap">{{ $recipe->item->code }}</div>
                                    </div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
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
                            </flux:table.cell>
                            <flux:table.cell class="card-status-overlay">
                                <div class="p-2 sm:p-0">
                                    <flux:switch wire:click="toggleActive({{ $recipe->id }})" :checked="$recipe->is_active" />
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="$dispatch('open-recipe-modal', { recipeId: {{ $recipe->id }} })" tooltip="Edit Resep" />
                                    <flux:button size="sm" variant="subtle" icon="trash" class="text-red-500 hover:text-red-600" wire:confirm="Hapus resep ini secara permanen?" wire:click="deleteRecipe({{ $recipe->id }})" tooltip="Hapus" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                <div class="px-6 py-12 text-center text-zinc-500">
                                    <flux:icon.document-text class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600 mb-3" />
                                    <p class="text-base font-medium text-zinc-900 dark:text-zinc-100">Belum ada resep</p>
                                    <p class="mt-1">Buat resep baru untuk menentukan komposisi perakitan produk.</p>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
            </flux:table.rows>
        </flux:table>
    </x-table.wrapper>

    <livewire:recipe.form-modal />
    <livewire:global.item-gallery-modal context="inventory" />
</div>
