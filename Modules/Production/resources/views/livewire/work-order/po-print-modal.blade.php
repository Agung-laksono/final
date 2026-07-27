<?php

use function Livewire\Volt\{state, on, computed};
use Modules\Purchase\Models\PurchaseOrder;

state([
    'show' => false,
    'poId' => null,
    'po' => null,
    'printMode' => 'compact',
]);

on(['open-po-print-modal' => function ($poId) {
    $this->poId = $poId;
    $this->po = PurchaseOrder::with(['vendor', 'items.item', 'creator'])->find($poId);
    $this->show = true;
}]);

$print = function () {
    $this->js('window.print()');
};

?>

<div>
    <flux:modal wire:model="show" class="md:w-[850px] max-w-full p-0 overflow-hidden print:p-0 print:m-0 print:border-none print:shadow-none" id="po-print-modal">
        @if($po)
            <div class="bg-white dark:bg-zinc-900 rounded-xl overflow-hidden print:bg-white print:text-black">
                
                <!-- Action Bar (Hidden when printing) -->
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 print:hidden">
                    <flux:heading>Preview SPK Jasa Luar</flux:heading>
                    <div class="flex items-center gap-2">
                        <div class="w-48 hidden sm:block">
                            <flux:select wire:model.live="printMode" size="sm">
                                <option value="compact">Mode: Padat</option>
                                <option value="half">Mode: 1/2 Halaman per Item</option>
                                <option value="full">Mode: 1 Halaman per Item</option>
                            </flux:select>
                        </div>
                        <flux:button variant="ghost" wire:click="$set('show', false)"> Tutup </flux:button>
                        @if($po->status !== 'pending_approval')
                            <flux:button variant="primary" icon="printer" wire:click="print">Cetak Dokumen</flux:button>
                        @endif
                    </div>
                </div>

                <!-- Print Area (A4 Simulation) -->
                <div class="p-8 print:p-0 mx-auto bg-white dark:bg-zinc-900 print:bg-white text-zinc-900 dark:text-zinc-100 print:text-black" id="print-area" style="max-width: 210mm; min-height: 297mm;">
                    
                    @if($po->status === 'pending_approval')
                        <div class="flex flex-col items-center justify-center h-[50vh] text-center print:hidden">
                            <div class="w-20 h-20 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center mb-4">
                                <flux:icon.clock class="w-10 h-10" />
                            </div>
                            <h2 class="text-2xl font-bold text-zinc-900 dark:text-white mb-2">Menunggu Persetujuan Finance</h2>
                            <p class="text-zinc-500 max-w-md">Dokumen SPK ini belum bisa dicetak atau diberikan ke Vendor karena sedang menunggu validasi dan persetujuan (ACC) dari departemen Keuangan.</p>
                        </div>
                    @else
                    <!-- Header -->
                    <div class="flex justify-between items-start border-b-2 border-zinc-900 dark:border-zinc-100 pb-4 mb-6">
                        <div>
                            <h1 class="text-2xl font-black text-zinc-900 dark:text-white print:text-black tracking-tight">PT. AGUNG LAKSONO</h1>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800">Jl. Industri No. 123, Jepara, Jawa Tengah<br>Telp: (0291) 123456</p>
                        </div>
                        <div class="text-right">
                            <h2 class="text-xl font-bold text-zinc-900 dark:text-white print:text-black">SURAT PERINTAH KERJA</h2>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800 font-mono mt-1">{{ $po->po_number }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800 mt-1">Tanggal: {{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d M Y') : '-' }}</p>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="flex justify-between mb-8">
                        <div>
                            <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Kepada Vendor / Mitra:</h3>
                            <p class="text-base font-bold text-zinc-900 dark:text-white print:text-black">{{ $po->vendor->name }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800">{{ $po->vendor->address ?? 'Alamat tidak tersedia' }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800">{{ $po->vendor->phone ?? 'Telp: -' }}</p>
                        </div>
                        <div class="text-right">
                            <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Dibuat Oleh:</h3>
                            <p class="text-base font-medium text-zinc-900 dark:text-white print:text-black">{{ $po->creator->name ?? 'Admin' }}</p>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800">Tenggat Waktu: <strong class="text-zinc-900 dark:text-zinc-100 print:text-black">{{ $po->expected_delivery_date ? \Carbon\Carbon::parse($po->expected_delivery_date)->format('d M Y') : '-' }}</strong></p>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <table class="w-full text-left border-collapse mb-8">
                        <thead>
                            <tr class="border-y-2 border-zinc-900 dark:border-zinc-100 print:border-black">
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black">No</th>
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black">Nama Barang</th>
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black text-center">Fase Pekerjaan</th>
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black text-center">Qty</th>
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black text-right">Ongkos/Unit</th>
                                <th class="py-3 px-2 text-sm font-bold text-zinc-900 dark:text-white print:text-black text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 print:divide-zinc-300">
                            @foreach($po->items as $index => $item)
                            <tr>
                                <td class="py-3 px-2 text-sm text-zinc-800 dark:text-zinc-200 print:text-black align-top">{{ $index + 1 }}</td>
                                <td class="py-3 px-2 text-sm font-medium text-zinc-900 dark:text-white print:text-black align-top">
                                    @if($item->item->alias)
                                        <span class="font-bold">{{ $item->item->alias }}</span> <span class="text-xs text-zinc-500 normal-case font-normal">- {{ $item->item->name }}</span>
                                    @else
                                        {{ $item->item->name }}
                                    @endif
                                </td>
                                <td class="py-3 px-2 text-sm text-zinc-800 dark:text-zinc-200 print:text-black text-center capitalize align-top">{{ \Modules\Production\Models\ProductionOrder::where('purchase_order_id', $po->id)->where('item_id', $item->item_id)->value('phase_type') ?? 'Jasa Luar' }}</td>
                                <td class="py-3 px-2 text-sm text-zinc-800 dark:text-zinc-200 print:text-black text-center align-top">{{ $item->quantity }}</td>
                                <td class="py-3 px-2 text-sm text-zinc-800 dark:text-zinc-200 print:text-black text-right align-top">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td class="py-3 px-2 text-sm font-medium text-zinc-900 dark:text-white print:text-black text-right align-top">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-zinc-900 dark:border-zinc-100 print:border-black">
                                <td colspan="5" class="py-3 px-2 text-right text-sm font-bold text-zinc-900 dark:text-white print:text-black">Grand Total</td>
                                <td class="py-3 px-2 text-right text-base font-black text-zinc-900 dark:text-white print:text-black">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Notes -->
                    <div class="mb-12">
                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white print:text-black mb-1">Catatan Tambahan:</h4>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800 p-3 bg-zinc-50 dark:bg-zinc-800/30 print:bg-transparent print:border print:border-zinc-300 rounded min-h-[80px] prose prose-sm max-w-none prose-p:my-0 prose-table:my-2 prose-td:p-1.5 prose-th:p-1.5 prose-td:border prose-td:border-zinc-300 print:prose-td:border-black prose-th:border prose-th:border-zinc-300 print:prose-th:border-black whitespace-pre-wrap">
                            {!! $po->notes ?: 'Tolong dikerjakan sesuai standar kualitas perusahaan. Terima kasih.' !!}
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="flex justify-between items-end">
                        <div class="w-1/3">
                            <!-- Placeholder for additional stamp/info if needed -->
                        </div>
                        <div class="flex gap-16 text-center">
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800 mb-16">Diterima Oleh (Vendor),</p>
                                <div class="w-40 border-b border-zinc-400 dark:border-zinc-600 print:border-black"></div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white print:text-black mt-1">{{ $po->vendor->name }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 print:text-zinc-800 mb-16">Hormat Kami,</p>
                                <div class="w-40 border-b border-zinc-400 dark:border-zinc-600 print:border-black"></div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white print:text-black mt-1">PT. Agung Laksono</p>
                            </div>
                        </div>
                    </div>

                    <!-- Lampiran Panduan Pengerjaan (Page 2) -->
                    @php
                        $itemsWithNotes = $po->items->filter(function($item) {
                            return !empty($item->notes);
                        });
                    @endphp
                    
                    @if($itemsWithNotes->count() > 0)
                    <div class="break-before-page mt-16 print:mt-0 print:pt-16">
                        <h3 class="text-xl font-bold text-zinc-900 dark:text-white print:text-black mb-6 border-b-2 border-zinc-900 dark:border-zinc-100 print:border-black pb-2">Lampiran: Panduan Pengerjaan Detail</h3>
                        
                        <div class="space-y-8">
                            @foreach($itemsWithNotes as $item)
                            <div class="bg-zinc-50 dark:bg-zinc-800/30 print:bg-transparent print:border print:border-zinc-300 rounded-lg p-6 
                                break-inside-avoid
                                {{ $printMode === 'half' ? 'print:min-h-[125mm]' : '' }}
                                {{ $printMode === 'full' ? 'print:min-h-[250mm] break-before-page' : '' }}
                            ">
                                <h4 class="text-lg font-bold text-zinc-900 dark:text-white print:text-black mb-1">
                                    @if($item->item->alias)
                                        {{ $item->item->alias }} <span class="text-sm text-zinc-500 normal-case font-medium ml-1">- {{ $item->item->name }}</span>
                                    @else
                                        {{ $item->item->name }}
                                    @endif
                                </h4>
                                <div class="text-sm text-zinc-500 print:text-zinc-700 mb-4 border-b border-zinc-200 dark:border-zinc-700 print:border-zinc-300 pb-2">
                                    Qty: <strong class="text-zinc-800 dark:text-zinc-200 print:text-black">{{ $item->quantity }}</strong> &nbsp;|&nbsp; 
                                    Fase: <strong class="capitalize text-zinc-800 dark:text-zinc-200 print:text-black">{{ \Modules\Production\Models\ProductionOrder::where('purchase_order_id', $po->id)->where('item_id', $item->item_id)->value('phase_type') ?? 'Jasa Luar' }}</strong>
                                </div>
                                
                                <div class="prose prose-sm max-w-none text-zinc-700 print:text-black prose-p:my-1 prose-table:my-2 prose-td:p-1.5 prose-th:p-1.5 prose-td:border prose-td:border-zinc-300 print:prose-td:border-black prose-th:border prose-th:border-zinc-300 print:prose-th:border-black whitespace-pre-wrap">
                                    {!! $item->notes !!}
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
    
    <!-- CSS for Printing -->
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                max-width: none !important;
                min-height: 0 !important;
            }
            dialog {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                max-height: none !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: visible !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }
            dialog::backdrop {
                display: none !important;
            }
            @page {
                size: A4;
                margin: 1cm;
            }
        }
    </style>
</div>
