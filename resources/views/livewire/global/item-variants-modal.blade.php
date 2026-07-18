<?php

use Livewire\Volt\Component;
use Livewire\Attributes\On;
use Modules\Sales\Models\SalesOrderItem;
use Modules\Inventory\Models\Item;
use Flux\Flux;

new class extends Component {
    public $itemId = null;
    public $itemName = '';
    public $historyItems = [];

    public function loadData($itemId)
    {
        $this->itemId = $itemId;
        
        $item = Item::find($itemId);
        $this->itemName = $item ? $item->name : 'Varian Barang';
        
        $this->loadHistory();
    }
    
    public function loadHistory()
    {
        if (!$this->itemId) return;
        
        $this->historyItems = SalesOrderItem::with('salesOrder.customer')
            ->where('item_id', $this->itemId)
            ->whereNotNull('custom_attributes')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get()
            ->map(function ($poi) {
                return [
                    'id' => $poi->id,
                    'date' => $poi->salesOrder?->order_date ?? '',
                    'customer' => $poi->salesOrder?->customer?->name ?? 'Unknown',
                    'so_number' => $poi->salesOrder?->so_number ?? '',
                    'attributes' => $poi->custom_attributes ?? [],
                    'attachments' => $poi->custom_attachments ?? [],
                    // Need to also provide base item info so we can add it to cart easily
                    'item' => [
                        'item_id' => $this->itemId,
                        'name' => $this->itemName,
                        'code' => $poi->item->code ?? '0001',
                        'unit_price' => $poi->unit_price ?? 0,
                        'image' => $poi->item->image ?? null,
                    ]
                ];
            })
            ->toArray();
    }

    public function selectVariant($historyId)
    {
        $history = collect($this->historyItems)->firstWhere('id', $historyId);
        if ($history) {
            // Dispatch event to Alpine cart system
            $this->dispatch('add-variant-to-cart', [
                'item' => $history['item'],
                'custom_attributes' => $history['attributes'],
                'custom_attachments' => $history['attachments']
            ]);
            Flux::modal('item-variants-modal')->close();
            
            // Also notify user
            $this->dispatch('notify', [
                'type' => 'success',
                'title' => 'Varian Ditambahkan',
                'description' => 'Varian berhasil ditambahkan ke keranjang.'
            ]);
        }
    }
};
?>
<div>
    <div x-data="{ open: false }" 
         @open-variants.window="
             console.log('Badge diklik! Membuka laci varian...');
             open = true; 
             $wire.loadData($event.detail.itemId);
         "
         x-show="open" 
         class="relative z-[9999]" 
         x-cloak
         style="display: none;">
    
    {{-- Backdrop --}}
    <div x-show="open" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" 
         @click="open = false"></div>
         
    {{-- Panel --}}
    <div class="fixed inset-y-0 right-0 z-10 w-full overflow-hidden sm:w-96 md:w-[450px] bg-slate-50 shadow-2xl transform transition-transform flex flex-col"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
         
    <!-- Header -->
    <div class="px-6 py-5 border-b border-slate-200 bg-white shadow-sm shrink-0 flex justify-between items-start">
        <div class="flex gap-4">
            <div class="w-10 h-10 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
                <flux:icon.swatch class="w-5 h-5 text-indigo-600" />
            </div>
            <div>
                <h2 class="text-xl font-bold text-slate-800">Galeri Varian</h2>
                <p class="text-sm text-slate-500 flex items-center gap-1.5 mt-0.5 font-medium">
                    <flux:icon.cube class="w-3.5 h-3.5 text-slate-400" />
                    {{ $itemName }}
                </p>
            </div>
        </div>
        <button @click="open = false" class="text-slate-400 hover:text-slate-600 transition-colors p-1 rounded-md hover:bg-slate-100">
            <flux:icon.x-mark class="w-5 h-5" />
        </button>
    </div>

    <!-- Content -->
    <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
        @if(empty($historyItems))
            <div class="text-center py-16 flex flex-col items-center justify-center h-full">
                <flux:icon.archive-box class="w-16 h-16 text-slate-200 mb-4" />
                <h3 class="text-lg font-bold text-slate-700 mb-1">Belum Ada Varian</h3>
                <p class="text-sm text-slate-500 max-w-[250px]">Belum ada riwayat pesanan custom untuk barang ini.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-5">
                @foreach($historyItems as $history)
                    <div class="group bg-white border border-slate-200 hover:border-indigo-300 rounded-2xl shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden relative">
                        {{-- Image Section (Top) --}}
                        <div class="w-full h-40 bg-slate-100 flex items-center justify-center relative border-b border-slate-200 overflow-hidden">
                            @if(count($history['attachments']) > 0)
                                <img src="{{ asset('storage/' . $history['attachments'][0]) }}" class="w-full h-full object-cover absolute inset-0 transition-transform duration-500 group-hover:scale-105">
                                @if(count($history['attachments']) > 1)
                                    <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] font-bold px-2 py-1 rounded shadow-sm backdrop-blur-sm z-10">
                                        +{{ count($history['attachments']) - 1 }} Foto
                                    </div>
                                @endif
                            @else
                                <flux:icon.cube class="w-12 h-12 text-slate-300" />
                            @endif
                            
                            {{-- SO Badge floating on top left --}}
                            <div class="absolute top-2 left-2 bg-white/95 backdrop-blur-sm text-slate-700 px-2 py-0.5 rounded-md text-[10px] font-bold border border-slate-200 shadow-sm flex items-center gap-1 z-10">
                                <flux:icon.document-text class="w-3 h-3 text-slate-400" />
                                {{ $history['so_number'] }}
                            </div>
                            
                            {{-- Overlay gradient for text readability --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                        </div>

                        {{-- Content Section (Bottom) --}}
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-3">
                                <span class="text-[10px] font-medium text-slate-400 flex items-center gap-1"><flux:icon.calendar class="w-3 h-3" /> {{ $history['date'] }}</span>
                                <div class="flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-indigo-100 truncate max-w-[120px]" title="{{ $history['customer'] }}">
                                    <flux:icon.user class="w-3 h-3 shrink-0" />
                                    <span class="truncate">{{ $history['customer'] }}</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-col gap-2 mb-5 flex-1">
                                @foreach($history['attributes'] as $attr)
                                    <div class="text-[11px] flex justify-between bg-slate-50 px-2 py-1.5 rounded border border-slate-100">
                                        <span class="text-slate-500">{{ $attr['key'] }}</span> 
                                        <span class="font-bold text-slate-700 text-right break-words max-w-[60%]">{{ $attr['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>

                            <flux:button variant="primary" icon="shopping-cart" wire:click="selectVariant({{$history['id']}})" class="w-full !bg-indigo-600 hover:!bg-indigo-700 !border-indigo-700 mt-auto font-bold shadow-sm">Masukkan ke Keranjang</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    
    <!-- Footer -->
    <div class="p-4 border-t border-slate-200 bg-white shrink-0 flex justify-end">
        <flux:button variant="subtle" @click="open = false">Tutup</flux:button>
    </div>
    </div>
</div>
</div>
