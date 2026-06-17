<?php
use function Livewire\Volt\{state, on};
use Modules\Sales\Models\SalesOrder;

state([
    'show' => false,
    'orderId' => null,
    'order' => null,
]);

on(['open-detail-modal' => function ($orderId) {
    $this->orderId = $orderId;
    $this->order = SalesOrder::with(['customer', 'items.item', 'creator', 'payments', 'fulfillments'])->find($orderId);
    if ($this->order) {
        $this->show = true;
    }
}]);

?>

<flux:modal wire:model="show" class="w-full md:w-[50rem] md:max-w-4xl">
    @if($order)
    <div class="p-4 sm:p-6">
        {{-- Header Modal --}}
        <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-6 pb-6 border-b border-zinc-200 dark:border-zinc-700">
            <div>
                <flux:heading size="xl" class="flex items-center gap-2">
                    SO {{ $order->so_number }}
                    <flux:badge size="sm" color="{{ 
                        $order->status === 'completed' ? 'emerald' : 
                        ($order->status === 'shipping' ? 'orange' : 
                        ($order->status === 'packing' ? 'purple' : 
                        ($order->status === 'processing' ? 'blue' : 'amber'))) 
                    }}">
                        {{ strtoupper($order->status) }}
                    </flux:badge>
                </flux:heading>
                <div class="mt-2 text-sm text-zinc-500 flex flex-wrap items-center gap-3">
                    <span class="flex items-center gap-1"><flux:icon.calendar class="w-4 h-4" /> {{ \Carbon\Carbon::parse($order->order_date)->format('d M Y') }}</span>
                    <span class="flex items-center gap-1"><flux:icon.user class="w-4 h-4" /> Dibuat oleh {{ $order->creator->name ?? 'Sistem' }}</span>
                </div>
            </div>
            
            <div class="flex gap-2">
                <flux:button variant="subtle" icon="printer">Cetak Nota</flux:button>
                {{-- Double X button removed, flux:modal already provides one --}}
            </div>
        </div>

        @php
            // Tim gudang biasa (tanpa role manajerial) tidak boleh melihat harga
            $canViewPrices = !(auth()->user()->hasRole('Gudang') && !auth()->user()->hasAnyRole(['Super Admin', 'Manager', 'Kepala Sales']));
        @endphp

        <div class="{{ $canViewPrices ? 'grid grid-cols-1 lg:grid-cols-3 gap-6' : 'flex flex-col gap-6' }}">
            {{-- Kolom Kiri/Atas: Info Pelanggan & Keuangan --}}
            <div class="{{ $canViewPrices ? 'space-y-6' : 'w-full md:w-1/2' }}">
                {{-- Info Pelanggan --}}
                <div>
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Informasi Pelanggan</h3>
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center gap-3">
                        <flux:avatar src="{{ $order->customer->image ? Storage::url($order->customer->image) : '' }}" fallback="{{ substr($order->customer->name, 0, 2) }}" />
                        <div class="flex-1 overflow-hidden">
                            <h4 class="font-bold text-sm text-zinc-900 dark:text-zinc-100 truncate">{{ $order->customer->name }}</h4>
                            <p class="text-xs text-zinc-500 truncate">{{ $order->customer->phone ?? 'Tidak ada kontak' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Status Keuangan --}}
                @if($canViewPrices)
                <div>
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Status Keuangan</h3>
                    <div class="bg-emerald-50 dark:bg-emerald-900/10 p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/50">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-emerald-800 dark:text-emerald-300">Pembayaran</span>
                            <flux:badge size="sm" color="{{ $order->payment_status === 'paid' ? 'green' : ($order->payment_status === 'partial' ? 'amber' : 'red') }}">
                                {{ strtoupper($order->payment_status) }}
                            </flux:badge>
                        </div>
                        <div class="text-2xl font-black text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                        </div>
                        
                        @php
                            $terbayar = collect($order->payments)->sum('amount');
                            $sisa = $order->total_amount - $terbayar;
                        @endphp
                        <div class="mt-3 pt-3 border-t border-emerald-200 dark:border-emerald-800/50 text-xs space-y-1">
                            <div class="flex justify-between text-emerald-700 dark:text-emerald-500">
                                <span>Telah Dibayar</span>
                                <span>Rp {{ number_format($terbayar, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-800 dark:text-emerald-400 font-bold">
                                <span>Sisa Tagihan</span>
                                <span>Rp {{ number_format(max(0, $sisa), 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Kolom Kanan: Daftar Barang & Logistik --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Daftar Barang --}}
                <div>
                    <h3 class="text-xs font-bold text-zinc-400 uppercase tracking-wider mb-3">Daftar Pesanan</h3>
                    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-sm whitespace-nowrap min-w-[30rem]">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                                <tr>
                                    <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300">Barang</th>
                                    <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300 text-right">Qty</th>
                                    @if($canViewPrices)
                                    <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300 text-right">Harga</th>
                                    <th class="px-4 py-2 font-semibold text-zinc-600 dark:text-zinc-300 text-right">Subtotal</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($order->items as $item)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->item->name }}</div>
                                            @if($item->notes)
                                                <div class="text-[10px] text-zinc-500 mt-0.5">{{ $item->notes }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">{{ $item->qty }}</td>
                                        @if($canViewPrices)
                                        <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-zinc-900 dark:text-zinc-100">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            @if($canViewPrices)
                            <tfoot class="bg-zinc-50 dark:bg-zinc-800/50 text-right">
                                @if($order->packing_fee > 0)
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-zinc-500">
                                        Biaya Packing
                                        @if($order->packing_receipt_path)
                                            <a href="{{ Storage::url($order->packing_receipt_path) }}" target="_blank" class="ml-2 inline-flex items-center text-xs text-purple-600 hover:text-purple-700 underline" title="Lihat Bukti Nota"><flux:icon.document-text class="w-3 h-3 mr-0.5" /> Bukti</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-medium">Rp {{ number_format($order->packing_fee, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if($order->shipping_fee > 0)
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-zinc-500">
                                        Ongkos Kirim ({{ $order->courier_vendor }})
                                        @if($order->shipping_receipt_path)
                                            <a href="{{ Storage::url($order->shipping_receipt_path) }}" target="_blank" class="ml-2 inline-flex items-center text-xs text-orange-600 hover:text-orange-700 underline" title="Lihat Resi Asli"><flux:icon.document-text class="w-3 h-3 mr-0.5" /> Bukti</a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-medium">Rp {{ number_format($order->shipping_fee, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                                @if($order->discount > 0)
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-zinc-500">Diskon</td>
                                    <td class="px-4 py-2 font-medium text-red-500">- Rp {{ number_format($order->discount, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</flux:modal>
