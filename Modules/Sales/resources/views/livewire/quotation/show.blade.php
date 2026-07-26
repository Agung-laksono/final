<?php
use function Livewire\Volt\{state, layout, title, computed, mount};
use Modules\Sales\Models\Quotation;

layout('layouts.app');
title('Detail Penawaran Harga');

state([
    'quotation' => null,
]);

mount(function ($id) {
    $this->quotation = Quotation::with(['customer', 'items.item.unit', 'creator'])->findOrFail($id);
});

$convertToSalesOrder = function () {
    abort_unless(auth()->user()->can('sales.order.create'), 403);
    
    // Buat one-time token yang disimpan di cache (berlaku 5 menit)
    $token = \Illuminate\Support\Str::random(40);
    \Illuminate\Support\Facades\Cache::put('sq_convert_' . $token, $this->quotation->id, now()->addMinutes(5));
    
    \Flux::toast('Memuat data penawaran ke form Sales Order...', variant: 'success');
    $this->redirect(route('sales.orders.create', ['token' => $token]), navigate: true);
};

?>

<div class="max-w-5xl mx-auto py-6 space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
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
            </h1>
            <p class="text-sm text-zinc-500">Dibuat oleh {{ $quotation->creator->name ?? 'Sistem' }} pada {{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d M Y') }}</p>
        </div>
        
        <div class="flex items-center gap-2 print:hidden">
            <flux:button variant="subtle" icon="arrow-left" href="{{ route('sales.quotations.index') }}" wire:navigate>Kembali</flux:button>
            <flux:button variant="subtle" icon="printer" href="{{ route('sales.quotations.print', $quotation->id) }}" wire:navigate>Cetak</flux:button>
            
            @if(in_array($quotation->status, ['draft', 'sent', 'accepted']) && auth()->user()->can('sales.order.create'))
                <flux:button variant="primary" wire:click="convertToSalesOrder">
                    <div class="flex items-center gap-1.5">
                        <flux:icon.document-duplicate class="w-4 h-4" wire:loading.remove wire:target="convertToSalesOrder" />
                        <flux:icon.loading class="w-4 h-4 animate-spin" wire:loading wire:target="convertToSalesOrder" />
                        <span wire:loading.remove wire:target="convertToSalesOrder">Konversi ke SO</span>
                        <span wire:loading wire:target="convertToSalesOrder">Memproses...</span>
                    </div>
                </flux:button>
            @endif
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
                    @php
                        $custName = $quotation->customer->name ?? $quotation->customer_name ?? 'Pelanggan Umum';
                        $custImage = $quotation->customer->image ?? null;
                    @endphp
                    <flux:avatar src="{{ $custImage ? Storage::url($custImage) : '' }}" fallback="{{ substr($custName, 0, 2) }}" size="lg" />
                    <div>
                        <h4 class="font-bold text-zinc-900 dark:text-zinc-100">{{ $custName }}</h4>
                        <div class="mt-2 space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                            @if($quotation->customer && $quotation->customer->email)
                            <div class="flex items-center gap-2">
                                <flux:icon.envelope class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ $quotation->customer->email }}</span>
                            </div>
                            @endif
                            @if($quotation->customer && $quotation->customer->phone)
                            <div class="flex items-center gap-2">
                                <flux:icon.phone class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ $quotation->customer->phone }}</span>
                            </div>
                            @endif
                            @if($quotation->customer && $quotation->customer->address)
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
