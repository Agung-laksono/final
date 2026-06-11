<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Category;
use Livewire\Attributes\On;

new class extends Component {
    use WithPagination;

    public $searchQuery = '';
    public $categoryId = '';
    public $perPage = 12;

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }
    
    public function updatedCategoryId()
    {
        $this->resetPage();
    }
    
    public function loadMore()
    {
        $this->perPage += 12;
    }
    
    #[On('item-saved')]
    #[On('echo:inventory,InventoryUpdated')]
    public function refreshGallery()
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = Item::with(['category', 'subCategory', 'unit', 'warehouses']);

        if (strlen($this->searchQuery) >= 2) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('code', 'like', '%' . $this->searchQuery . '%');
            });
        }
        
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }

        return [
            'galleryItems' => $query->latest()->paginate($this->perPage),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
};
?>

<div>
    <flux:modal name="gallery-modal" class="md:max-w-4xl">
        <div class="space-y-4">
            <div class="flex items-start">
                <div>
                    <flux:heading size="lg">Galeri Barang</flux:heading>
                    <flux:subheading>Cari dan pilih barang untuk dimasukkan ke Purchase Order.</flux:subheading>
                </div>
            </div>
            
            {{-- Search & Filter Bar (Sticky) --}}
            <div class="sticky -top-6 z-50 bg-white dark:bg-zinc-900 pb-2 pt-1 -mx-2 px-2">
                <div class="flex flex-col sm:flex-row items-center gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <flux:input 
                        wire:model.live.debounce.300ms="searchQuery" 
                        icon="magnifying-glass" 
                        placeholder="Cari nama atau kode barang..." 
                        class="flex-1 w-full" />
                        
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <flux:select wire:model.live="categoryId" placeholder="Semua Kategori" class="w-full sm:w-48">
                            <flux:select.option value="">Semua Kategori</flux:select.option>
                            @foreach($categories as $category)
                                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        
                        <flux:button wire:click="$dispatch('open-item-modal')" variant="primary" icon="plus" class="shrink-0">
                            <span class="hidden md:inline">Barang Baru</span>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Loading State Indicator --}}
            <div wire:loading wire:target="searchQuery" class="w-full text-center py-4">
                <span class="text-sm text-zinc-500 flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 animate-spin" /> Mencari barang...
                </span>
            </div>

            <div wire:loading.remove wire:target="searchQuery" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-2">
                @forelse($galleryItems as $item)
                    <div @click="$dispatch('item-selected', { item: { item_id: {{ $item->id }}, name: '{{ addslashes($item->name) }}', code: '{{ $item->code ?? '0001' }}', unit_price: {{ $item->purchase_price ?? 0 }}, image: '{{ $item->image }}' } })"
                         :class="$data.items?.find(i => i.item_id == {{ $item->id }}) ? 'border-cyan-600 ring-2 ring-cyan-600 shadow-lg scale-[1.02]' : 'border-zinc-200 dark:border-zinc-800 hover:border-cyan-500/50 hover:shadow-lg hover:scale-[1.02]'"
                         class="relative bg-white dark:bg-zinc-900 rounded-xl border overflow-hidden transition-all cursor-pointer group flex flex-col h-full">
                        
                        {{-- NON ACTIVE Overlay --}}
                        @if (!$item->is_active)
                        <div class="absolute z-20 top-0 w-full h-full bg-[#000000ba] flex items-center justify-center pointer-events-none">
                            <span class="font-bold text-white tracking-widest text-sm">NON ACTIVE</span>
                        </div>
                        @endif

                        {{-- Selection Badge --}}
                        <template x-if="$data.items?.find(i => i.item_id == {{ $item->id }})">
                            <div class="absolute top-2 right-2 bg-cyan-600 text-white text-xs font-extrabold px-2 py-1 rounded-md flex items-center gap-1 shadow-md z-30 pointer-events-none">
                                <flux:icon.check-circle class="w-4 h-4" />
                                <span x-text="$data.items.find(i => i.item_id == {{ $item->id }}).qty + 'x'"></span>
                            </div>
                        </template>

                        {{-- Gambar Atas (Mencolok) --}}
                        <div class="relative w-full aspect-[4/3] bg-zinc-100 dark:bg-zinc-800 overflow-hidden border-b border-zinc-100 dark:border-zinc-800/50">
                            @if ($item->image)
                                <img src="{{ Storage::url($item->image) }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-zinc-300">
                                    <flux:icon.photo class="w-10 h-10" />
                                </div>
                            @endif
                        </div>
                        
                        {{-- Informasi Bawah --}}
                        <div class="p-3 flex flex-col flex-1">
                            {{-- Kategori & Kode --}}
                            <div class="flex justify-between items-center mb-1.5 gap-2">
                                <span class="text-[8px] sm:text-[9px] font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-widest truncate">{{ $item->category?->name ?? 'Tanpa Kategori' }} / {{ $item->subCategory?->name ?? '-' }}</span>
                                <span class="text-[9px] font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-zinc-600 dark:text-zinc-400 shrink-0 border border-zinc-200 dark:border-zinc-700/50">{{ $item->code }}</span>
                            </div>
                            
                            {{-- Nama Barang --}}
                            <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 text-[13px] leading-snug line-clamp-2 mb-3 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">
                                {{ $item->name }}
                            </h3>
                            
                            {{-- Harga & Stok --}}
                            <div class="mt-auto pt-2.5 border-t border-zinc-100 dark:border-zinc-800/50 flex justify-between items-end">
                                <div class="flex flex-col">
                                    <span class="text-[10px] sm:text-[11px] font-medium text-zinc-400 mb-0.5">Harga
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 leading-none"> jual</span> /
                                        <span class="font-bold text-gray-600 dark:text-gray-400 leading-none"> beli</span>
                                    </span>
                                    <span class="flex flex-col sm:flex-row sm:items-baseline gap-1">
                                        <span class="font-bold text-emerald-600 dark:text-emerald-400 text-[11px] sm:text-xs leading-none">Rp{{ number_format($item->selling_price ?? 0, 0, ',', '.') }} <span class="hidden sm:inline">/</span></span>
                                        <span class="font-bold text-gray-600 dark:text-gray-400 text-[9px] leading-none">Rp{{ number_format($item->purchase_price ?? 0, 0, ',', '.') }}</span>
                                    </span>
                                </div>
                                
                                <div class="flex flex-col items-end">
                                    <span class="text-[9px] font-medium text-zinc-400 mb-0.5">Stok</span>
                                    <div class="flex items-baseline gap-0.5">
                                        <span class="font-bold text-zinc-800 dark:text-zinc-200 text-[14px] sm:text-[16px] leading-none">{{ $item->warehouses->sum('pivot.stock') }}</span>
                                        <span class="text-[9px] text-zinc-500">{{ $item->unit?->name ?? '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center flex flex-col items-center justify-center">
                        <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                        <span class="text-zinc-500">Tidak ada barang yang cocok dengan pencarian Anda.</span>
                    </div>
                @endforelse
            </div>

            {{-- Pagination (Load More) --}}
            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <x-load-more :paginator="$galleryItems" item-name="barang" />
            </div>
        </div>
    </flux:modal>
</div>
