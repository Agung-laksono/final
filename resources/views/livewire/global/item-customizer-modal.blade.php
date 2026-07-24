<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Modules\Inventory\Models\Item;
use Modules\Sales\Models\SalesOrderItem;
use Illuminate\Support\Facades\Storage;
use Flux\Flux;

new class extends Component {
    use WithFileUploads;

    public $cartIndex = null;
    public $itemId = null;
    public $itemName = '';
    
    public $note = '';
    public $custom_attributes = [];
    public $existing_attachments = [];
    
    public $temp_attachment = null; // For single image cropper upload
    public $new_attachments = []; // Array of strings (paths)
    
    public $activeTab = 'attributes'; // attributes, attachments, history

    // History
    public $historyItems = [];

    #[On('open-customizer')]
    public function openCustomizer($index, $itemData)
    {
        $this->cartIndex = $index;
        $this->itemId = $itemData['item_id'] ?? null;
        $this->itemName = $itemData['name'] ?? 'Barang';
        $this->note = $itemData['note'] ?? '';
        
        $this->custom_attributes = $itemData['custom_attributes'] ?? [];
        $this->existing_attachments = $itemData['custom_attachments'] ?? [];
        $this->new_attachments = [];
        $this->loadHistory();
        
        // Auto-select history tab if history exists, otherwise fallback to attributes
        if (!empty($this->historyItems)) {
            $this->activeTab = 'history';
        } else {
            $this->activeTab = 'attributes';
        }

        Flux::modal('item-customizer-modal')->show();
    }
    
    public function loadHistory()
    {
        if (!$this->itemId) return;
        
        // Load from SalesOrderItem
        $salesHistory = \Modules\Sales\Models\SalesOrderItem::with(['salesOrder.customer', 'item'])
            ->where('item_id', $this->itemId)
            ->where(function($q) {
                $q->where(function($q1) {
                    $q1->whereNotNull('custom_attributes')
                       ->where('custom_attributes', '!=', '[]')
                       ->where('custom_attributes', '!=', 'null');
                })->orWhere(function($q2) {
                    $q2->whereNotNull('custom_attachments')
                       ->where('custom_attachments', '!=', '[]')
                       ->where('custom_attachments', '!=', 'null');
                });
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get()
            ->map(function ($poi) {
                return [
                    'id' => 'sales_' . $poi->id,
                    'type' => 'SO',
                    'date' => $poi->salesOrder?->order_date ?? '',
                    'customer' => $poi->salesOrder?->customer?->name ?? 'Unknown',
                    'reference' => $poi->salesOrder?->so_number ?? '',
                    'master_image' => $poi->item?->image ?? '',
                    'attributes' => $poi->custom_attributes ?? [],
                    'attachments' => $poi->custom_attachments ?? []
                ];
            });

        // Load from PurchaseOrderItem
        $purchaseHistory = \Modules\Purchase\Models\PurchaseOrderItem::with(['purchaseOrder.vendor', 'item'])
            ->where('item_id', $this->itemId)
            ->where(function($q) {
                $q->where(function($q1) {
                    $q1->whereNotNull('custom_attributes')
                       ->where('custom_attributes', '!=', '[]')
                       ->where('custom_attributes', '!=', 'null');
                })->orWhere(function($q2) {
                    $q2->whereNotNull('custom_attachments')
                       ->where('custom_attachments', '!=', '[]')
                       ->where('custom_attachments', '!=', 'null');
                });
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get()
            ->map(function ($poi) {
                return [
                    'id' => 'purchase_' . $poi->id,
                    'type' => 'PO',
                    'date' => $poi->purchaseOrder?->order_date ?? '',
                    'customer' => $poi->purchaseOrder?->vendor?->name ?? 'Vendor',
                    'reference' => $poi->purchaseOrder?->po_number ?? '',
                    'master_image' => $poi->item?->image ?? '',
                    'attributes' => $poi->custom_attributes ?? [],
                    'attachments' => $poi->custom_attachments ?? []
                ];
            });

        $this->historyItems = $salesHistory->concat($purchaseHistory)
            ->sortByDesc('date')
            ->unique(function ($item) {
                return md5(json_encode($item['attributes']) . json_encode($item['attachments']));
            })
            ->take(10)
            ->values()
            ->toArray();
    }
    
    public function reuseHistory($historyId)
    {
        $history = collect($this->historyItems)->firstWhere('id', $historyId);
        if ($history) {
            $this->custom_attributes = $history['attributes'];
            // We can also copy attachments if we want, but copying files means duplicating them or referencing the same.
            // For now, let's reference the same existing attachments.
            $this->existing_attachments = $history['attachments'];
            $this->activeTab = 'attributes';
        }
    }

    public function updatedTempAttachment()
    {
        if ($this->temp_attachment) {
            if (is_string($this->temp_attachment) && str_starts_with($this->temp_attachment, 'data:image')) {
                $parts = explode(',', $this->temp_attachment);
                if (count($parts) == 2) {
                    $imageData = base64_decode($parts[1]);
                    $extension = 'webp';
                    if (preg_match('/^data:image\/(\w+);base64/', $parts[0], $type)) {
                        $extension = strtolower($type[1]);
                        if ($extension == 'jpeg') $extension = 'jpg';
                    }
                    $filename = 'custom_attachments/' . uniqid('custom_', true) . '.' . $extension;
                    Storage::disk('public')->put($filename, $imageData);
                    $this->new_attachments[] = $filename;
                }
            }
            $this->temp_attachment = null;
            $this->dispatch('reset-cropper');
        }
    }

    public function addAttribute()
    {
        $this->custom_attributes[] = ['key' => '', 'value' => ''];
    }

    public function addSuggestedAttribute($key)
    {
        // Jika ada atribut yang valuenya kosong dan key-nya kosong, kita bisa replace.
        // Tapi untuk simplenya kita tambahkan saja.
        $this->custom_attributes[] = ['key' => $key, 'value' => ''];
    }

    public function removeAttribute($index)
    {
        unset($this->custom_attributes[$index]);
        $this->custom_attributes = array_values($this->custom_attributes);
    }

    public function removeExistingAttachment($index)
    {
        unset($this->existing_attachments[$index]);
        $this->existing_attachments = array_values($this->existing_attachments);
    }
    
    public function removeNewAttachment($index)
    {
        unset($this->new_attachments[$index]);
        $this->new_attachments = array_values($this->new_attachments);
    }

    public function save()
    {
        $finalAttachments = array_merge($this->existing_attachments, $this->new_attachments);

        // Filter empty attributes
        $filteredAttributes = array_filter($this->custom_attributes, function($attr) {
            return !empty(trim($attr['key'])) && !empty(trim($attr['value']));
        });

        // Dispatch back to Alpine
        $this->dispatch('customizer-saved', [
            'index' => $this->cartIndex,
            'note' => $this->note,
            'custom_attributes' => array_values($filteredAttributes),
            'custom_attachments' => $finalAttachments
        ]);

        Flux::modal('item-customizer-modal')->close();
    }
};
?>

<flux:modal name="item-customizer-modal" class="w-full max-w-3xl" @customizer-saved.window="$flux.modal('item-customizer-modal').close()">
    <!-- Header -->
    <div class="p-4 sm:p-5 border-b border-zinc-100 dark:border-zinc-800 flex items-start gap-3 sm:gap-4">
        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-emerald-50 dark:bg-emerald-500/20 border border-emerald-100 dark:border-emerald-500/30 flex items-center justify-center shrink-0">
            <flux:icon.adjustments-horizontal class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 dark:text-emerald-400" />
        </div>
        <div>
            <h2 class="text-lg sm:text-xl font-bold text-zinc-800 dark:text-white">Customisasi Spesifikasi</h2>
            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 flex items-center gap-1 mt-0.5">
                <flux:icon.cube class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-zinc-400 dark:text-zinc-500" />
                {{ $itemName }}
            </p>
        </div>
    </div>

    <div class="p-4 sm:p-5 bg-zinc-50/30 dark:bg-zinc-900/30">

        <div class="mb-5 sm:mb-6 bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-zinc-200/60 dark:border-zinc-800 p-1 flex flex-col sm:flex-row gap-1">
            <button wire:click="$set('activeTab', 'history')" class="{{ $activeTab === 'history' ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 shadow-sm ring-1 ring-emerald-200 dark:ring-emerald-500/30' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white' }} relative flex-1 py-2 px-3 rounded-lg font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 transition-all">
                <flux:icon.clock class="w-4 h-4 {{ $activeTab === 'history' ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500' }}" /> 
                <span class="tracking-wide">Riwayat Custom</span>
                @if(count($historyItems) > 0)
                    <span class="absolute top-1.5 right-1.5 bg-emerald-500 dark:bg-emerald-400 text-white dark:text-zinc-900 text-[9px] w-3.5 h-3.5 sm:w-4 sm:h-4 flex items-center justify-center rounded-full font-bold">{{ count($historyItems) }}</span>
                @endif
            </button>
            <button wire:click="$set('activeTab', 'attributes')" class="{{ $activeTab === 'attributes' ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 shadow-sm ring-1 ring-emerald-200 dark:ring-emerald-500/30' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800 hover:text-zinc-900 dark:hover:text-white' }} relative flex-1 py-2 px-3 rounded-lg font-semibold text-xs sm:text-sm flex items-center justify-center gap-2 transition-all">
                <flux:icon.document-plus class="w-4 h-4 {{ $activeTab === 'attributes' ? 'text-emerald-500 dark:text-emerald-400' : 'text-zinc-400 dark:text-zinc-500' }}" /> 
                <span class="tracking-wide">Buat Baru (Spek & Gambar)</span>
                @if(count($existing_attachments) > 0 || count($new_attachments) > 0)
                    <span class="absolute top-1.5 right-1.5 bg-emerald-500 dark:bg-emerald-400 text-white dark:text-zinc-900 text-[9px] w-3.5 h-3.5 sm:w-4 sm:h-4 flex items-center justify-center rounded-full font-bold">{{ count($existing_attachments) + count($new_attachments) }}</span>
                @endif
            </button>
        </div>

        <div class="min-h-[250px] sm:min-h-[300px]">
            <!-- Tab Attributes & Notes -->
            @if($activeTab === 'attributes')
                <div class="space-y-5 sm:space-y-6">
                    <div>
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-2 gap-2">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Spesifikasi Custom Dinamis</label>
                            <flux:button size="sm" icon="plus" wire:click="addAttribute" class="w-full sm:w-auto">Tambah Spek Kosong</flux:button>
                        </div>

                        <div x-data="{ 
                            suggestions: $persist(['Panjang', 'Lebar', 'Tinggi', 'Warna', 'Bahan', 'Finishing', 'Motif Ukiran', 'Kain Dudukan']).as('customizer_attribute_suggestions'),
                            newSuggestion: '',
                            showInput: false,
                            addSuggestion() {
                                let val = this.newSuggestion.trim();
                                if (val && !this.suggestions.includes(val)) {
                                    this.suggestions.push(val);
                                }
                                this.newSuggestion = '';
                                this.showInput = false;
                            },
                            removeSuggestion(index) {
                                this.suggestions.splice(index, 1);
                            }
                        }" class="mb-4">
                            <div class="flex flex-wrap gap-1.5 items-center">
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 mr-1">Template Spek Cepat:</span>
                                <template x-for="(sug, index) in suggestions" :key="index">
                                    <div class="group relative flex items-center">
                                        <button type="button" @click="$wire.addSuggestedAttribute(sug)" class="text-[10px] sm:text-xs pl-2 pr-5 py-1 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md hover:bg-emerald-50 hover:text-emerald-700 dark:hover:bg-emerald-500/20 dark:hover:text-emerald-400 dark:hover:border-emerald-500/30 transition-colors text-zinc-600 dark:text-zinc-300 shadow-sm flex items-center gap-1">
                                            <flux:icon.plus class="w-3 h-3 shrink-0" /> <span x-text="sug" class="truncate max-w-[100px]"></span>
                                        </button>
                                        <button type="button" @click.stop="removeSuggestion(index)" class="absolute right-1 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-red-500 p-0.5 rounded-sm opacity-0 group-hover:opacity-100 transition-opacity" title="Hapus template ini">
                                            <flux:icon.x-mark class="w-3 h-3" />
                                        </button>
                                    </div>
                                </template>
                                
                                <button x-show="!showInput" @click="showInput = true; $nextTick(() => $refs.sugInput.focus())" type="button" class="text-[10px] sm:text-xs px-2 py-1 bg-zinc-50 dark:bg-zinc-800/50 border border-dashed border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors text-zinc-500 dark:text-zinc-400 flex items-center gap-1">
                                    <flux:icon.plus class="w-3 h-3" /> Tambah
                                </button>
                                
                                <div x-show="showInput" @click.away="showInput = false" style="display: none;" class="flex items-center gap-1">
                                    <input x-ref="sugInput" type="text" x-model="newSuggestion" @keydown.enter.prevent="addSuggestion()" placeholder="Nama spek..." class="text-xs px-2 py-1 bg-white dark:bg-zinc-900 border border-emerald-300 dark:border-emerald-600 rounded-md outline-none focus:ring-1 focus:ring-emerald-500 w-24 h-[26px] text-zinc-800 dark:text-white">
                                    <button type="button" @click="addSuggestion()" class="bg-emerald-500 text-white p-1 rounded-md hover:bg-emerald-600 h-[26px] w-[26px] flex items-center justify-center">
                                        <flux:icon.check class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        @if(empty($custom_attributes))
                            <div class="text-center py-6 sm:py-8 border-2 border-dashed border-emerald-200 dark:border-emerald-500/30 rounded-2xl bg-emerald-50/50 dark:bg-emerald-500/10 hover:bg-emerald-50 dark:hover:bg-emerald-500/20 transition-colors cursor-pointer" wire:click="addAttribute">
                                <div class="bg-white dark:bg-zinc-800 w-12 h-12 sm:w-14 sm:h-14 rounded-full shadow-sm border border-emerald-100 dark:border-emerald-500/30 flex items-center justify-center mx-auto mb-2 sm:mb-3">
                                    <flux:icon.plus class="w-5 h-5 sm:w-6 sm:h-6 text-emerald-500 dark:text-emerald-400" />
                                </div>
                                <h3 class="text-sm font-bold text-emerald-800 dark:text-emerald-400 mb-1">Mulai Kustomisasi</h3>
                                <p class="text-xs text-emerald-600/70 dark:text-emerald-400/70 px-4">Klik untuk menambahkan spesifikasi seperti warna, bahan, atau dimensi.</p>
                            </div>
                        @else
                            <div class="space-y-2 sm:space-y-3 bg-white dark:bg-zinc-900 p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm">
                                @foreach($custom_attributes as $idx => $attr)
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 group border-b border-zinc-100 dark:border-zinc-800 sm:border-0 pb-3 sm:pb-0 last:border-0 last:pb-0">
                                        <div class="hidden sm:flex w-8 h-8 rounded-full bg-zinc-100 dark:bg-zinc-800 text-zinc-400 dark:text-zinc-500 items-center justify-center font-bold text-xs shrink-0 border border-zinc-200 dark:border-zinc-700">
                                            {{ $idx + 1 }}
                                        </div>
                                        <div class="flex flex-col sm:flex-row flex-1 gap-2">
                                            <flux:input wire:model="custom_attributes.{{$idx}}.key" placeholder="Atribut (Warna, Bahan...)" class="!bg-zinc-50 dark:!bg-zinc-800 focus:!bg-white dark:focus:!bg-zinc-900 text-sm" />
                                            <flux:input wire:model="custom_attributes.{{$idx}}.value" placeholder="Nilai (Merah, Kayu Jati...)" class="!bg-zinc-50 dark:!bg-zinc-800 focus:!bg-white dark:focus:!bg-zinc-900 text-sm" />
                                        </div>
                                        <button wire:click="removeAttribute({{$idx}})" class="w-full sm:w-8 h-8 rounded-lg flex items-center justify-center text-red-500 bg-red-50 sm:bg-transparent sm:text-zinc-400 hover:bg-red-50 dark:bg-red-500/10 dark:hover:bg-red-500/20 hover:text-red-500 dark:hover:text-red-400 transition-colors shrink-0 mt-1 sm:mt-0" title="Hapus">
                                            <flux:icon.trash class="w-4 h-4 mr-1 sm:mr-0" /> <span class="text-xs sm:hidden">Hapus Baris Ini</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5 sm:mb-2">Catatan Bebas (Opsional)</label>
                        <flux:textarea wire:model="note" placeholder="Tulis catatan tambahan untuk bagian produksi atau pengiriman..." rows="2" class="text-sm" />
                    </div>
                    
                    <div class="pt-5 sm:pt-6 border-t border-zinc-200 dark:border-zinc-800">
                        <div class="mb-3 sm:mb-4 w-full sm:max-w-sm">
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5 sm:mb-2">Tambahkan Sketsa/Gambar (Opsional)</label>
                            <x-image-cropper wire:model="temp_attachment" id="customizer-cropper" label="Tambah Gambar" />
                            <div wire:loading wire:target="temp_attachment" class="text-xs sm:text-sm text-emerald-600 dark:text-emerald-400 mt-1.5">Sedang memproses...</div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mt-3 sm:mt-4">
                            {{-- Existing Attachments --}}
                            @foreach($existing_attachments as $idx => $path)
                                <div class="relative group rounded-xl overflow-hidden border border-zinc-200 dark:border-zinc-700 h-24 sm:h-32">
                                    <img src="{{ asset('storage/' . $path) }}" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-black/40 sm:bg-black/50 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <flux:button size="sm" variant="danger" icon="trash" wire:click="removeExistingAttachment({{$idx}})" class="!p-2" />
                                    </div>
                                </div>
                            @endforeach

                            {{-- New Uploads Preview --}}
                            @if($new_attachments)
                                @foreach($new_attachments as $idx => $path)
                                    <div class="relative group rounded-xl overflow-hidden border border-emerald-200 dark:border-emerald-500/30 ring-2 ring-emerald-500/20 h-24 sm:h-32">
                                        <img src="{{ asset('storage/' . $path) }}" class="w-full h-full object-cover" />
                                        <div class="absolute inset-0 bg-black/40 sm:bg-black/50 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <flux:button size="sm" variant="danger" icon="trash" wire:click="removeNewAttachment({{$idx}})" class="!p-2" />
                                        </div>
                                        <div class="absolute top-1.5 left-1.5 bg-emerald-500 dark:bg-emerald-400 text-white dark:text-zinc-900 text-[9px] px-1.5 py-0.5 rounded-full font-bold uppercase tracking-wider shadow-sm">
                                            Baru
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab History -->
            @if($activeTab === 'history')
                <div>
                    @if(empty($historyItems))
                        <div class="text-center py-8 sm:py-10 border border-dashed border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <flux:icon.archive-box class="w-10 h-10 sm:w-12 sm:h-12 text-zinc-300 dark:text-zinc-600 mx-auto mb-2 sm:mb-3" />
                            <h3 class="text-base sm:text-lg font-medium text-zinc-900 dark:text-white mb-1">Belum ada riwayat custom</h3>
                            <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 px-4">Barang ini belum pernah dibuat dengan spesifikasi custom sebelumnya.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            @foreach($historyItems as $history)
                                <div class="group bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 hover:border-emerald-300 dark:hover:border-emerald-500/50 rounded-xl sm:rounded-2xl shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden relative">
                                    {{-- Image Section (Top) --}}
                                    <div class="w-full h-28 sm:h-36 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center relative border-b border-zinc-200 dark:border-zinc-800">
                                        @if(count($history['attachments']) > 0)
                                            <img src="{{ asset('storage/' . $history['attachments'][0]) }}" class="w-full h-full object-cover absolute inset-0">
                                            @if(count($history['attachments']) > 1)
                                                <div class="absolute bottom-1.5 right-1.5 bg-black/60 text-white text-[9px] sm:text-[10px] font-bold px-1.5 py-0.5 rounded shadow-sm backdrop-blur-sm">
                                                    +{{ count($history['attachments']) - 1 }} Foto
                                                </div>
                                            @endif
                                        @elseif(!empty($history['master_image']))
                                            <img src="{{ asset('storage/' . $history['master_image']) }}" class="w-full h-full object-contain absolute inset-0 p-3 sm:p-4 opacity-50 dark:opacity-30 mix-blend-multiply dark:mix-blend-normal">
                                        @else
                                            <flux:icon.cube class="w-10 h-10 sm:w-12 sm:h-12 text-zinc-300 dark:text-zinc-600" />
                                        @endif
                                        
                                        {{-- Document Badge floating on top left --}}
                                        <div class="absolute top-1.5 left-1.5 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-sm px-1.5 py-0.5 rounded-md text-[9px] sm:text-[10px] font-bold border border-zinc-200 dark:border-zinc-700 shadow-sm flex items-center gap-1 {{ $history['type'] === 'PO' ? 'text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30' : 'text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/30' }}">
                                            <flux:icon.document-text class="w-2.5 h-2.5 sm:w-3 sm:h-3 {{ $history['type'] === 'PO' ? 'text-emerald-500 dark:text-emerald-400' : 'text-indigo-500 dark:text-indigo-400' }}" />
                                            {{ $history['reference'] }}
                                        </div>
                                    </div>

                                    {{-- Content Section (Bottom) --}}
                                    <div class="p-3 sm:p-4 flex flex-col flex-1">
                                        <div class="flex flex-wrap items-center justify-between mb-2 sm:mb-3 border-b border-zinc-100 dark:border-zinc-800 pb-2 sm:pb-3 gap-1 sm:gap-2">
                                            <span class="text-[9px] sm:text-[10px] font-medium text-zinc-400 dark:text-zinc-500 flex items-center gap-1 shrink-0"><flux:icon.calendar class="w-2.5 h-2.5 sm:w-3 sm:h-3" /> {{ $history['date'] }}</span>
                                            <div class="flex items-center gap-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 px-1.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-semibold border border-indigo-100 dark:border-indigo-500/20 truncate min-w-0" title="{{ $history['customer'] }}">
                                                <flux:icon.user class="w-2.5 h-2.5 sm:w-3 sm:h-3 shrink-0" />
                                                <span class="truncate">{{ $history['customer'] }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-col gap-1.5 mb-3 sm:mb-4 flex-1">
                                            @foreach($history['attributes'] as $attr)
                                                <div class="text-[10px] sm:text-[11px] flex justify-between bg-zinc-50 dark:bg-zinc-800/50 px-1.5 sm:px-2 py-1 sm:py-1.5 rounded border border-zinc-100 dark:border-zinc-700">
                                                    <span class="text-zinc-500 dark:text-zinc-400 shrink-0 mr-2">{{ $attr['key'] }}</span> 
                                                    <span class="font-bold text-zinc-700 dark:text-zinc-200 text-right break-words">{{ $attr['value'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <flux:button variant="primary" size="sm" icon="document-duplicate" wire:click="reuseHistory('{{$history['id']}}')" class="w-full !bg-emerald-600 hover:!bg-emerald-700 dark:!bg-emerald-500 dark:hover:!bg-emerald-600 !border-emerald-700 dark:!border-emerald-500 mt-auto text-xs sm:text-sm h-8 sm:h-9">Gunakan Spek Ini</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-5 sm:mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button variant="subtle" @click="$flux.modal('item-customizer-modal').close()" class="w-full sm:w-auto text-sm">Batal</flux:button>
            <flux:button variant="primary" icon="check" wire:click="save" wire:target="save" wire:loading.attr="disabled" class="w-full sm:w-auto !bg-emerald-600 hover:!bg-emerald-700 dark:!bg-emerald-500 dark:hover:!bg-emerald-600 !border-emerald-700 dark:!border-emerald-500 text-sm">Simpan Spesifikasi</flux:button>
        </div>
    </div>
</flux:modal>
