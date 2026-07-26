<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\Quotation;

state([
    'quotation' => null,
]);

on(['show-quotation-detail' => function ($id) {
    $this->quotation = Quotation::with(['customer', 'items.item.unit', 'creator'])->findOrFail($id);
    \Flux::modal('quotation-detail')->show();
}]);

$convertToSalesOrder = function () {
    abort_unless(auth()->user()->can('sales.order.create'), 403);
    
    // Konversi SQ ke SO logic
    $latestPo = \Modules\Sales\Models\SalesOrder::orderBy('id', 'desc')->first();
    $nextId = $latestPo ? $latestPo->id + 1 : 1000;
    $soNumber = 'ODM-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    
    $so = \Modules\Sales\Models\SalesOrder::create([
        'so_number' => $soNumber,
        'customer_id' => $this->quotation->customer_id,
        'order_date' => now()->format('Y-m-d'),
        'status' => 'pending_approval',
        'shipping_fee' => $this->quotation->shipping_fee,
        'discount' => $this->quotation->discount,
        'pajak' => $this->quotation->tax,
        'total_amount' => $this->quotation->total_amount,
        'notes' => $this->quotation->notes . "\n[Konversi dari Penawaran: " . $this->quotation->quotation_number . "]",
        'created_by' => auth()->id(),
        'brand_id' => auth()->user()->brand_id ?? null,
    ]);
    
    foreach ($this->quotation->items as $item) {
        \Modules\Sales\Models\SalesOrderItem::create([
            'sales_order_id' => $so->id,
            'item_id' => $item->item_id,
            'qty' => $item->qty,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
            'notes' => $item->notes,
        ]);
    }
    
    $this->quotation->update([
        'status' => 'converted',
        'converted_to_so_id' => $so->id
    ]);
    
    \Flux::toast('Penawaran berhasil dikonversi ke Sales Order!', variant: 'success');
    return redirect()->route('sales.orders.index');
};

?>

