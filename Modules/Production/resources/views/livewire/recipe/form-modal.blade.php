<?php
use function Livewire\Volt\{state, on, computed};
use Modules\Production\Models\ProductionRecipe;
use Modules\Production\Models\ProductionRecipeItem;
use Modules\Inventory\Models\Item;

state([
    'show' => false,
    'recipeId' => null,
    'item_id' => '',
    'product_name' => '', // For display
    'materials' => [], // ['item_id' => '', 'name' => '', 'code' => '', 'qty' => 1]
    'pickingMode' => null, // 'product' or 'material'
    'search_query' => '',
]);

on(['open-recipe-modal' => function ($recipeId = null, $itemId = null, $params = null) {
    $this->recipeId = null;
    $this->item_id = '';
    $this->product_name = '';
    $this->materials = [];
    $this->pickingMode = null;
    
    // Support both named arguments (Livewire 3 behavior) and array payload (fallback)
    if (is_array($recipeId)) {
        $params = $recipeId;
        $recipeId = $params['recipeId'] ?? null;
        $itemId = $params['itemId'] ?? null;
    } elseif (is_array($params)) {
        $recipeId = $recipeId ?? $params['recipeId'] ?? null;
        $itemId = $itemId ?? $params['itemId'] ?? null;
    }
    
    if ($recipeId) {
        $recipe = ProductionRecipe::with(['items.item', 'item'])->find($recipeId);
        if ($recipe) {
            $this->recipeId = $recipe->id;
            $this->item_id = $recipe->item_id;
            $this->product_name = $recipe->item->code . ' - ' . $recipe->item->name;
            
            foreach ($recipe->items as $bom) {
                $this->materials[] = [
                    'item_id' => $bom->item_id,
                    'code' => $bom->item->code,
                    'name' => $bom->item->name,
                    'qty' => $bom->qty,
                    'unit' => $bom->item->unit->name ?? 'pcs',
                    'image' => $bom->item->image ?? null
                ];
            }
        }
    } elseif ($itemId) {
        $item = Item::find($itemId);
        if ($item) {
            $this->item_id = $item->id;
            $this->product_name = $item->code . ' - ' . ($item->alias ? $item->alias . ' - ' . $item->name : $item->name);
        }
    } else {
        // Start empty
    }
    
    $this->show = true;
}]);

$removeMaterial = function ($index) {
    unset($this->materials[$index]);
    $this->materials = array_values($this->materials); // Reindex
};

$searchResults = computed(function () {
    if (strlen($this->search_query) < 2) return [];
    return Item::with('unit')->where('name', 'like', '%' . $this->search_query . '%')
               ->orWhere('code', 'like', '%' . $this->search_query . '%')
               ->take(5)->get();
});

$addMaterialToRecipe = function ($itemId, $itemCode, $itemName, $unit = 'pcs', $image = null) {
    // Cek duplikat
    foreach ($this->materials as $mat) {
        if ($mat['item_id'] == $itemId) {
            \Flux::toast('Bahan sudah ada di resep.', variant: 'warning');
            $this->search_query = '';
            return;
        }
    }
    
    $this->materials[] = [
        'item_id' => $itemId,
        'code' => $itemCode,
        'name' => $itemName,
        'qty' => 1,
        'unit' => $unit,
        'image' => $image
    ];
    
    $this->search_query = '';
};

