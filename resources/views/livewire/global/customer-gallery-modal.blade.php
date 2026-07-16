<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\On;
use Modules\Sales\Models\Customer;

new class extends Component {
    use WithPagination;

    public $searchQuery = '';

    public function updatedSearchQuery()
    {
        $this->resetPage();
    }

    #[On('customer-saved')]
    #[On('echo:purchase,CustomerUpdated')]
    public function refreshGallery()
    {
        $this->resetPage();
    }

    public function with()
    {
        $query = Customer::query();

        if (strlen($this->searchQuery) >= 2) {
            $query->where('name', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('phone', 'like', '%' . $this->searchQuery . '%')
                  ->orWhere('type', 'like', '%' . $this->searchQuery . '%');
        }

        return [
            'customers' => $query->latest()->paginate(12),
        ];
    }
};
?>

<div>
    <flux:modal name="customer-gallery-modal" class="md:w-[800px] max-w-4xl max-sm:!p-0 max-sm:!m-0 max-sm:w-full max-sm:h-full max-sm:max-w-none max-sm:max-h-[100dvh] max-sm:rounded-none">
        <div class="space-y-6">
            <div class="hidden sm:flex items-start justify-between">
                <div>
                    <flux:heading size="lg">Galeri Customer</flux:heading>
                    <flux:subheading>Cari dan pilih customer atau supplier dari daftar di bawah ini.</flux:subheading>
                </div>
            </div>
            
            {{-- Search Bar & Filter (Sticky) --}}
            <div x-data="{ searchActive: false, showHeader: true, lastScrollY: 0 }"
                 x-init="
                     document.addEventListener('scroll', (e) => {
                         if (e.target.contains && e.target.contains($el)) {
                             let currentScroll = e.target.scrollTop;
                             if (currentScroll !== undefined) {
                                 if (Math.abs(currentScroll - lastScrollY) > 10) {
                                     showHeader = currentScroll < lastScrollY || currentScroll < 50;
                                     lastScrollY = currentScroll;
                                 }
                             }
                         }
                     }, true);
                 "
                 class="sticky -top-6 z-50 bg-white dark:bg-zinc-900 pb-2 pt-1 -mx-2 px-2 transition-all duration-300"
                 :class="showHeader ? 'translate-y-0 opacity-100' : '-translate-y-[120%] opacity-0 pointer-events-none'">
                <div class="flex items-center gap-3 bg-zinc-50 dark:bg-zinc-800/50 p-2 rounded-xl border border-zinc-200 dark:border-zinc-700 transition-all duration-300">
                    <div class="flex-1 min-w-0" @focusin="searchActive = true" @focusout="setTimeout(() => searchActive = false, 200)">
                        <flux:input 
                            wire:model.live.debounce.300ms="searchQuery" 
                            icon="magnifying-glass" 
                            placeholder="Cari nama, tipe, atau telepon..." 
                            class="w-full transition-all duration-300" />
                    </div>
                    
                    <div class="flex items-center shrink-0 overflow-hidden transition-all duration-300 origin-right"
                         :class="searchActive ? 'max-w-0 opacity-0 gap-0' : 'max-w-[300px] opacity-100 gap-1'">
                        <flux:button wire:click="$dispatch('open-customer-modal')" wire:loading.attr="disabled" variant="primary" icon="users" class="shrink-0 px-2 md:px-4">
                            <span class="hidden md:inline ml-1">Customer Baru</span>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Loading State Indicator --}}
            <div wire:loading wire:target="searchQuery" class="w-full text-center py-4">
                <span class="text-sm text-zinc-500 flex items-center justify-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 animate-spin" /> Mencari customer...
                </span>
            </div>

            <div wire:loading.remove wire:target="searchQuery" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 p-1">
                @forelse($customers as $customer)
                    <div x-data="{ selected: false }"
                         @click="
                            if (typeof window.playSelectSound !== 'undefined') window.playSelectSound('ting'); 
                            selected = true; 
                            setTimeout(() => { 
                                $dispatch('customer-selected', { customer: {{ json_encode($customer) }} }); 
                                $flux.modal('customer-gallery-modal').close();
                                selected = false; 
                            }, 200)
                         "
                         :class="selected ? 'border-cyan-600 ring-2 ring-cyan-600 shadow-lg scale-[1.02]' : 'hover:border-cyan-500/50 hover:shadow-lg hover:scale-[1.02]'"
                         class="cursor-pointer relative group bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 transition-all h-full">
                        
                        {{-- Selection Badge --}}
                        <template x-if="selected">
                            <div class="absolute inset-0 z-40 pointer-events-none flex items-center justify-center bg-white/30 dark:bg-black/30 backdrop-blur-[1px] rounded-xl transition-all">
                                <div class="bg-cyan-600 text-white font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg border border-white/40 dark:border-zinc-700/50 transition-all">
                                    <flux:icon.check-circle class="w-4 h-4" />
                                </div>
                            </div>
                        </template>
                        
                        <div class="flex items-center gap-4">
                            <flux:avatar src="{{ $customer->image ? Storage::url($customer->image) : '' }}" fallback="{{ substr($customer->name, 0, 2) }}" size="lg" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 truncate">{{ $customer->name }}</div>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 leading-none shrink-0">
                                        {{ $customer->type }}
                                    </span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-1.5 truncate"><flux:icon.phone class="w-3 h-3 inline-block shrink-0 text-zinc-400" /> {{ $customer->phone ?: 'Belum ada nomor telepon' }}</div>
                                @if($customer->province || $customer->city)
                                <div class="text-xs text-zinc-500 mt-0.5 truncate" title="{{ implode(', ', array_filter([$customer->district, $customer->city, $customer->province])) }}">
                                    <flux:icon.map-pin class="w-3 h-3 inline-block shrink-0 text-zinc-400" /> {{ implode(', ', array_filter([$customer->district, $customer->city, $customer->province])) }}
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center flex flex-col items-center justify-center">
                        <flux:icon.inbox class="w-12 h-12 text-zinc-300 mb-3" />
                        <span class="text-zinc-500">Tidak ada customer yang cocok dengan pencarian Anda.</span>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800">
                {{ $customers->links() }}
            </div>
        </div>

        {{-- Floating Back Button (Mobile) --}}
        <div class="sm:hidden sticky bottom-4 left-4 z-[100] w-max -mt-2">
            <button @click="$flux.modal('customer-gallery-modal').close()" class="w-14 h-14 bg-white dark:bg-zinc-800 rounded-full flex items-center justify-center shadow-[0_8px_30px_rgb(0,0,0,0.12)] border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 active:scale-95 transition-all focus:outline-none">
                <flux:icon.arrow-left stroke-width="2.5" class="w-6 h-6" />
            </button>
        </div>
    </flux:modal>
</div>
