<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
]);

on(['open-detail-modal' => function ($orderId) {
    $order = SalesOrder::find($orderId);
    if (!$order) return;
    
    $isOwn = $order->created_by === auth()->id();
    $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
    if (!$isOwn && !$isManagerial) {
        \Flux::toast('Anda tidak memiliki akses untuk melihat detail pesanan ini.', 'danger');
        return;
    }

    $this->orderId = $orderId;
    $this->order = SalesOrder::with(['customer', 'items.item', 'creator', 'payments', 'fulfillments'])->find($orderId);
    $this->show = true;
}]);

$getStatusBadge = function ($status) {
    $map = [
        'draft' => ['label' => 'Draft', 'color' => 'zinc'],
        'pending_approval' => ['label' => 'Menunggu ACC', 'color' => 'amber'],
        'processing' => ['label' => 'Diproses', 'color' => 'blue'],
        'packing' => ['label' => 'Packing', 'color' => 'purple'],
        'shipping' => ['label' => 'Dikirim', 'color' => 'orange'],
        'completed' => ['label' => 'Selesai', 'color' => 'emerald'],
        'cancelled' => ['label' => 'Dibatalkan', 'color' => 'red'],
    ];
    
    $mapped = $map[$status] ?? ['label' => strtoupper($status), 'color' => 'zinc'];
    
    return "<span class=\"inline-flex items-center rounded-md bg-{$mapped['color']}-50 px-2 py-1 text-xs font-medium text-{$mapped['color']}-700 ring-1 ring-inset ring-{$mapped['color']}-600/20\">{$mapped['label']}</span>";
};

?>

