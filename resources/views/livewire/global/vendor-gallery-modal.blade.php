<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Modules\Purchase\Models\Vendor;

new class extends Component {
    use WithPagination;

    public $searchQuery = '';

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    #[On('vendor-saved')]
    #[On('echo:purchase,VendorUpdated')]
    public function refreshGallery()
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = Vendor::query();

        if (strlen($this->searchQuery) >= 2) {
            $query->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('type', 'like', '%' . $this->searchQuery . '%');
        }

        return [
            'vendors' => $query->latest()->paginate(12),
        ];
    }
};
?>

<div>
    <flux:modal name="vendor-gallery-modal" class="md:w-[800px] max-w-4xl">
        <div class="space-y-6">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">Galeri Vendor</flux:heading>
                    <flux:subheading>Cari dan pilih vendor atau supplier dari daftar di bawah ini.</flux:subheading>
                </div>
            </div>
            
            {{-- Search Bar & Add Button --}}
            <div class="flex items-center gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <flux:input 
                    wire:model.live.debounce.300ms="searchQuery" 
                    icon="magnifying-glass" 
                    placeholder="Cari nama, tipe, atau telepon vendor..." 
                    class="flex-1" />
                
                <flux:button wire:click="$dispatch('open-vendor-modal')" wire:loading.attr="disabled" variant="primary" icon="plus" class="shrink-0">
                    <span class="hidden md:inline">Vendor Baru</span>
                </flux:button>
            </div>

            {{-- Loading State Indicator --}}
            <div wire:loading wire:target="searchQuery" class="w-full text-center py-4">
                <span class="text-sm text-zinc-500 flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 animate-spin" /> Mencari vendor...
                </span>
            </div>

            <div wire:loading.remove wire:target="searchQuery" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 max-h-[55vh] overflow-y-auto p-1 custom-scrollbar">
                @forelse($vendors as $vendor)
                    <div wire:click="$dispatch('vendor-selected', { vendorId: {{ $vendor->id }} })" 
                         x-on:click="$flux.modal('vendor-gallery-modal').close()"
                         class="cursor-pointer group bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 hover:border-cyan-500 hover:ring-1 hover:ring-cyan-500 rounded-xl p-4 transition-all h-full">
                        <div class="flex items-center gap-4">
                            <flux:avatar src="{{ $vendor->image ? Storage::url($vendor->image) : '' }}" fallback="{{ substr($vendor->name, 0, 2) }}" size="lg" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 truncate">{{ $vendor->name }}</div>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 leading-none shrink-0">
                                        {{ $vendor->type }}
                                    </span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-1.5 truncate"><flux:icon.phone class="w-3 h-3 inline-block shrink-0 text-zinc-400" /> {{ $vendor->phone ?: 'Belum ada nomor telepon' }}</div>
                                @if($vendor->province || $vendor->city)
                                <div class="text-xs text-zinc-500 mt-0.5 truncate" title="{{ implode(', ', array_filter([$vendor->district, $vendor->city, $vendor->province])) }}">
                                    <flux:icon.map-pin class="w-3 h-3 inline-block shrink-0 text-zinc-400" /> {{ implode(', ', array_filter([$vendor->district, $vendor->city, $vendor->province])) }}
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center flex flex-col items-center justify-center">
                        <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                        <span class="text-zinc-500">Tidak ada vendor yang cocok dengan pencarian Anda.</span>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                {{ $vendors->links() }}
            </div>
        </div>
    </flux:modal>
</div>
