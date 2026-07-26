<?php

use Livewire\Volt\Component;
use Modules\Inventory\Models\InventoryRequest;
use Livewire\Attributes\On;

new class extends Component {
    public $requestId = null;
    public $request = null;
    public $customOrderItem = null;
    public $show = false;

    #[On('open-request-detail-modal')]
    public function loadRequest($requestId)
    {
        $this->requestId = $requestId;
        $this->request = InventoryRequest::with(['item', 'item.type', 'item.unit', 'createdBy'])->find($requestId);
        
        $this->customOrderItem = null;
        if ($this->request && $this->request->reference_number && str_starts_with($this->request->reference_number, 'ODM-')) {
            $so = \Modules\Sales\Models\SalesOrder::where('so_number', $this->request->reference_number)->first();
            if ($so) {
                $this->customOrderItem = \Modules\Sales\Models\SalesOrderItem::where('sales_order_id', $so->id)
                    ->where('item_id', $this->request->item_id)
                    ->where(function($q) {
                        $q->whereNotNull('custom_attributes')->orWhereNotNull('custom_attachments');
                    })
                    ->first();
            }
        }
        
        $this->show = true;
    }
};
?>

<div x-data="{ lightboxOpen: false, lightboxImg: '' }">
<flux:modal wire:model="show" class="md:w-[600px]">
    @if($request)
    <div class="space-y-6">
        <div class="flex justify-between items-start">
            <div>
                <flux:heading size="lg">Detail Permintaan</flux:heading>
                <flux:subheading>Informasi lengkap terkait tiket permintaan barang.</flux:subheading>
            </div>
            <flux:badge size="md" color="{{ $request->status === 'draft' ? 'amber' : ($request->status === 'routed' ? 'emerald' : 'zinc') }}">{{ strtoupper($request->status) }}</flux:badge>
        </div>

        <div class="grid grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-xs text-zinc-500 mb-1">Nomor Referensi</div>
                <div class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $request->reference_number }}</div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 mb-1">Dibuat Oleh</div>
                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $request->createdBy->name ?? 'Sistem / Auto' }}</div>
            </div>
            <div class="col-span-2">
                <div class="text-xs text-zinc-500 mb-1">Nama Barang</div>
                <div class="font-bold text-base text-blue-600 dark:text-blue-400">{{ $request->item->name }}</div>
                <div class="text-xs text-zinc-500 mt-0.5">Tipe: {{ $request->item->type->name ?? '-' }} &middot; Kode: {{ $request->item->code ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 mb-1">Kuantitas Dibutuhkan</div>
                <div class="font-black text-lg text-red-600 dark:text-red-400">{{ $request->requested_qty }} <span class="text-sm font-medium">{{ $request->item->unit->name ?? 'pcs' }}</span></div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 mb-1">Tanggal Dibuat</div>
                <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $request->created_at->format('d M Y, H:i') }}</div>
            </div>
            @if($request->notes)
            <div class="col-span-2">
                <div class="text-xs text-zinc-500 mb-1">Catatan Tambahan</div>
                <div class="text-sm text-zinc-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="prose prose-sm max-w-none">{!! $request->notes !!}</div>
                </div>
            </div>
            @endif
        </div>
        
        @if($customOrderItem)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50 rounded-xl p-4 shadow-sm relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <flux:icon.star class="w-24 h-24 text-amber-500" />
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <flux:icon.sparkles class="w-4 h-4" />
                    </div>
                    <flux:heading size="md" class="!text-amber-800 dark:!text-amber-400">Permintaan Pesanan Custom</flux:heading>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if(!empty($customOrderItem->custom_attributes))
                        <div>
                            <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-2">Spesifikasi Khusus</h4>
                            <div class="bg-white/60 dark:bg-zinc-900/40 rounded-lg p-3 space-y-2 border border-amber-100 dark:border-amber-800/30">
                                @foreach($customOrderItem->custom_attributes as $attr)
                                    <div class="flex justify-between items-start text-sm">
                                        <span class="text-zinc-500 font-medium">{{ $attr['key'] ?? '-' }}</span>
                                        <span class="text-zinc-900 dark:text-zinc-100 font-bold text-right">{{ $attr['value'] ?? '-' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if(!empty($customOrderItem->custom_attachments))
                        <div>
                            <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-2">Lampiran / Sketsa</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                @foreach($customOrderItem->custom_attachments as $idx => $path)
                                    <div @click="lightboxImg = '{{ asset('storage/' . $path) }}'; lightboxOpen = true" class="cursor-pointer block relative group rounded-lg overflow-hidden border border-amber-200 dark:border-amber-700/50 aspect-square">
                                        <img src="{{ asset('storage/' . $path) }}" class="w-full h-full object-cover transition-transform group-hover:scale-110" />
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <flux:icon.arrows-pointing-out class="w-5 h-5 text-white" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
                
                @if($customOrderItem->notes)
                    <div class="mt-4 pt-3 border-t border-amber-200/60 dark:border-amber-800/60">
                        <h4 class="text-xs font-bold text-amber-700 dark:text-amber-500 uppercase tracking-wider mb-1">Catatan Tambahan Sales</h4>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300 italic prose prose-sm prose-p:my-0">{!! $customOrderItem->notes !!}</div>
                    </div>
                @endif
                
                <div class="mt-4 p-3 bg-amber-100/50 dark:bg-amber-900/30 rounded-lg border border-amber-200/50 dark:border-amber-800/50 text-xs text-amber-800 dark:text-amber-400">
                    <strong class="block mb-1">PERHATIAN UNTUK KEPALA GUDANG:</strong>
                    Barang ini memiliki permintaan kustomisasi. Jika Anda memilih tombol <strong>"Produksi"</strong>, resep standar (BOM Master) akan otomatis digunakan. Pastikan modifikasi bahan sudah diantisipasi secara manual oleh tim produksi.
                </div>
            </div>
        </div>
        @endif

        {{-- Mini Dashboard (Pipeline Stock) --}}
        @php
            $stats = $request->item->getInventoryStats();
        @endphp
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <div class="bg-zinc-50 dark:bg-zinc-800/50 px-4 py-2 border-b border-zinc-200 dark:border-zinc-700">
                <div class="text-xs font-bold text-zinc-500 uppercase tracking-wider">Kondisi Stok Real-Time</div>
            </div>
            <div class="p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="flex flex-col">
                    <span class="text-[10px] text-zinc-500 uppercase font-bold tracking-wider mb-1">Stok Fisik</span>
                    <span class="text-lg font-black text-zinc-800 dark:text-zinc-200">{{ $stats['physical'] }} <span class="text-xs font-medium text-zinc-400">{{ $request->item->unit->name ?? 'pcs' }}</span></span>
                    @if(isset($stats['warehouse_details']) && count($stats['warehouse_details']) > 0)
                        <div class="flex flex-col gap-1 mt-2">
                            @foreach($stats['warehouse_details'] as $wh)
                                @if($wh['stock'] > 0)
                                    <span class="text-[10px] text-zinc-500 flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 rounded-full bg-zinc-400"></div>
                                        <span class="truncate" title="{{ $wh['name'] }}">{{ $wh['name'] }}</span>: <span class="font-bold text-zinc-700 dark:text-zinc-300">{{ $wh['stock'] }}</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-purple-500 uppercase font-bold tracking-wider mb-1">Sedang Diproduksi</span>
                    <span class="text-lg font-black text-purple-600 dark:text-purple-400">{{ $stats['production'] }} <span class="text-xs font-medium text-purple-400/70">{{ $request->item->unit->name ?? 'pcs' }}</span></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-amber-500 uppercase font-bold tracking-wider mb-1">Antre Beli (PR)</span>
                    <span class="text-lg font-black text-amber-600 dark:text-amber-400">{{ $stats['purchase_queue'] }} <span class="text-xs font-medium text-amber-400/70">{{ $request->item->unit->name ?? 'pcs' }}</span></span>
                </div>
                <div class="flex flex-col">
                    <span class="text-[10px] text-blue-500 uppercase font-bold tracking-wider mb-1">Sedang Beli (PO)</span>
                    <span class="text-lg font-black text-blue-600 dark:text-blue-400">{{ $stats['purchase_order'] }} <span class="text-xs font-medium text-blue-400/70">{{ $request->item->unit->name ?? 'pcs' }}</span></span>
                </div>
            </div>
            <div class="bg-red-50 dark:bg-red-900/10 px-4 py-3 border-t border-red-100 dark:border-red-900/30 flex justify-between items-center">
                <span class="text-[10px] text-red-600 dark:text-red-400 uppercase font-bold tracking-wider">Kebutuhan / Pesanan Penjualan</span>
                <span class="text-base font-black text-red-600 dark:text-red-400">{{ $stats['sales'] ?? 0 }} <span class="text-xs font-medium text-red-400/70">{{ $request->item->unit->name ?? 'pcs' }}</span></span>
            </div>
        </div>
        
        @if($request->status === 'routed')
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4 rounded-xl">
            <div class="flex items-start gap-3">
                <flux:icon.check-circle class="w-5 h-5 text-emerald-500 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold text-sm text-emerald-800 dark:text-emerald-300">Tiket Telah Dialihkan</div>
                    <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                        Permintaan ini telah disetujui dan dialihkan ke rute <strong>{{ strtoupper($request->routed_to) }}</strong> oleh sistem.
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="flex justify-end pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <flux:button variant="ghost" wire:click="$set('show', false)"> Tutup </flux:button>
        </div>
    </div>
    @else
    <div class="py-8 text-center">
        <flux:icon.arrow-path class="w-8 h-8 animate-spin mx-auto text-zinc-400" />
    </div>
    @endif
</flux:modal>

    <!-- Image Lightbox -->
    <template x-teleport="body">
        <div x-show="lightboxOpen" x-transition.opacity class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 p-4 backdrop-blur-sm" style="display: none;">
            <button @click="lightboxOpen = false" class="absolute top-6 right-6 text-white hover:text-amber-400 bg-black/50 hover:bg-black/80 rounded-full p-2 transition-colors">
                <flux:icon.x-mark class="w-8 h-8" />
            </button>
            <img :src="lightboxImg" class="max-w-[95vw] max-h-[95vh] object-contain rounded-lg shadow-2xl" @click.away="lightboxOpen = false" />
        </div>
    </template>
</div>