$save = function () {
    abort_unless(auth()->user()->can('production.order.update'), 403);
    
    $this->validate([
        'item_id' => 'required',
        'materials' => 'required|array|min:1',
        'materials.*.item_id' => 'required',
        'materials.*.qty' => 'required|integer|min:1',
    ], [
        'item_id.required' => 'Pilih produk jadi terlebih dahulu.',
        'materials.required' => 'Minimal harus ada 1 bahan baku.',
        'materials.*.item_id.required' => 'Bahan baku tidak boleh kosong.',
        'materials.*.qty.min' => 'Kuantitas minimal 1.',
        'materials.*.qty.integer' => 'Kuantitas harus bilangan bulat.'
    ]);

    // Check if recipe for this item already exists (if creating new)
    if (!$this->recipeId) {
        $exists = ProductionRecipe::where('item_id', $this->item_id)->exists();
        if ($exists) {
            $this->addError('item_id', 'Resep untuk produk ini sudah ada.');
            return;
        }
    }

    $recipe = ProductionRecipe::updateOrCreate(
        ['id' => $this->recipeId],
        [
            'item_id' => $this->item_id,
            'is_active' => true
        ]
    );

    // Sync BOM items
    $recipe->items()->delete();
    foreach ($this->materials as $mat) {
        $recipe->items()->create([
            'item_id' => $mat['item_id'],
            'qty' => $mat['qty']
        ]);
    }

    \Flux::toast('Resep berhasil disimpan!', variant: 'success');
    $this->dispatch('recipe-saved');
    $this->show = false;
};

$handleItemSelected = function ($itemData) {
    if ($this->pickingMode === 'product') {
        $this->item_id = $itemData['item_id'];
        $this->product_name = $itemData['code'] . ' - ' . $itemData['name'];
    } elseif ($this->pickingMode === 'material') {
        $this->addMaterialToRecipe($itemData['item_id'], $itemData['code'], $itemData['name'], $itemData['unit'] ?? 'pcs', $itemData['image'] ?? null);
    }
};
?>