<div>
    <flux:modal wire:model="show" class="w-[92vw] md:w-[680px] space-y-3" scroll="body">
        @if($order)
            @php
                // Tim gudang biasa (tanpa role manajerial) tidak boleh melihat harga
                $canViewPrices = !(auth()->user()->hasRole('Gudang') && !auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Kepala Sales']));
            @endphp
            
            {{-- Header: SO Number + Status + Date --}}
            <div class="pb-2 border-b border-zinc-200 dark:border-zinc-700 pr-6 relative">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-base font-black text-zinc-800 dark:text-zinc-100">{{ $order->so_number }}</span>
                    {!! $this->getStatusBadge($order->status) !!}
                    @if($order->pajak)
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">PPN</span>
                    @endif
                    
                    <div class="ml-auto flex items-center gap-1.5 text-[11px] text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700" title="Petugas Checkout">
                        <flux:icon.user class="w-3 h-3" />
                        <span class="font-medium">{{ explode(' ', $order->creator?->name ?? 'Sistem')[0] }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <div class="flex items-center gap-4 text-[11px] text-zinc-400 flex-wrap">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Order: {{ \Carbon\Carbon::parse($order->order_date)->translatedFormat('d F Y') }}
                        </span>
                        @if($order->deadline)
                            @php
                                $deadline = \Carbon\Carbon::parse($order->deadline);
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
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-zinc-400 italic">
                                📅 Deadline: Tdk ditentukan
                            </span>
                        @endif
                    </div>
                    
                    {{-- Tombol Print Invoice --}}
                    @if(!in_array($order->status, ['pending_approval', 'rejected']))
                        <div x-data="{
                            printing: false,
                            printInvoice(url, filename) {
                                if (this.printing) return;
                                
                                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                                
                                if (isMobile) {
                                    window.open(url, '_blank');
                                    return;
                                }
                                
                                this.printing = true;
                                
                                const originalTitle = document.title;
                                document.title = filename;

                                const iframe = document.createElement('iframe');
                                iframe.style.display = 'none';
                                iframe.src = url;
                                document.body.appendChild(iframe);
                                iframe.onload = () => {
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                    this.printing = false;
                                    
                                    setTimeout(() => {
                                        document.title = originalTitle;
                                        document.body.removeChild(iframe);
                                    }, 5000);
                                };
                            }
                        }">
                            <flux:button size="sm" variant="subtle" class="!px-2 !py-1 h-auto text-[10px]" icon="printer" x-on:click="printInvoice('{{ route('sales.orders.invoice', $order->id) }}', '{{ $order->so_number }} {{ addslashes($order->customer?->name ?? '') }}')" x-bind:disabled="printing">
                                <span x-show="!printing">Cetak Nota</span>
                                <span x-show="printing" style="display: none;">Mencetak...</span>
                            </flux:button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                {{-- Customer Info --}}
                <div class="flex items-start gap-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-lg p-2.5 border border-zinc-200 dark:border-zinc-700">
                    <flux:avatar src="{{ $order->customer?->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer?->name ?? '?', 0, 2) }}" size="sm" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $order->customer?->name ?? 'Pelanggan Terhapus' }}</div>
                        <div class="flex flex-col gap-0.5 text-[10px] text-zinc-500 mt-0.5">
                            @if($order->customer?->phone)
                                <span>📞 {{ $order->customer->phone }}</span>
                            @endif
                            @if($order->customer?->address)
                                <span class="truncate" title="{{ $order->customer->address }}">📍 {{ $order->customer->address }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                
                {{-- Payment Status --}}
                @if($canViewPrices)
                @php
                    $terbayar = collect($order->payments)->sum('amount');
                    $sisa = $order->total_amount - $terbayar;
                    
                    $payColor = $order->payment_status === 'paid' ? 'emerald' : ($order->payment_status === 'partial' ? 'amber' : 'red');
                @endphp
                <div class="bg-{{ $payColor }}-50 dark:bg-{{ $payColor }}-900/10 rounded-lg p-2.5 border border-{{ $payColor }}-200 dark:border-{{ $payColor }}-900/50 flex flex-col justify-center">
                    <div class="flex justify-between items-start mb-1">
                        <span class="text-[10px] font-bold text-{{ $payColor }}-800 dark:text-{{ $payColor }}-400 uppercase tracking-wider">Total Tagihan</span>
                        <flux:badge size="sm" class="!text-[9px] !py-0 !px-1.5 h-4" color="{{ $order->payment_status === 'paid' ? 'green' : ($order->payment_status === 'partial' ? 'amber' : 'red') }}">
                            {{ strtoupper($order->payment_status) }}
                        </flux:badge>
                    </div>
                    <div class="text-base font-black text-{{ $payColor }}-700 dark:text-{{ $payColor }}-400">
                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                    </div>
                    @if($terbayar > 0)
                        <div class="mt-1 flex justify-between text-[10px] font-medium text-{{ $payColor }}-800 dark:text-{{ $payColor }}-500">
                            <span>Sisa: Rp {{ number_format(max(0, $sisa), 0, ',', '.') }}</span>
                        </div>
                    @endif
                </div>
                @else
                <div class="bg-zinc-50 dark:bg-zinc-800/40 rounded-lg p-2.5 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center">
                    <span class="text-xs text-zinc-400 italic">Info Harga Disembunyikan</span>
                </div>
                @endif
            </div>

            {{-- Notes (collapsible) --}}
            @if($order->notes)
                <div x-data="{ open: false }" class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <button @click="open = !open"
                            class="w-full flex items-center justify-between px-3 py-1.5 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors text-left border-b border-transparent"
                            :class="open ? '!border-amber-200 dark:!border-amber-800/50' : ''">
                        <span class="text-[11px] font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            💬 Catatan Khusus
                            <span class="font-normal text-amber-600/70 dark:text-amber-500/70 italic" x-show="!open">— {{ Str::limit(strip_tags($order->notes), 60) }}</span>
                        </span>
                        <svg class="w-3.5 h-3.5 text-amber-600/70 transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-cloak
                         class="px-3 py-2 bg-white dark:bg-zinc-900 text-[11px] text-zinc-700 dark:text-zinc-300 prose prose-xs max-w-none prose-p:my-0.5">
                        {!! nl2br(e($order->notes)) !!}
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
                            @if($canViewPrices)
                            <th class="px-2 py-1.5 font-semibold text-right w-24">Harga</th>
                            <th class="px-3 py-1.5 font-semibold text-right w-28">Subtotal</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($order->items as $item)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                <td class="px-3 py-2">
                                    <div class="flex items-start gap-3">
                                        @php
                                            $itemImage = null;
                                            if (!empty($item->custom_attachments) && is_array($item->custom_attachments) && count($item->custom_attachments) > 0) {
                                                $itemImage = $item->custom_attachments[0]['path'] ?? ($item->custom_attachments[0] ?? null);
                                            }
                                            if (!$itemImage && $item->item) {
                                                $itemImage = $item->item->image;
                                            }
                                        @endphp
                                        @if($itemImage && is_string($itemImage))
                                            <div class="shrink-0 w-10 h-10 rounded overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-100 cursor-zoom-in hover:opacity-80 transition-opacity mt-0.5"
                                                 @click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($itemImage) }}' })"
                                                 title="Lihat Gambar">
                                                <img src="{{ Storage::url($itemImage) }}" class="w-full h-full object-cover">
                                            </div>
                                        @else
                                            <div class="shrink-0 w-10 h-10 rounded border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center text-zinc-400 mt-0.5">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100 leading-snug flex items-center gap-1.5 flex-wrap">
                                                {{ $item->item->name }}
                                                @if(!empty($item->custom_attributes) || !empty($item->custom_attachments) || str_contains(strtoupper($item->notes ?? ''), '[CUSTOM]'))
                                                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-500 border border-amber-200 dark:border-amber-800">CUSTOM</span>
                                                @endif
                                            </div>
                                            <div class="font-mono text-[10px] text-zinc-400">{{ $item->item->code }}</div>
                                            @if(!empty($item->custom_attributes))
                                                <div class="mt-1">
                                                    @foreach($item->custom_attributes as $attr)
                                                        <div class="text-[10px] text-amber-600 dark:text-amber-500 leading-tight">
                                                            <span class="font-medium">{{ $attr['key'] ?? '-' }}:</span> {{ $attr['value'] ?? '-' }}
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if($item->notes)
                                                <div class="text-[10px] text-zinc-500 mt-0.5 italic flex items-start gap-0.5">
                                                    <span class="text-blue-500 mt-px">↳</span> {{ $item->notes }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 py-2 text-right text-zinc-700 dark:text-zinc-300">{{ number_format($item->qty, 0, ',', '.') }}</td>
                                @if($canViewPrices)
                                <td class="px-2 py-2 text-right text-zinc-600 dark:text-zinc-400">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-zinc-800 dark:text-zinc-200">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    @if($canViewPrices)
                    <tfoot class="bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                        @if($order->packing_fee > 0 || $order->shipping_fee > 0 || $order->discount > 0)
                            @if($order->packing_fee > 0)
                            <tr>
                                <td colspan="3" class="px-3 py-1 text-right text-[10px] text-zinc-500">Biaya Packing</td>
                                <td class="px-3 py-1 text-right text-[10px] font-medium text-zinc-600 dark:text-zinc-300">Rp {{ number_format($order->packing_fee, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($order->shipping_fee > 0)
                            <tr>
                                <td colspan="3" class="px-3 py-1 text-right text-[10px] text-zinc-500">Ongkir ({{ $order->courier_vendor ?? '-' }})</td>
                                <td class="px-3 py-1 text-right text-[10px] font-medium text-zinc-600 dark:text-zinc-300">Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($order->discount > 0)
                            <tr>
                                <td colspan="3" class="px-3 py-1 text-right text-[10px] text-zinc-500">Diskon</td>
                                <td class="px-3 py-1 text-right text-[10px] font-medium text-red-500">-Rp {{ number_format($order->discount, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                        @endif
                        <tr>
                            <td colspan="{{ $canViewPrices ? '3' : '1' }}" class="px-3 py-2 text-right text-xs font-bold text-zinc-600 dark:text-zinc-400 border-t border-zinc-200 dark:border-zinc-700">TOTAL</td>
                            <td class="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-400 border-t border-zinc-200 dark:border-zinc-700">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{-- Footer actions --}}
            <div class="flex justify-end pt-1">
                <flux:button size="sm" variant="ghost" wire:click="$set('show', false)">Tutup Modal</flux:button>
            </div>
        @else
            <div class="p-8 text-center text-zinc-500">Memuat data...</div>
        @endif
    </flux:modal>
</div>
