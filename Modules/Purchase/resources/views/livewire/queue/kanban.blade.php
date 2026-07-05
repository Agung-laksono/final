<?php
use function Livewire\Volt\{state, layout, title, computed, on};
use Modules\Purchase\Models\PurchaseQueue;
use Modules\Inventory\Models\Item;

layout('layouts.app');
title('Kanban Permintaan Pembelian');

// Definisi Kolom Kanban
state([
    'columns' => [
        'approved' => ['title' => 'Disetujui (Antre)', 'color' => 'blue'],
        'ordered' => ['title' => 'Sudah Dipesan (PO)', 'color' => 'amber'],
        'completed' => ['title' => 'Selesai / Ready', 'color' => 'emerald'],
        'rejected' => ['title' => 'Ditolak', 'color' => 'red'],
        'archived' => ['title' => 'Arsip', 'color' => 'slate'],
    ],
    'transparent_columns' => false,
    'search' => '',
]);

$queues = computed(function () {
    $query = PurchaseQueue::with(['item', 'fulfillments.purchaseOrderItem.purchaseOrder.vendor'])->latest();
    if ($this->search) {
        $query->whereHas('item', function($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('code', 'like', '%' . $this->search . '%');
        });
    }
    return $query->get()->groupBy(function($q) {
        return $q->status ?? 'approved';
    });
});

$updateStatus = function ($queueId, $newStatus) {
    if (!array_key_exists($newStatus, $this->columns)) return;
    
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        abort_unless(auth()->user()->can('purchase.queue.update'), 403, 'Anda tidak memiliki izin untuk menggeser antrean.');

        $queue->status = $newStatus;
        $queue->save();
        $this->dispatch('status-updated');
        \App\Events\KanbanUpdated::safeDispatch('purchase_queue');
        \Flux::toast('Status berhasil diperbarui.', variant: 'success');
    }
};

$rejectQueue = function ($queueId) {
    abort_unless(auth()->user()->can('purchase.queue.update'), 403, 'Anda tidak memiliki izin untuk menolak antrean.');
    $queue = PurchaseQueue::find($queueId);
    if ($queue) {
        $queue->status = 'rejected';
        $queue->approved_qty = 0;
        $queue->save();
        $this->dispatch('status-updated');
    }
};

on(['status-updated' => function () {
    // Kosong saja, tujuannya hanya memancing re-render agar computed $queues dijalankan ulang
}]);

?>