<div x-data="{ isRecipeOpen: @entangle('show') }" @item-selected.window="if(isRecipeOpen) { $wire.handleItemSelected($event.detail.item); setTimeout(() => { $flux.modal('gallery-modal').close() }, 50); }">
<flux:modal wire:model="show" class="md:w-[700px] max-w-full">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $recipeId ? 'Edit Resep Produksi' : 'Buat Resep Baru' }}</flux:heading>
            <flux:subheading>Tentukan produk yang akan dirakit beserta komposisi bahan-bahannya.</flux:subheading>
        </div>

        <div class="space-y-4">
            <div>
                <flux:label>Pilih Produk Jadi (Hasil Rakitan)</flux:label>
                <div class="flex gap-2 mt-1">
                    <flux:input wire:model="product_name" readonly placeholder="Ketik atau klik tombol galeri ->" class="flex-1 bg-zinc-50" />
                    <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; $wire.set('pickingMode', 'product'); Livewire.dispatch('open-gallery', { context: 'production' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading || $recipeId !== null">
                        <div class="flex items-center gap-2">
                            <flux:icon.squares-plus class="w-4 h-4" x-show="!loading" />
                            <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="hidden sm:block">Galeri</span>
                        </div>
                    </flux:button>
                </div>
            </div>
            
            @error('item_id')
                <div class="text-sm text-red-500">{{ $message }}</div>
            @enderror

            <div class="mt-6 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="md">Komposisi Bahan Baku (BOM)</flux:heading>
                </div>
                
                {{-- Unified Search Bar & Gallery Button --}}
                <div class="flex items-center gap-2 mb-4">
                    <div class="relative flex-1" x-data="{ focused: false }" @click.outside="focused = false">
                        <flux:input 
                            wire:model.live.debounce.300ms="search_query" 
                            @focus="focused = true"
                            icon="magnifying-glass" 
                            placeholder="Cari bahan baku tambahan..." />
                        
                        {{-- Dropdown Suggestion --}}
                        <div x-show="focused && $wire.search_query.length >= 2" 
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-xl overflow-hidden">
                            @if(count($this->searchResults) > 0)
                                <ul class="divide-y divide-zinc-100 dark:divide-zinc-700 max-h-64 overflow-y-auto custom-scrollbar">
                                    @foreach($this->searchResults as $res)
                                        <li wire:click="addMaterialToRecipe({{ $res->id }}, '{{ $res->code ?? '-' }}', '{{ addslashes($res->name) }}', '{{ $res->unit->name ?? 'pcs' }}', '{{ $res->image }}')"
                                            class="px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-700 cursor-pointer flex items-center gap-3 transition-colors">
                                            @if($res->image)
                                                <img src="{{ Storage::url($res->image) }}" class="w-8 h-8 rounded bg-zinc-100 object-cover">
                                            @else
                                                <div class="w-8 h-8 rounded bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400">
                                                    <flux:icon.cube class="w-4 h-4" />
                                                </div>
                                            @endif
                                            <div>
                                                <div class="mb-0.5">
                                                    <span class="inline-block px-1 py-0.5 text-[9px] font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded border border-zinc-300 dark:border-zinc-600 leading-none">
                                                        {{ $res->code }}
                                                    </span>
                                                </div>
                                                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $res->name }}</div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="px-4 py-3 text-sm text-zinc-500 text-center">Barang tidak ditemukan.</div>
                            @endif
                        </div>
                    </div>
                    
                    <flux:button variant="primary" class="shrink-0" x-data="{ loading: false }" x-on:click="loading = true; $wire.set('pickingMode', 'material'); Livewire.dispatch('open-gallery', { context: 'production' }); setTimeout(() => { $flux.modal('gallery-modal').show(); loading = false; }, 300)" x-bind:disabled="loading">
                        <div class="flex items-center gap-2">
                            <flux:icon.squares-2x2 class="w-4 h-4" x-show="!loading" />
                            <svg x-show="loading" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span class="hidden md:block">Galeri</span>
                        </div>
                    </flux:button>
                </div>
                
                @if(empty($materials))
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-center text-sm text-zinc-500 border border-zinc-200 dark:border-zinc-700">
                        <flux:icon.beaker class="w-8 h-8 mx-auto mb-2 opacity-30" />
                        Belum ada bahan. Silakan cari atau pilih dari galeri.
                    </div>
                @else
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-2">
                        <div class="space-y-2">
                            @foreach($materials as $index => $mat)
                                <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center gap-3 shadow-sm hover:border-emerald-500/30 transition-colors">
                                    <div class="flex-1 flex items-center gap-3 min-w-0">
                                        @if(!empty($mat['image']))
                                            <img src="{{ Storage::url($mat['image']) }}" class="w-10 h-10 rounded-lg object-cover bg-zinc-100 shrink-0">
                                        @else
                                            <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 shrink-0">
                                                <flux:icon.cube class="w-5 h-5" />
                                            </div>
                                        @endif
                                        <div class="flex flex-col min-w-0">
                                            <div class="mb-1">
                                                <span class="inline-block px-1.5 py-0.5 text-[9px] font-mono font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 rounded border border-zinc-300 dark:border-zinc-600 leading-none shadow-sm">
                                                    {{ $mat['code'] }}
                                                </span>
                                            </div>
                                            <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm truncate">{{ $mat['name'] }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1 sm:mt-0 justify-between sm:justify-end border-t sm:border-0 border-zinc-100 dark:border-zinc-700 pt-3 sm:pt-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-24">
                                                <flux:input type="number" inputmode="numeric" pattern="[0-9]*" wire:model="materials.{{ $index }}.qty" step="1" min="1" class="text-center !h-8 !text-sm" />
                                                @error('materials.'.$index.'.qty') <span class="text-[10px] text-red-500 block mt-1">{{ $message }}</span> @enderror
                                            </div>
                                            <span class="text-xs text-zinc-500 font-medium w-8">{{ $mat['unit'] ?? 'pcs' }}</span>
                                        </div>
                                        <flux:button variant="subtle" size="sm" icon="trash" class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 w-8 h-8 p-0 shrink-0" wire:click="removeMaterial({{ $index }})" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                @error('materials')
                    <div class="text-sm text-red-500 mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Batal </flux:button>
            <flux:button icon="check" variant="primary" wire:click="save" wire:target="save" wire:loading.attr="disabled"> Simpan Resep </flux:button>
        </div>
    </div>
</flux:modal>
</div>
