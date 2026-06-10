@props(['search' => '', 'results' => collect(), 'warehouseId' => null, 'placeholder' => 'Ketik nama atau kode barang...'])

<div class="relative w-full">
    <!-- Input Search -->
    <flux:input 
        wire:model.live.debounce.300ms="search" 
        icon="magnifying-glass" 
        :placeholder="$placeholder" 
        autocomplete="off"
    />

    <!-- Dropdown Results -->
    @if(strlen($search) >= 2)
        <div class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            @if($results->count() > 0)
                <ul class="max-h-60 overflow-y-auto py-1">
                    @foreach($results as $item)
                        <li>
                            <button 
                                type="button" 
                                wire:click="selectItem({{ $item->id }})"
                                class="w-full text-left px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 focus:bg-zinc-100 dark:focus:bg-zinc-700 focus:outline-none transition-colors duration-150 flex justify-between items-center"
                            >
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-white text-sm">{{ $item->name }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->code }}</div>
                                </div>
                                @if($warehouseId)
                                    @php
                                        $wh = $item->warehouses->firstWhere('id', $warehouseId);
                                        $qty = $wh ? $wh->pivot->stock : 0;
                                    @endphp
                                    <div class="text-xs font-semibold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded">
                                        Stok: {{ $qty }}
                                    </div>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400 text-center">
                    Barang tidak ditemukan.
                </div>
            @endif
        </div>
    @endif
</div>
