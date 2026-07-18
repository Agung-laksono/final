@php
    $isCustom = str_contains($order->notes ?? '', '[CUSTOM]');
@endphp
<div @click="activeId = '{{ isset($hideParentPo) && $hideParentPo ? 'po-'.$hideParentPo : $order->id }}'" 
     x-show="processingId !== '{{ isset($hideParentPo) && $hideParentPo ? 'po-'.$hideParentPo : $order->id }}'"
     class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border transition-all duration-300 group relative overflow-hidden {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-1 hover:shadow-lg hover:shadow-amber-500/30 hover:border-amber-500' : 'border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500' }}">
    @if($isCustom)
        <div class="absolute top-0 right-0 w-24 h-24 pointer-events-none opacity-40 dark:opacity-20">
            <div class="absolute inset-0 bg-gradient-to-bl from-amber-400 to-transparent"></div>
        </div>
    @endif
    <div class="flex justify-between items-start mb-2 relative z-10">
        <div class="flex flex-col gap-1.5">
            <span wire:click="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })" class="cursor-pointer text-xs font-mono bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded w-max hover:bg-blue-100 hover:text-blue-700 transition-colors">
                {{ $order->order_number }}
            </span>
            @if($isCustom)
                <span class="text-[10px] font-black uppercase tracking-widest text-white bg-gradient-to-r from-amber-500 to-orange-500 px-2 py-0.5 rounded shadow-sm shadow-amber-500/40 flex items-center gap-1 w-max" title="Pesanan dengan permintaan spesifikasi khusus">
                    <flux:icon.sparkles class="w-3 h-3" /> Custom
                </span>
            @endif
            @if($order->status === 'waiting_material')
                <span class="text-[10px] font-semibold text-red-600 dark:text-red-400 flex items-center gap-1">
                    <flux:icon.exclamation-triangle class="w-3 h-3" /> Kurang Bahan
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-zinc-500">{{ $order->created_at->format('d M') }}</span>
        </div>
    </div>
    <div class="flex items-start gap-2 relative z-10">
        @if($statusKey === 'waiting_vendor')
            <div class="pt-0.5">
                <flux:checkbox wire:click="toggleSelection({{ $order->id }})" :checked="in_array($order->id, $this->selectedOrders)" />
            </div>
        @endif
        <div class="font-bold text-sm text-zinc-900 dark:text-white mb-1">{{ $order->item->name }}</div>
    </div>
    <div class="flex gap-2 text-sm text-zinc-600 dark:text-zinc-400 mb-3 relative z-10">
        <div>Target: <span class="font-bold text-blue-600">{{ $order->requested_qty }}</span></div>
        @if($order->fulfilled_qty > 0)
            <div>Selesai: <span class="font-bold text-emerald-600">{{ $order->fulfilled_qty }}</span></div>
        @endif
    </div>
    
    @if($order->reference_number)
        <div class="text-xs text-zinc-500 flex items-center gap-1 mb-2">
            <flux:icon.link class="w-3 h-3" /> Ref: {{ $order->reference_number }}
        </div>
    @endif
    @if($order->purchaseOrder && $statusKey !== 'waiting_vendor' && ($statusKey !== 'in_production' || ($viewModeMaklon ?? 'list') === 'list'))
        <div class="text-xs text-zinc-500 flex items-center gap-1 mb-2">
            <span class="hover:text-blue-600 cursor-pointer flex items-center gap-1" wire:click="$dispatch('open-po-detail-modal', { poId: {{ $order->purchase_order_id }} })">
                <flux:icon.truck class="w-3 h-3" /> PO: {{ $order->purchaseOrder->po_number }}
            </span>
            @if(count($order->completed_phases) > 0)
                <span class="text-[10px] text-emerald-600 font-semibold bg-emerald-50 dark:bg-emerald-900/30 px-1 rounded border border-emerald-200 dark:border-emerald-800">
                    Ada Riwayat Jasa Luar
                </span>
            @endif
        </div>
    @endif
    @if(count($order->completed_phases) > 0)
        <div class="mt-2 mb-3 bg-zinc-50 dark:bg-zinc-800/40 p-2 rounded-lg border border-zinc-100 dark:border-zinc-800 text-[10px] text-zinc-500 flex justify-between items-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800" wire:click="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })">
            <div class="flex items-center gap-1 font-medium">
                <flux:icon.clock class="w-3 h-3 text-emerald-500" /> {{ count($order->completed_phases) }} Riwayat Fase
            </div>
            <span class="text-blue-600 hover:underline">Lihat Detail &rarr;</span>
        </div>
    @endif
    @if($order->phase_type)
        <div class="mb-2 flex">
            <flux:badge size="sm" color="{{ $order->phase_color }}">
                {{ ucfirst($order->phase_type) }}
            </flux:badge>
        </div>
    @endif

    @if(isset($this->validationErrors[$order->id]))
        <div class="mb-3 bg-red-50 dark:bg-red-900/30 p-2.5 rounded-lg border border-red-200 dark:border-red-800 text-[11px] text-red-600 dark:text-red-400 leading-snug">
            <flux:icon.exclamation-triangle class="w-3.5 h-3.5 inline mr-1 -mt-0.5" />
            <span class="font-semibold">{{ $this->validationErrors[$order->id] }}</span>
        </div>
    @endif

    <div class="flex flex-col gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
        @if($statusKey === 'material_fulfillment')
            @if($order->status === 'waiting_material' || $order->status === 'material_fulfillment')
                <div class="text-center text-[11px] font-medium text-zinc-500 bg-zinc-50 dark:bg-zinc-800/50 py-2 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <flux:icon.clock class="w-3.5 h-3.5 inline mr-1 -mt-0.5" /> Menunggu Penyiapan Bahan di Gudang...
                </div>
            @elseif($order->status === 'material_issued')
                <div class="text-xs text-zinc-500 mb-2 leading-tight">Bahan telah diserahkan oleh Gudang. Silakan periksa kelengkapan fisik bahan.</div>
                <flux:button size="sm" variant="primary" wire:click="$dispatch('open-material-receipt-modal', { orderId: {{ $order->id }} })" class="w-full justify-center" icon="check-circle">Validasi & Terima Bahan</flux:button>

            @endif
        @elseif($statusKey === 'waiting_vendor')
            <div class="flex flex-col gap-2">
                <div class="flex gap-2">
                    <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'material_fulfillment')" class="flex-1 justify-center text-zinc-500">Batal Antrean</flux:button>
                </div>
            </div>
        @elseif($statusKey === 'in_production')
            <div class="flex gap-2">
                <flux:button size="sm" variant="primary" wire:click="$dispatch('open-finish-phase-modal', { orderId: {{ $order->id }}, phase: 'maklon' })" class="w-full justify-center">Selesai</flux:button>
            </div>
        @elseif($statusKey === 'receiving')
            <div class="text-xs text-center text-zinc-500 font-medium py-1.5 border border-dashed border-zinc-300 dark:border-zinc-700 rounded-lg">
                <flux:icon.clock class="w-3.5 h-3.5 inline mr-1 -mt-0.5" /> Menunggu Validasi Gudang
            </div>
        @elseif($statusKey === 'completed')
            <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'archived')" class="w-full justify-center text-zinc-500">Arsipkan</flux:button>
        @endif
    </div>
</div>
