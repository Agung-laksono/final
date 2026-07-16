<?php

use function Livewire\Volt\{state, on};
use Modules\Purchase\Models\PurchaseOrder;

state([
    'show' => false,
    'order' => null,
]);

on(['open-detail-modal' => function ($orderId) {
    $this->order = PurchaseOrder::with(['vendor', 'items.item'])->findOrFail($orderId);
    $this->show = true;
}]);

$getStatusBadge = function ($status) {
    $map = [
        'draft' => ['label' => 'Draft', 'color' => 'zinc'],
        'pending_approval' => ['label' => 'Menunggu ACC', 'color' => 'amber'],
        'processing' => ['label' => 'Diproses Vendor', 'color' => 'blue'],
        'partially_received' => ['label' => 'Diterima Sebagian', 'color' => 'indigo'],
        'completed' => ['label' => 'Selesai', 'color' => 'emerald'],
    ];
    
    $mapped = $map[$status] ?? ['label' => $status, 'color' => 'zinc'];
    
    return "<span class=\"inline-flex items-center rounded-md bg-{$mapped['color']}-50 px-2 py-1 text-xs font-medium text-{$mapped['color']}-700 ring-1 ring-inset ring-{$mapped['color']}-600/20\">{$mapped['label']}</span>";
};

?>

