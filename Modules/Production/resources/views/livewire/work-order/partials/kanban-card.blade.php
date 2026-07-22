@php
    $isCustom = str_contains($order->notes ?? '', '[CUSTOM]');
@endphp
<div x-data="{ showFooter: false }"
     @click="
         activeId = '{{ isset($hideParentPo) && $hideParentPo ? 'po-'.$hideParentPo : $order->id }}';
         if (!window.matchMedia('(hover: hover)').matches) {
             showFooter = !showFooter;
         }
     "
     @click.outside="showFooter = false"
     x-show="processingId !== '{{ isset($hideParentPo) && $hideParentPo ? 'po-'.$hideParentPo : $order->id }}'"
     class="bg-white dark:bg-zinc-800 p-2 rounded-lg shadow-sm border transition-all duration-200 active:scale-[0.98] active:shadow-none group relative flex flex-col overflow-hidden {{ $isCustom ? 'border-amber-400 dark:border-amber-500 shadow-amber-500/20 hover:-translate-y-0.5 hover:shadow-amber-500/30 hover:border-amber-500' : 'border-zinc-200 dark:border-zinc-700 hover:-translate-y-0.5 hover:shadow-sm hover:border-blue-400 dark:hover:border-blue-500' }}">
    @if($isCustom)
        <div class="absolute top-0 right-0 w-24 h-24 pointer-events-none opacity-40 dark:opacity-20">
            <div class="absolute inset-0 bg-gradient-to-bl from-amber-400 to-transparent"></div>
        </div>
    @endif
    <div class="flex justify-between items-center relative z-10">
        <div class="flex items-center gap-1.5 flex-wrap">
            <span wire:click="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })" class="font-mono text-[9px] font-bold text-zinc-600 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-700/50 border border-zinc-200 dark:border-zinc-600 px-1 py-px rounded w-max cursor-pointer hover:bg-blue-100 hover:text-blue-700 transition-colors">
                {{ $order->order_number }}
            </span>
            @if($isCustom)
                <span class="text-[8px] font-black uppercase text-amber-600 bg-amber-100 border border-amber-200 px-1 py-px rounded shadow-sm flex items-center gap-0.5 w-max" title="Pesanan Custom">
                    <flux:icon.sparkles class="w-2 h-2" /> Custom
                </span>
            @endif
            @if($order->status === 'waiting_material')
                <span class="text-[8px] font-semibold text-red-600 dark:text-red-400 flex items-center gap-0.5 bg-red-50 dark:bg-red-900/30 px-1 py-px rounded border border-red-200 dark:border-red-800">
                    <flux:icon.exclamation-triangle class="w-2 h-2" /> Krg Bahan
                </span>
            @elseif($statusKey === 'material_fulfillment' && $order->status === 'material_fulfillment')
                <span class="text-[8px] font-semibold text-blue-600 dark:text-blue-400 flex items-center gap-0.5 bg-blue-50 dark:bg-blue-900/30 px-1 py-px rounded border border-blue-200 dark:border-blue-800">
                    <flux:icon.clock class="w-2 h-2" /> Tggu Gudang
                </span>
            @endif
            @if($statusKey === 'receiving')
                <span class="text-[8px] font-semibold text-purple-600 dark:text-purple-400 flex items-center gap-0.5 bg-purple-50 dark:bg-purple-900/30 px-1 py-px rounded border border-purple-200 dark:border-purple-800">
                    <flux:icon.clock class="w-2 h-2" /> Tggu Gudang (Validasi)
                </span>
            @endif
        </div>
        <div class="flex items-center gap-1 text-[8px] text-zinc-500 dark:text-zinc-400 font-medium">
            <flux:icon.calendar class="w-2 h-2" /> {{ $order->created_at->format('d M') }}
        </div>
    </div>
    <div class="grid grid-cols-2 gap-2 items-center mt-1">
        <div class="flex items-center gap-1.5 overflow-hidden">
            @if($statusKey === 'waiting_vendor')
                <div class="shrink-0">
                    <flux:checkbox wire:click="toggleSelection({{ $order->id }})" :checked="in_array($order->id, $this->selectedOrders)" />
                </div>
            @endif
            <span class="font-bold text-[10px] text-zinc-800 dark:text-zinc-100 leading-tight line-clamp-2" title="{{ $order->item->name }}">
                {{ $order->item->name }}
            </span>
        </div>

        @php
            $target = $order->requested_qty;
            $selesai = $order->fulfilled_qty ?? 0;
            $progressPercent = $target > 0 ? min(100, round(($selesai / $target) * 100)) : 0;
        @endphp
        
        <div class="flex flex-col gap-1.5 border-l border-zinc-100 dark:border-zinc-800 pl-2">
            <div>
                <div class="flex justify-between items-end mb-0.5">
                    <span class="text-[7px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-0.5">
                        <flux:icon.cube class="w-1.5 h-1.5" /> PROGRES
                    </span>
                    <span class="text-[7px] font-semibold {{ $progressPercent >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-200' }}">
                        {{ $selesai }}/{{ $target }}
                    </span>
                </div>
                <div class="w-full h-1 bg-zinc-100 dark:bg-zinc-700/50 rounded-full overflow-hidden">
                    <div class="h-full {{ $progressPercent >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }} rounded-full" style="width: {{ $progressPercent }}%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-1 flex flex-col gap-1">
        @if($order->reference_number)
            <div class="text-[10px] text-zinc-500 flex items-center gap-1">
                <flux:icon.link class="w-2.5 h-2.5" /> Ref: {{ $order->reference_number }}
            </div>
        @endif
        @if($order->purchaseOrder && $statusKey !== 'waiting_vendor' && ($statusKey !== 'in_production' || ($viewModeMaklon ?? 'list') === 'list'))
            <div class="text-[10px] text-zinc-500 flex items-center gap-1 flex-wrap">
                <span class="hover:text-blue-600 cursor-pointer flex items-center gap-1" wire:click="$dispatch('open-po-detail-modal', { poId: {{ $order->purchase_order_id }} })">
                    <flux:icon.truck class="w-2.5 h-2.5" /> PO: {{ $order->purchaseOrder->po_number }}
                </span>
                @if(count($order->completed_phases) > 0)
                    <span class="text-[8px] text-emerald-600 font-semibold bg-emerald-50 dark:bg-emerald-900/30 px-1 rounded border border-emerald-200 dark:border-emerald-800">
                        Ada Riwayat Jasa Luar
                    </span>
                @endif
            </div>
        @endif
        @if(count($order->completed_phases) > 0)
            <div class="bg-zinc-50 dark:bg-zinc-800/40 p-1.5 rounded-lg border border-zinc-100 dark:border-zinc-700/50 text-[9px] text-zinc-500 flex justify-between items-center cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-700" wire:click="$dispatch('open-prod-detail-modal', { orderId: {{ $order->id }} })">
                <div class="flex items-center gap-1 font-medium">
                    <flux:icon.clock class="w-2.5 h-2.5 text-emerald-500" /> {{ count($order->completed_phases) }} Riwayat Fase
                </div>
                <span class="text-blue-600">Detail &rarr;</span>
            </div>
        @endif
        @if($order->phase_type)
            <div class="flex">
                <span class="text-[8px] font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-1.5 py-px rounded uppercase tracking-wide">
                    {{ $order->phase_type }}
                </span>
            </div>
        @endif
    </div>

    @if(isset($this->validationErrors[$order->id]))
        <div class="mt-1 bg-red-50 dark:bg-red-900/30 p-1.5 rounded-lg border border-red-200 dark:border-red-800 text-[10px] text-red-600 dark:text-red-400 leading-snug">
            <flux:icon.exclamation-triangle class="w-2.5 h-2.5 inline mr-1 -mt-0.5" />
            <span class="font-semibold">{{ $this->validationErrors[$order->id] }}</span>
        </div>
    @endif

    @php
        $hasAction = false;
        if ($statusKey === 'material_fulfillment' && $order->status === 'material_issued') $hasAction = true;
        if ($statusKey === 'waiting_vendor') $hasAction = true;
        if ($statusKey === 'in_production') $hasAction = true;
        if ($statusKey === 'completed') $hasAction = true;
    @endphp

    @if($hasAction)
    <div class="grid transition-all duration-300 grid-rows-[0fr] [@media(hover:hover)]:group-hover:grid-rows-[1fr]"
         :class="showFooter ? '!grid-rows-[1fr]' : ''">
        <div class="overflow-hidden">
            <div class="flex flex-col gap-1.5 pt-1.5 border-t border-zinc-100 dark:border-zinc-700/50 mt-1 transition-opacity duration-300 opacity-0 [@media(hover:hover)]:group-hover:opacity-100"
                 :class="showFooter ? '!opacity-100' : ''">
                @if($statusKey === 'material_fulfillment' && $order->status === 'material_issued')
                    <div class="text-[9px] text-zinc-500 leading-tight">Bahan siap. Silakan validasi fisik.</div>
                    <flux:button size="sm" variant="primary" wire:click="$dispatch('open-material-receipt-modal', { orderId: {{ $order->id }} })" class="w-full justify-center !py-1 !text-[10px]" icon="check-circle">Terima Bahan</flux:button>
                @elseif($statusKey === 'waiting_vendor')
                    <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'material_fulfillment')" class="w-full justify-center text-zinc-500 !py-1 !text-[10px]">Batal Antrean</flux:button>
                @elseif($statusKey === 'in_production')
                    <flux:button size="sm" variant="primary" wire:click="$dispatch('open-finish-phase-modal', { orderId: {{ $order->id }}, phase: 'maklon' })" class="w-full justify-center !py-1 !text-[10px]">Selesai</flux:button>
                @elseif($statusKey === 'completed')
                    <flux:button size="sm" variant="subtle" wire:click="updateStatus({{ $order->id }}, 'archived')" class="w-full justify-center text-zinc-500 !py-1 !text-[10px]">Arsipkan</flux:button>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