<div class="kanban-root relative flex flex-col w-full" 
     x-data="{ 
        showHeader: $persist(true).as('kanban-queue-header-user-{{ auth()->id() }}'),
        transparent: $persist(false).as('kanban-queue-transparent-user-{{ auth()->id() }}')
     }" 
     style="height: 100vh; overflow: hidden;">
    
    <style>
        /* Paksa hilangkan padding bawaan layout KHUSUS untuk halaman Kanban ini */
        *:has(> .kanban-root), *:has(> div > .kanban-root) {
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        main[data-flux-main] {
            padding: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            overflow: hidden !important;
        }
        body {
            overflow: hidden !important;
        }
        
        /* Menyembunyikan scrollbar tapi tetap bisa digulir */
        .hide-scroll::-webkit-scrollbar {
            display: none;
        }
        .hide-scroll {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
     
    {{-- Floating Show Header Button --}}
    <div class="absolute top-2 right-2 sm:top-4 sm:right-6 z-[110]" x-show="!showHeader" x-transition x-cloak>
        <flux:button variant="subtle" class="rounded-full shadow-lg bg-white/90 dark:bg-zinc-800/90 backdrop-blur border border-zinc-200 dark:border-zinc-700 w-10 h-10 p-0 flex items-center justify-center" @click="showHeader = true" title="Tampilkan Alat">
            <flux:icon.chevron-down class="w-5 h-5 text-zinc-500" />
        </flux:button>
    </div>

    {{-- Floating Controls (Full Width) --}}
    <div class="absolute top-2 left-2 right-2 sm:top-4 sm:left-4 sm:right-4 z-[60] flex items-center justify-between gap-2 sm:gap-4 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md px-2 py-2 sm:px-4 sm:py-3 rounded-2xl shadow-sm border border-zinc-200/50 dark:border-zinc-800/50" x-show="showHeader" x-transition>
        
        <div class="flex-1 min-w-0 max-w-md">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama atau kode barang..." />
        </div>

        <div class="flex items-center gap-1 sm:gap-2 shrink-0">
            <div class="hidden sm:flex items-center mr-2" title="Mode Transparan">
                <flux:switch x-model="transparent" label="Transparan" />
            </div>

            <flux:button variant="subtle" class="px-2.5 sm:px-3 text-zinc-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 ml-1 sm:ml-2" title="Sembunyikan Alat" @click="showHeader = false">
                <flux:icon.eye-slash class="w-5 h-5" />
            </flux:button>
        </div>
    </div>

    {{-- Kanban Board Area --}}
    <div class="flex-1 min-h-0 flex flex-col px-0 lg:px-6 transition-all duration-300"
         :class="showHeader ? 'pt-16 sm:pt-20 lg:pt-24' : 'pt-2 lg:pt-6'">
        <div class="flex-1 min-h-0 overflow-x-auto pb-2 lg:pb-4 snap-x snap-mandatory scroll-smooth custom-scrollbar">
            <div class="flex justify-start gap-3 sm:gap-4 lg:gap-6 items-stretch min-w-max h-full px-2 lg:px-0">
        @foreach($columns as $statusKey => $column)
            <div x-data="{ collapsed: $persist({{ in_array($statusKey, ['archived', 'rejected']) ? 'true' : 'false' }}).as('kanban-col-queue-{{ $statusKey }}-user-{{ auth()->id() }}') }"
                 style="height: 100%; display: flex; flex-direction: column;"
                 class="h-full max-h-full rounded-2xl overflow-hidden snap-center transition-all duration-300 flex-col shrink-0" 
                 :class="(transparent ? '' : 'bg-zinc-50 dark:bg-zinc-800/30 border border-zinc-200 dark:border-zinc-800/50 shadow-sm ') + (collapsed ? 'w-16 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800/80' : 'w-80')"
                 @click="if(collapsed) collapsed = false"
                 wire:key="column-{{ $statusKey }}">
                
                {{-- Column Header --}}
                <div class="p-4 flex justify-between items-center rounded-t-xl transition-all duration-300"
                     :class="(transparent ? '' : 'bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 ') + (collapsed ? 'flex-col gap-4 h-full pb-8' : '')">
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        <div class="w-2.5 h-2.5 rounded-full bg-{{ $column['color'] }}-500 shadow-[0_0_8px_rgba(0,0,0,0.5)] shadow-{{ $column['color'] }}-500/50 shrink-0"></div>
                        <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 transition-all duration-300 whitespace-nowrap"
                            :class="collapsed ? 'vertical-text tracking-widest mt-2' : ''">{{ $column['title'] }}</h3>
                    </div>
                    <div class="flex items-center gap-2" :class="collapsed ? 'flex-col' : ''">
                        @if($statusKey === 'approved' && count($this->queues['approved'] ?? []) > 0)
                            <div x-show="!collapsed">
                                <flux:button size="xs" variant="primary" icon="document-plus" wire:click="$dispatch('open-consolidation-modal')" class="!h-6 !px-2 text-[10px]">Buat PO</flux:button>
                            </div>
                        @endif
                        <flux:badge size="sm" class="bg-zinc-100 dark:bg-zinc-800 shrink-0">{{ count($this->queues[$statusKey] ?? []) }}</flux:badge>
                        <button @click.stop="collapsed = !collapsed" class="text-zinc-400 hover:text-zinc-600 transition-colors shrink-0" x-bind:title="collapsed ? 'Buka Kolom' : 'Tutup Kolom'">
                            <flux:icon.arrows-right-left class="w-4 h-4" x-bind:class="collapsed ? 'rotate-90' : ''" />
                        </button>
                    </div>
                </div>

                {{-- Column Items --}}
                <div x-show="!collapsed" x-transition.opacity.duration.300ms class="flex-1 p-3 overflow-y-auto space-y-3" :class="transparent ? 'hide-scroll' : 'custom-scrollbar'">
                    @forelse($this->queues[$statusKey] ?? [] as $queue)
                            @php
                                $sourceBadge = match($queue->source_type) {
                                    'low_stock' => [
                                        'color' => 'red',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
                                        'label' => 'Stok Menipis'
                                    ],
                                    'sales' => [
                                        'color' => 'emerald',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>',
                                        'label' => 'Pesanan Jual'
                                    ],
                                    default => [
                                        'color' => 'zinc',
                                        'icon' => '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>',
                                        'label' => 'Manual'
                                    ]
                                };
                            @endphp

                        <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 hover:-translate-y-1 hover:shadow-md hover:border-{{ $column['color'] }}-400 dark:hover:border-{{ $column['color'] }}-500 transition-all duration-300 group relative flex flex-col">
                            
                            {{-- Header Card --}}
                            <div class="flex justify-between items-start mb-3">
                                <span class="font-mono text-[11px] font-bold text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 rounded-md">
                                    #ANT-{{ str_pad($queue->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="flex items-center text-[10px] text-zinc-400 font-medium">
                                    @if($queue->creator)
                                        <flux:avatar src="" fallback="{{ substr($queue->creator->name, 0, 2) }}" size="xs" class="w-4 h-4 mr-1 text-[8px]" />
                                        <span class="mr-2" title="Dibuat oleh {{ $queue->creator->name }}">{{ strtok($queue->creator->name, ' ') }}</span>
                                    @else
                                        <flux:icon.cpu-chip class="w-3 h-3 mr-1" />
                                        <span class="mr-2" title="Dibuat otomatis oleh Sistem">Sistem</span>
                                    @endif
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ $queue->created_at->diffForHumans() }}
                                </div>
                            </div>

                            {{-- Source Badge --}}
                            <div class="mb-2">
                                <span class="inline-flex items-center rounded-md bg-{{ $sourceBadge['color'] }}-50 dark:bg-{{ $sourceBadge['color'] }}-500/10 px-2 py-1 text-[10px] font-semibold text-{{ $sourceBadge['color'] }}-600 dark:text-{{ $sourceBadge['color'] }}-400 ring-1 ring-inset ring-{{ $sourceBadge['color'] }}-500/20">
                                    {!! $sourceBadge['icon'] !!}
                                    {{ $sourceBadge['label'] }}
                                </span>
                            </div>

                            {{-- Item Info --}}
                            <h4 class="font-semibold text-sm text-zinc-900 dark:text-zinc-100 mb-1 leading-tight group-hover:text-{{ $column['color'] }}-600 dark:group-hover:text-{{ $column['color'] }}-400 transition-colors line-clamp-2">
                                {{ $queue->item->name ?? 'Barang Dihapus' }}
                            </h4>
                            
                            {{-- Vendor Info --}}
                            @if(in_array($statusKey, ['ordered', 'completed', 'archived']) && $queue->fulfillments->count() > 0)
                                @php
                                    $vendorName = $queue->fulfillments->first()->purchaseOrderItem->purchaseOrder->vendor->name ?? null;
                                @endphp
                                @if($vendorName)
                                    <div class="mt-1.5 flex items-center gap-1.5 text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-800/50 w-max max-w-full px-2 py-1 rounded-md border border-zinc-100 dark:border-zinc-700/50">
                                        <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <span class="font-medium truncate">{{ $vendorName }}</span>
                                    </div>
                                @endif
                            @endif

                            {{-- Qty Bottom --}}
                            <div class="flex items-end justify-between text-sm mt-3 pt-3 border-t border-dashed border-zinc-200 dark:border-zinc-700">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider mb-0.5">Jumlah Diperlukan</span>
                                    <span class="text-lg font-black text-zinc-800 dark:text-zinc-200 tracking-tight leading-none">{{ $queue->requested_qty }} <span class="text-xs font-medium text-zinc-500">Unit</span></span>
                                </div>
                                @if($queue->approved_qty !== null && $queue->status !== 'rejected')
                                    @if($queue->approved_qty < $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Hanya disetujui sebagian">
                                            <span class="text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400 tracking-tight leading-none">{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @elseif($queue->approved_qty > $queue->requested_qty)
                                        <div class="flex flex-col text-right" title="Disetujui lebih dari permintaan">
                                            <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-0.5">Disetujui Ekstra</span>
                                            <span class="text-sm font-bold text-blue-600 dark:text-blue-400 tracking-tight leading-none">+{{ $queue->approved_qty }} Unit</span>
                                        </div>
                                    @endif
                                @endif
                                @if($queue->status === 'rejected')
                                    <div class="flex flex-col text-right">
                                        <span class="text-[10px] font-bold text-red-500 uppercase tracking-wider mb-0.5">Disetujui</span>
                                        <span class="text-sm font-bold text-red-600 dark:text-red-400 tracking-tight leading-none">0 Unit</span>
                                    </div>
                                @endif
                            </div>


                        </div>
                    @empty
                        <div class="h-24 flex items-center justify-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-xl text-sm text-zinc-400 dark:text-zinc-500">
                            Kosong
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
            </div>
        </div>
    </div>
    
    <livewire:queue.consolidation-modal />
    <livewire:global.item-gallery-modal context="purchase" />
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
        }
        .vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
        }
    </style>
</div>
