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

<flux:modal name="item-customizer-modal" class="md:w-3/4 max-w-4xl" @customizer-saved.window="$flux.modal('item-customizer-modal').close()">
    <!-- Header -->
    <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-start gap-4">
        <div class="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0">
            <flux:icon.adjustments-horizontal class="w-5 h-5 text-emerald-600" />
        </div>
        <div>
            <h2 class="text-xl font-bold text-slate-800">Customisasi Spesifikasi</h2>
            <p class="text-sm text-slate-500 flex items-center gap-1.5 mt-0.5">
                <flux:icon.cube class="w-3.5 h-3.5 text-slate-400" />
                {{ $itemName }}
            </p>
        </div>
    </div>

    <div class="p-6 bg-slate-50/30">

        <div class="mb-6 bg-white rounded-xl shadow-sm border border-slate-200/60 p-1.5 flex gap-1 overflow-x-auto custom-scrollbar">
            <button wire:click="$set('activeTab', 'history')" class="{{ $activeTab === 'history' ? 'bg-emerald-50 text-emerald-700 shadow-sm ring-1 ring-emerald-200' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} relative flex-1 min-w-[150px] py-2.5 px-4 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 transition-all">
                <flux:icon.clock class="w-4 h-4 {{ $activeTab === 'history' ? 'text-emerald-500' : 'text-slate-400' }}" /> 
                <span class="tracking-wide">Riwayat Custom</span>
                @if(count($historyItems) > 0)
                    <span class="absolute top-2 right-2 bg-emerald-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full font-bold">{{ count($historyItems) }}</span>
                @endif
            </button>
            <button wire:click="$set('activeTab', 'attributes')" class="{{ $activeTab === 'attributes' ? 'bg-emerald-50 text-emerald-700 shadow-sm ring-1 ring-emerald-200' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} relative flex-1 min-w-[150px] py-2.5 px-4 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 transition-all">
                <flux:icon.document-plus class="w-4 h-4 {{ $activeTab === 'attributes' ? 'text-emerald-500' : 'text-slate-400' }}" /> 
                <span class="tracking-wide">Buat Baru (Spek & Gambar)</span>
                @if(count($existing_attachments) > 0 || count($new_attachments) > 0)
                    <span class="absolute top-2 right-2 bg-emerald-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full font-bold">{{ count($existing_attachments) + count($new_attachments) }}</span>
                @endif
            </button>
        </div>

        <div class="min-h-[300px]">
            <!-- Tab Attributes & Notes -->
            @if($activeTab === 'attributes')
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-slate-700">Spesifikasi Custom Dinamis</label>
                            <flux:button size="sm" icon="plus" wire:click="addAttribute">Tambah Spek</flux:button>
                        </div>
                        
                        @if(empty($custom_attributes))
                            <div class="text-center py-10 border-2 border-dashed border-emerald-200 rounded-2xl bg-emerald-50/50 hover:bg-emerald-50 transition-colors cursor-pointer" wire:click="addAttribute">
                                <div class="bg-white w-14 h-14 rounded-full shadow-sm border border-emerald-100 flex items-center justify-center mx-auto mb-3">
                                    <flux:icon.plus class="w-6 h-6 text-emerald-500" />
                                </div>
                                <h3 class="text-sm font-bold text-emerald-800 mb-1">Mulai Kustomisasi</h3>
                                <p class="text-xs text-emerald-600/70">Klik untuk menambahkan spesifikasi seperti warna, bahan, atau dimensi.</p>
                            </div>
                        @else
                            <div class="space-y-3 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                                @foreach($custom_attributes as $idx => $attr)
                                    <div class="flex items-center gap-3 group">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center font-bold text-xs shrink-0 border border-slate-200">
                                            {{ $idx + 1 }}
                                        </div>
                                        <div class="flex-1 grid grid-cols-2 gap-3 relative">
                                            <flux:input wire:model="custom_attributes.{{$idx}}.key" placeholder="Atribut (Warna, Bahan...)" class="!bg-slate-50 focus:!bg-white" />
                                            <flux:input wire:model="custom_attributes.{{$idx}}.value" placeholder="Nilai (Merah, Kayu Jati...)" class="!bg-slate-50 focus:!bg-white" />
                                        </div>
                                        <button wire:click="removeAttribute({{$idx}})" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition-colors shrink-0" title="Hapus">
                                            <flux:icon.trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Catatan Bebas (Opsional)</label>
                        <flux:textarea wire:model="note" placeholder="Tulis catatan tambahan untuk bagian produksi atau pengiriman..." rows="3" />
                    </div>
                    
                    <div class="pt-6 border-t border-slate-200">
                        <div class="mb-4 max-w-sm">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Tambahkan Sketsa/Gambar (Opsional)</label>
                            <x-image-cropper wire:model="temp_attachment" id="customizer-cropper" label="Tambah Gambar" />
                            <div wire:loading wire:target="temp_attachment" class="text-sm text-emerald-600 mt-2">Sedang memproses...</div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                            {{-- Existing Attachments --}}
                            @foreach($existing_attachments as $idx => $path)
                                <div class="relative group rounded-xl overflow-hidden border border-slate-200">
                                    <img src="{{ asset('storage/' . $path) }}" class="w-full h-32 object-cover" />
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                        <flux:button size="sm" variant="danger" icon="trash" wire:click="removeExistingAttachment({{$idx}})" />
                                    </div>
                                </div>
                            @endforeach

                            {{-- New Uploads Preview --}}
                            @if($new_attachments)
                                @foreach($new_attachments as $idx => $path)
                                    <div class="relative group rounded-xl overflow-hidden border border-emerald-200 ring-2 ring-emerald-500/20">
                                        <img src="{{ asset('storage/' . $path) }}" class="w-full h-32 object-cover" />
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <flux:button size="sm" variant="danger" icon="trash" wire:click="removeNewAttachment({{$idx}})" />
                                        </div>
                                        <div class="absolute top-2 left-2 bg-emerald-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">
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
                        <div class="text-center py-10">
                            <flux:icon.archive-box class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                            <h3 class="text-lg font-medium text-slate-900 mb-1">Belum ada riwayat custom</h3>
                            <p class="text-sm text-slate-500">Barang ini belum pernah dibuat dengan spesifikasi custom sebelumnya.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($historyItems as $history)
                                <div class="group bg-white border border-slate-200 hover:border-emerald-300 rounded-2xl shadow-sm hover:shadow-md transition-all flex flex-col overflow-hidden relative">
                                    {{-- Image Section (Top) --}}
                                    <div class="w-full h-32 sm:h-40 bg-slate-100 flex items-center justify-center relative border-b border-slate-200">
                                        @if(count($history['attachments']) > 0)
                                            <img src="{{ asset('storage/' . $history['attachments'][0]) }}" class="w-full h-full object-cover absolute inset-0">
                                            @if(count($history['attachments']) > 1)
                                                <div class="absolute bottom-2 right-2 bg-black/60 text-white text-[10px] font-bold px-2 py-1 rounded shadow-sm backdrop-blur-sm">
                                                    +{{ count($history['attachments']) - 1 }} Foto
                                                </div>
                                            @endif
                                        @elseif(!empty($history['master_image']))
                                            <img src="{{ asset('storage/' . $history['master_image']) }}" class="w-full h-full object-contain absolute inset-0 p-4 opacity-50 mix-blend-multiply">
                                        @else
                                            <flux:icon.cube class="w-12 h-12 text-slate-300" />
                                        @endif
                                        
                                        {{-- Document Badge floating on top left --}}
                                        <div class="absolute top-2 left-2 bg-white/95 backdrop-blur-sm px-2 py-0.5 rounded-md text-[10px] font-bold border border-slate-200 shadow-sm flex items-center gap-1" :class="'{{ $history['type'] }}' === 'PO' ? 'text-emerald-700 border-emerald-200' : 'text-indigo-700 border-indigo-200'">
                                            <flux:icon.document-text class="w-3 h-3 {{ $history['type'] === 'PO' ? 'text-emerald-500' : 'text-indigo-500' }}" />
                                            {{ $history['reference'] }}
                                        </div>
                                    </div>

                                    {{-- Content Section (Bottom) --}}
                                    <div class="p-4 flex flex-col flex-1">
                                        <div class="flex items-center justify-between mb-3 border-b border-slate-100 pb-3">
                                            <span class="text-[10px] font-medium text-slate-400 flex items-center gap-1"><flux:icon.calendar class="w-3 h-3" /> {{ $history['date'] }}</span>
                                            <div class="flex items-center gap-1 bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-indigo-100 truncate max-w-[100px]" title="{{ $history['customer'] }}">
                                                <flux:icon.user class="w-3 h-3 shrink-0" />
                                                <span class="truncate">{{ $history['customer'] }}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex flex-col gap-2 mb-4 flex-1">
                                            @foreach($history['attributes'] as $attr)
                                                <div class="text-[11px] flex justify-between bg-slate-50 px-2 py-1.5 rounded border border-slate-100">
                                                    <span class="text-slate-500">{{ $attr['key'] }}</span> 
                                                    <span class="font-bold text-slate-700 text-right break-words max-w-[60%]">{{ $attr['value'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <flux:button variant="primary" size="sm" icon="document-duplicate" wire:click="reuseHistory('{{$history['id']}}')" class="w-full !bg-emerald-600 hover:!bg-emerald-700 !border-emerald-700 mt-auto">Gunakan Spesifikasi Ini</flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-200">
            <flux:button variant="subtle" @click="$flux.modal('item-customizer-modal').close()">Batal</flux:button>
            <flux:button variant="primary" icon="check" wire:click="save" wire:target="save" wire:loading.attr="disabled" class="!bg-emerald-600 hover:!bg-emerald-700 !border-emerald-700">Simpan Spesifikasi</flux:button>
        </div>
    </div>
</flux:modal>
