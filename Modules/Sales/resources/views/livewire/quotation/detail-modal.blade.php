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
    
    // Buat one-time token yang disimpan di cache (berlaku 5 menit)
    $token = \Illuminate\Support\Str::random(40);
    \Illuminate\Support\Facades\Cache::put('sq_convert_' . $token, $this->quotation->id, now()->addMinutes(5));
    
    \Flux::toast('Memuat data penawaran ke form Sales Order...', variant: 'success');
    $this->redirect(route('sales.orders.create', ['token' => $token]), navigate: true);
};

?>

<flux:modal name="quotation-detail" class="md:max-w-5xl w-full">
@if($quotation)
<div class="py-0 space-y-6">
    {{-- Modal Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-2 pb-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-4 w-full sm:w-auto flex-1 min-w-0">
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
        </div>
        
        <div x-data="{
            printing: false,
            printDocument(url, filename) {
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
        }" class="flex w-full sm:w-auto items-center gap-2 print:hidden shrink-0 mt-2 sm:mt-0">
            @if(in_array($quotation->status, ['draft', 'sent', 'accepted']) && auth()->user()->can('sales.order.create'))
                <flux:button size="sm" variant="primary" wire:click="convertToSalesOrder">
                    <div class="flex items-center gap-1.5">
                        <flux:icon.document-duplicate class="w-4 h-4" wire:loading.remove wire:target="convertToSalesOrder" />
                        <flux:icon.loading class="w-4 h-4 animate-spin" wire:loading wire:target="convertToSalesOrder" />
                        <span wire:loading.remove wire:target="convertToSalesOrder">Konversi ke SO</span>
                        <span wire:loading wire:target="convertToSalesOrder">Memproses...</span>
                    </div>
                </flux:button>
            @endif
            <flux:button size="sm" variant="subtle" x-on:click="printDocument('{{ route('sales.quotations.print', $quotation->id) }}', '{{ $quotation->quotation_number }} {{ addslashes($quotation->customer?->name ?? '') }}')" x-bind:disabled="printing" class="flex-1 sm:flex-none justify-center">
                <div class="flex items-center gap-1.5">
                    <flux:icon.printer class="w-4 h-4" x-show="!printing" />
                    <flux:icon.loading class="w-4 h-4 animate-spin" x-show="printing" style="display: none;" />
                    <span x-show="!printing">Cetak</span>
                    <span x-show="printing" style="display: none;">Mencetak...</span>
                </div>
            </flux:button>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/50">
                    <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Rincian Penawaran</h3>
                </div>
                <div class="p-0">
                    <!-- Desktop Table View -->
                    <div class="hidden md:block overflow-x-auto">
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
                                                  @if($item->custom_attachments && count($item->custom_attachments) > 0)
                                                      <img src="{{ Storage::url($item->custom_attachments[0]) }}" class="w-10 h-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 cursor-zoom-in hover:opacity-80 transition-opacity" alt="img" x-on:click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($item->custom_attachments[0]) }}' })">
                                                  @elseif($item->item->image)
                                                      <img src="{{ Storage::url($item->item->image) }}" class="w-10 h-10 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 cursor-zoom-in hover:opacity-80 transition-opacity" alt="img" x-on:click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($item->item->image) }}' })">
                                                  @else
                                                      <div class="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center">
                                                          <flux:icon.cube class="w-5 h-5 text-zinc-400" />
                                                      </div>
                                                  @endif
                                                  <div>
                                                      <p class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                                                          {{ $item->item->name }}
                                                          @if(($item->custom_attributes && count($item->custom_attributes) > 0) || ($item->custom_attachments && count($item->custom_attachments) > 0))
                                                              <span class="inline-flex items-center gap-0.5 text-[9px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 px-1.5 py-0.5 rounded shadow-sm">
                                                                  <flux:icon.sparkles class="w-2.5 h-2.5 text-emerald-600" /> CUSTOM
                                                              </span>
                                                          @endif
                                                      </p>
                                                      
                                                      @if($item->custom_attributes && count($item->custom_attributes) > 0)
                                                          <div class="flex flex-wrap gap-1 mt-1">
                                                              @foreach($item->custom_attributes as $attr)
                                                                  @if(is_array($attr))
                                                                      <span class="inline-block px-1.5 py-0.5 text-[9px] font-medium bg-amber-50 text-amber-700 border border-amber-200 rounded-sm">{{ $attr['key'] ?? '' }}: {{ $attr['value'] ?? '' }}</span>
                                                                  @endif
                                                              @endforeach
                                                          </div>
                                                      @endif
                                                      
                                                      @if($item->notes)
                                                          <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 italic prose prose-xs prose-p:my-0 leading-tight">
                                                              {!! $item->notes !!}
                                                          </div>
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
                    
                    <!-- Mobile Card List View -->
                    <div class="block md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($quotation->items as $item)
                            <div class="p-4 space-y-3">
                                <div class="flex items-start gap-3">
                                      @if($item->custom_attachments && count($item->custom_attachments) > 0)
                                          <img src="{{ Storage::url($item->custom_attachments[0]) }}" class="w-12 h-12 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 cursor-zoom-in hover:opacity-80 transition-opacity" alt="img" x-on:click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($item->custom_attachments[0]) }}' })">
                                      @elseif($item->item->image)
                                          <img src="{{ Storage::url($item->item->image) }}" class="w-12 h-12 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700 shrink-0 cursor-zoom-in hover:opacity-80 transition-opacity" alt="img" x-on:click.stop="$dispatch('open-lightbox', { url: '{{ Storage::url($item->item->image) }}' })">
                                      @else
                                          <div class="w-12 h-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center shrink-0">
                                              <flux:icon.cube class="w-6 h-6 text-zinc-400" />
                                          </div>
                                      @endif
                                      <div class="flex-1 min-w-0">
                                          <p class="font-semibold text-zinc-900 dark:text-zinc-100 flex items-center flex-wrap gap-1.5">
                                              {{ $item->item->name }}
                                              @if(($item->custom_attributes && count($item->custom_attributes) > 0) || ($item->custom_attachments && count($item->custom_attachments) > 0))
                                                  <span class="inline-flex items-center gap-0.5 text-[9px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 px-1.5 py-0.5 rounded shadow-sm">
                                                      <flux:icon.sparkles class="w-2.5 h-2.5 text-emerald-600" /> CUSTOM
                                                  </span>
                                              @endif
                                          </p>
                                          
                                          @if($item->custom_attributes && count($item->custom_attributes) > 0)
                                              <div class="flex flex-wrap gap-1 mt-1.5">
                                                  @foreach($item->custom_attributes as $attr)
                                                      @if(is_array($attr))
                                                          <span class="inline-block px-1.5 py-0.5 text-[9px] font-medium bg-amber-50 text-amber-700 border border-amber-200 rounded-sm">{{ $attr['key'] ?? '' }}: {{ $attr['value'] ?? '' }}</span>
                                                      @endif
                                                  @endforeach
                                              </div>
                                          @endif
                                          
                                          @if($item->notes)
                                              <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1.5 italic prose prose-xs prose-p:my-0 leading-tight">
                                                  {!! $item->notes !!}
                                              </div>
                                          @endif
                                      </div>
                                </div>
                                
                                <div class="flex items-center justify-between text-sm bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 mt-2">
                                    <div class="text-center">
                                        <p class="text-xs text-zinc-500 mb-0.5">Qty</p>
                                        <p class="font-bold text-zinc-900 dark:text-white">{{ $item->qty }} <span class="text-[10px] text-zinc-500 font-normal">{{ $item->item->unit->name ?? 'pcs' }}</span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-zinc-500 mb-0.5">Harga Satuan</p>
                                        <p class="font-medium text-zinc-700 dark:text-zinc-300">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-zinc-500 mb-0.5">Subtotal</p>
                                        <p class="font-black text-zinc-900 dark:text-white">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        
                        <!-- Mobile Totals -->
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/30 space-y-2.5">
                            <div class="flex justify-between text-sm items-center">
                                <span class="text-zinc-500 dark:text-zinc-400 font-medium">Subtotal</span>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->items->sum('subtotal'), 0, ',', '.') }}</span>
                            </div>
                            @if($quotation->discount > 0)
                            <div class="flex justify-between text-sm items-center text-red-500">
                                <span class="font-medium">Diskon</span>
                                <span class="font-bold">- Rp {{ number_format($quotation->discount, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            @if($quotation->shipping_fee > 0)
                            <div class="flex justify-between text-sm items-center">
                                <span class="text-zinc-500 dark:text-zinc-400 font-medium">Biaya Pengiriman</span>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->shipping_fee, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            @if($quotation->tax > 0)
                            <div class="flex justify-between text-sm items-center">
                                <span class="text-zinc-500 dark:text-zinc-400 font-medium">Pajak (PPN)</span>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">Rp {{ number_format($quotation->tax, 0, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between text-base pt-3 border-t border-zinc-200 dark:border-zinc-700/50 mt-3 items-center">
                                <span class="font-black text-zinc-900 dark:text-white">TOTAL</span>
                                <span class="font-black text-emerald-600 dark:text-emerald-400 text-lg">Rp {{ number_format($quotation->total_amount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
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
                            @elseif($quotation->customer_phone)
                            <div class="flex items-center gap-2">
                                <flux:icon.phone class="w-3.5 h-3.5 text-zinc-400" />
                                <span>{{ $quotation->customer_phone }}</span>
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

@endif
</flux:modal>