<div>
    <flux:modal wire:model="show" class="w-full md:w-[680px] space-y-3">
        @if($order)
            {{-- Header: PO Number + Status + Date --}}
            <div class="pb-2 border-b border-zinc-200 dark:border-zinc-700 pr-6">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-base font-black text-zinc-800 dark:text-zinc-100">{{ $order->po_number }}</span>
                    {!! $this->getStatusBadge($order->status) !!}
                    @if($order->pajak)
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">PPN</span>
                    @endif
                </div>
                <div class="flex items-center gap-4 text-[11px] text-zinc-400 mt-0.5 flex-wrap">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Order: {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('d F Y') }}
                    </span>
                    @if($order->expected_delivery_date)
                        @php
                            $deadline = \Carbon\Carbon::parse($order->expected_delivery_date);
                            $now = \Carbon\Carbon::now();
                            $isCompleted = in_array($order->status, ['completed', 'cancelled']);
                            if ($isCompleted) {
                                $dlColor = 'text-emerald-600'; $dlIcon = '✅';
                            } elseif ($deadline->isPast()) {
                                $dlColor = 'text-red-600 font-semibold'; $dlIcon = '🔴';
                            } elseif ($deadline->diffInDays($now) <= 3) {
                                $dlColor = 'text-amber-600 font-semibold'; $dlIcon = '⚠️';
                            } else {
                                $dlColor = 'text-zinc-500'; $dlIcon = '📅';
                            }
                        @endphp
                        <span class="flex items-center gap-1 {{ $dlColor }}">
                            {{ $dlIcon }} Deadline: {{ $deadline->translatedFormat('d F Y') }}
                            @if(!$isCompleted && $deadline->isPast())
                                <span class="text-[10px]">({{ $deadline->locale('id')->diffForHumans() }})</span>
                            @elseif(!$isCompleted && $deadline->isFuture())
                                <span class="text-[10px] text-zinc-400">({{ $deadline->locale('id')->diffForHumans() }})</span>
                            @endif
                        </span>
                    @else
                        <span class="flex items-center gap-1 text-zinc-400 italic">
                            📅 Deadline: Tidak ditentukan
                        </span>
                    @endif
                </div>
            </div>

            {{-- Vendor + Notes in one compact row --}}
            <div class="flex items-start gap-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-lg p-2.5 border border-zinc-200 dark:border-zinc-700">
                <flux:avatar src="{{ $order->vendor?->image ? Storage::url($order->vendor->image) : '' }}" fallback="{{ substr($order->vendor?->name ?? '?', 0, 2) }}" size="sm" class="shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $order->vendor?->name ?? 'Vendor Terhapus' }}</div>
                    <div class="flex items-center gap-3 text-xs text-zinc-500 mt-0.5 flex-wrap">
                        @if($order->vendor?->phone)
                            <span>📞 {{ $order->vendor->phone }}</span>
                        @endif
                        @if($order->vendor?->address)
                            <span class="truncate max-w-[200px]">📍 {{ $order->vendor->address }}</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Notes (collapsible) --}}
            @if($order->notes)
                <div x-data="{ open: false }" class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-2 bg-zinc-50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors text-left">
                        <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 flex items-center gap-1.5">
                            💬 Catatan Pesanan
                            <span class="text-[10px] font-normal text-zinc-400 italic" x-show="!open">— {{ Str::limit(strip_tags($order->notes), 80) }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 text-zinc-400 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak
                         class="px-3 py-2.5 bg-white dark:bg-zinc-900 text-xs text-zinc-700 dark:text-zinc-300 prose prose-xs max-w-none prose-p:my-1 prose-img:rounded-lg border-t border-zinc-100 dark:border-zinc-800">
                        {!! $order->notes !!}
                    </div>
                </div>
            @endif

            {{-- Items Table - Ultra Compact --}}
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                <table class="w-full text-xs">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="px-3 py-1.5 font-semibold text-left">Barang</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-12">Qty</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-14">Terima</th>
                            <th class="px-2 py-1.5 font-semibold text-right w-24">Harga</th>
                            <th class="px-3 py-1.5 font-semibold text-right w-28">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($order->items as $item)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 leading-snug">{{ $item->item->name }}</div>
                                    <div class="font-mono text-[10px] text-zinc-400">{{ $item->item->code }}</div>
                                    @if($item->notes)
                                        <div x-data="{ open: false }">
                                            <div @click="open = !open"
                                                 class="mt-1 text-[10px] text-zinc-500 italic cursor-pointer hover:text-blue-500 transition-colors flex items-start gap-0.5 group">
                                                <span x-show="!open" class="line-clamp-1">{{ strip_tags($item->notes) }}</span>
                                                <span x-show="open" x-cloak class="text-zinc-400">▲ tutup</span>
                                                <svg x-show="!open" class="w-2.5 h-2.5 shrink-0 mt-px text-zinc-400 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                            <div x-show="open" x-cloak
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100"
                                                 class="mt-1 p-1.5 bg-zinc-50 dark:bg-zinc-800/50 rounded border border-zinc-200 dark:border-zinc-700 text-[10px] text-zinc-600 dark:text-zinc-400 prose prose-xs max-w-none prose-p:my-0.5 prose-img:rounded">
                                                {!! $item->notes !!}
                                            </div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right text-zinc-700 dark:text-zinc-300">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right">
                                    @if($item->received_quantity >= $item->quantity)
                                        <span class="text-emerald-600 font-semibold">{{ number_format($item->received_quantity, 0, ',', '.') }}</span>
                                    @elseif($item->received_quantity > 0)
                                        <span class="text-amber-600 font-semibold">{{ number_format($item->received_quantity, 0, ',', '.') }}</span>
                                    @else
                                        <span class="text-zinc-400">0</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right text-zinc-600 dark:text-zinc-400">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-zinc-800 dark:text-zinc-200">{{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right text-xs font-bold text-zinc-600 dark:text-zinc-400">Total</td>
                            <td class="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-400">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Footer actions --}}
            <div class="flex justify-end gap-2 pt-1">
                <flux:button size="sm" variant="ghost" wire:click="$set('show', false)">Tutup</flux:button>
                @if(in_array($order->status, ['processing', 'partially_received']))
                    @can('purchase.order.update')
                        <flux:button size="sm" variant="primary" icon="cube" wire:click="$dispatch('open-receipt-modal', { orderId: {{ $order->id }} }); $set('show', false)">
                            Terima Barang
                        </flux:button>
                    @endcan
                @endif
                @if($order->status === 'completed')
                    @can('purchase.order.update')
                        <flux:button size="sm" variant="ghost" icon="archive-box" class="text-zinc-500" wire:click="confirmArchive({{ $order->id }}); $set('show', false)">
                            Arsipkan
                        </flux:button>
                    @endcan
                @endif
            </div>
        @else
            <div class="p-8 text-center text-zinc-500">Memuat data...</div>
        @endif
    </flux:modal>
</div>