<flux:modal name="quotation-detail" class="md:max-w-5xl w-full">
@if($quotation)
<div class="py-0 space-y-6">
    {{-- Modal Header --}}
    <div class="flex items-center gap-4 mb-2 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="p-2.5 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl text-indigo-600 dark:text-indigo-400 shadow-sm shrink-0">
            <flux:icon.document-text class="w-6 h-6" />
        </div>
        <div class="flex-1 min-w-0">
            <flux:heading size="lg" class="!mb-0 flex items-center gap-2">
                {{ $quotation->quotation_number }}
                @if($quotation->status === 'draft')
                    <flux:badge size="sm" color="zinc">Draft</flux:badge>
                @elseif($quotation->status === 'sent')
                    <flux:badge size="sm" color="blue">Terkirim</flux:badge>
                @elseif($quotation->status === 'accepted')
                    <flux:badge size="sm" color="emerald">Diterima</flux:badge>
                @elseif($quotation->status === 'rejected')
                    <flux:badge size="sm" color="red">Ditolak</flux:badge>
                @elseif($quotation->status === 'converted')
                    <flux:badge size="sm" color="purple" icon="check-badge">Jadi SO</flux:badge>
                @endif
            </flux:heading>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Dibuat oleh {{ $quotation->creator->name ?? 'Sistem' }} pada {{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') }}</p>
        </div>
        
        <div class="flex items-center gap-2 print:hidden shrink-0">
            @if(in_array($quotation->status, ['draft', 'sent', 'accepted']) && auth()->user()->can('sales.order.create'))
                <flux:button size="sm" variant="primary" icon="document-duplicate" wire:click="convertToSalesOrder" wire:confirm="Konversi penawaran ini ke Sales Order?">Konversi ke SO</flux:button>
            @endif
            <flux:button size="sm" variant="subtle" icon="printer" onclick="window.print()">Cetak</flux:button>
            <flux:modal.close>
                <flux:button size="sm" variant="ghost" icon="x-mark"></flux:button>
            </flux:modal.close>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50">
                    <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Rincian Penawaran</h3>
                </div>
                <div class="p-0">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-zinc-500 bg-zinc-50 dark:bg-zinc-800/80 uppercase">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Produk</th>
                                <th class="px-5 py-3 font-semibold text-center">Qty</th>
                                <th class="px-5 py-3 font-semibold text-right">Harga Satuan</th>
                                <th class="px-5 py-3 font-semibold text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($quotation->items as $item)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            @if($item->item->image)
                                                <img src="{{ Storage::url($item->item->image) }}" class="w-10 h-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" alt="img">
                                            @else
                                                <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center">
                                                    <flux:icon.cube class="w-5 h-5 text-zinc-400" />
                                                </div>
                                            @endif
                                            <div>
                                                <p class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $item->item->name }}</p>
                                                @if($item->notes)
                                                    @php
                                                        $displayNote = str_replace('Salinan Pesanan: ', '', strip_tags($item->notes));
                                                    @endphp
                                                    <p class="text-xs text-zinc-500 mt-0.5 line-clamp-1" title="{{ $displayNote }}">{{ $displayNote }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-center font-medium text-zinc-700 dark:text-zinc-300">
                                        {{ $item->qty }} <span class="text-[10px] text-zinc-500 font-normal">{{ $item->item->unit->name ?? 'pcs' }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium text-zinc-700 dark:text-zinc-300">
                                        Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-zinc-900 dark:text-zinc-100">
                                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-50 dark:bg-zinc-800/30 border-t border-zinc-200 dark:border-zinc-700/50">
                            <tr>
                                <td colspan="3" class="px-5 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">Subtotal</td>
                                <td class="px-5 py-3 text-right font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->items->sum('subtotal'), 0, ',', '.') }}</td>
                            </tr>
                            @if($quotation->discount > 0)
                            <tr>
                                <td colspan="3" class="px-5 py-2 text-right font-medium text-red-500">Diskon</td>
                                <td class="px-5 py-2 text-right font-bold text-red-500">- Rp {{ number_format($quotation->discount, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($quotation->shipping_fee > 0)
                            <tr>
                                <td colspan="3" class="px-5 py-2 text-right font-medium text-zinc-600 dark:text-zinc-400">Biaya Pengiriman</td>
                                <td class="px-5 py-2 text-right font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->shipping_fee, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($quotation->tax > 0)
                            <tr>
                                <td colspan="3" class="px-5 py-2 text-right font-medium text-zinc-600 dark:text-zinc-400">Pajak (PPN)</td>
                                <td class="px-5 py-2 text-right font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->tax, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr class="border-t border-zinc-200 dark:border-zinc-700/50">
                                <td colspan="3" class="px-5 py-4 text-right font-black text-lg text-zinc-900 dark:text-white">TOTAL</td>
                                <td class="px-5 py-4 text-right font-black text-lg text-emerald-600 dark:text-emerald-400">Rp {{ number_format($quotation->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            @if($quotation->notes || $quotation->terms_and_conditions)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                @if($quotation->notes)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm p-5 print:hidden">
                    <h4 class="font-bold text-sm text-zinc-800 dark:text-zinc-200 mb-2">Catatan Internal</h4>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{!! nl2br(e($quotation->notes)) !!}</div>
                </div>
                @endif
                
                @if($quotation->terms_and_conditions)
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm p-5">
                    <h4 class="font-bold text-sm text-zinc-800 dark:text-zinc-200 mb-2">Syarat & Ketentuan</h4>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400 whitespace-pre-wrap">{!! nl2br(e($quotation->terms_and_conditions)) !!}</div>
                </div>
                @endif
            </div>
            @endif
        </div>
        
        <div class="space-y-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50">
                    <h3 class="font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-2">
                        <flux:icon.user class="w-4 h-4 text-zinc-400" />
                        Info Pelanggan
                    </h3>
                </div>
                <div class="p-5 flex items-start gap-4">
                    <flux:avatar src="{{ $quotation->customer->image ? Storage::url($quotation->customer->image) : '' }}" fallback="{{ substr($quotation->customer->name, 0, 2) }}" size="lg" />
                    <div>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $quotation->customer->name }}</h4>
                        <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if($quotation->customer->email)
                            <div class="flex items-center gap-2">
                                <flux:icon.envelope class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ $quotation->customer->email }}</span>
                            </div>
                            @endif
                            @if($quotation->customer->phone)
                            <div class="flex items-center gap-2">
                                <flux:icon.phone class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ $quotation->customer->phone }}</span>
                            </div>
                            @endif
                            @if($quotation->customer->address)
                            <div class="flex items-start gap-2 mt-2">
                                <flux:icon.map-pin class="w-3.5 h-3.5 text-zinc-400 shrink-0 mt-0.5" />
                                <span>{{ $quotation->customer->address }}
                                    @if($quotation->customer->city) <br>{{ $quotation->customer->city }} @endif
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm p-5 space-y-4">
                <div>
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-1">Berlaku Hingga</p>
                    <p class="font-bold text-zinc-800 dark:text-zinc-200">
                        @if($quotation->valid_until)
                            {{ \Carbon\Carbon::parse($quotation->valid_until)->format('d M Y') }}
                            @php
                                $diff = \Carbon\Carbon::parse($quotation->valid_until)->diffInDays(now(), false);
                            @endphp
                            @if($diff > 0)
                                <span class="text-red-500 text-xs ml-2 font-normal">(Kadaluarsa)</span>
                            @else
                                <span class="text-emerald-500 text-xs ml-2 font-normal">(Berlaku {{ abs($diff) }} hari lagi)</span>
                            @endif
                        @else
                            -
                        @endif
                    </p>
                </div>
                @if($quotation->converted_to_so_id)
                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-1">Dikonversi Menjadi</p>
                    <a href="{{ route('sales.orders.index') }}?show_detail={{ $quotation->converted_to_so_id }}" class="inline-flex items-center gap-1 font-bold text-blue-600 hover:text-blue-700 hover:underline">
                        Lihat Sales Order <flux:icon.arrow-top-right-on-square class="w-3 h-3" />
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endif
</flux:modal>
