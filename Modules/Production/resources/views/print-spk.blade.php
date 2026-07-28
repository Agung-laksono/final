<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ strtolower($po->po_number) }} - SPK Maklon</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
            color: #111;
        }
        @media print {
            body {
                background-color: transparent !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            .page-container {
                width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                min-height: auto !important;
                background-color: transparent !important;
            }
            .break-before-page { page-break-before: always; }
            .break-inside-avoid { page-break-inside: avoid; }
        }
        .page-container {
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto 2cm auto;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            padding: 1.5cm;
        }
    </style>
</head>
<body class="bg-white text-zinc-900 text-sm">

<!-- Action Bar -->
<div class="flex justify-between items-center mb-8 no-print border-b pb-4 p-4 bg-zinc-50">
    <button onclick="window.close()" class="px-4 py-2 bg-white border border-zinc-300 text-zinc-700 rounded-md text-sm font-medium hover:bg-zinc-50 flex items-center gap-2 shadow-sm transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        Tutup Tab
    </button>
    <div class="flex items-center gap-4">
        <form action="" method="GET" class="flex items-center gap-2">
            <label class="text-sm font-medium text-zinc-700">Mode:</label>
            <select name="mode" onchange="this.form.submit()" class="border-zinc-300 rounded-md shadow-sm text-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                <option value="compact" {{ request('mode') === 'compact' ? 'selected' : '' }}>Padat</option>
                <option value="half" {{ request('mode') === 'half' ? 'selected' : '' }}>1/2 Halaman per Item</option>
                <option value="full" {{ request('mode') === 'full' ? 'selected' : '' }}>1 Halaman per Item</option>
            </select>
        </form>
        @if($po->status !== 'pending_approval')
            <button onclick="window.print()" class="px-4 py-2 bg-zinc-900 text-white rounded-md text-sm font-medium hover:bg-zinc-800 flex items-center gap-2 shadow-sm transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak SPK
            </button>
        @endif
    </div>
</div>

<div class="page-container">
    @if($po->status === 'pending_approval')
        <div class="flex flex-col items-center justify-center h-[50vh] text-center no-print">
            <div class="w-20 h-20 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mb-4">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-zinc-900 mb-2">Menunggu Persetujuan Finance</h2>
            <p class="text-zinc-500 max-w-md">Dokumen SPK ini belum bisa dicetak atau diberikan ke Vendor karena sedang menunggu validasi dan persetujuan (ACC) dari departemen Keuangan.</p>
        </div>
    @else
        <!-- Header -->
        <div class="flex justify-between items-start border-b-2 border-zinc-900 pb-4 mb-6">
            <div>
                @if($po->creator->brand && $po->creator->brand->logo)
                    <img src="{{ Storage::url($po->creator->brand->logo) }}" alt="Logo" class="h-16 w-auto object-contain mb-2">
                @endif
                <h1 class="text-2xl font-black text-zinc-900 tracking-tight">{{ $po->creator->brand ? $po->creator->brand->name : 'PERUSAHAAN' }}</h1>
                <p class="text-sm text-zinc-800 whitespace-pre-line">{{ $po->creator->brand ? $po->creator->brand->address : 'Jl. Industri No. 123, Jepara, Jawa Tengah' }}
@if($po->creator->brand && $po->creator->brand->phone)Telp: {{ $po->creator->brand->phone }}@else Telp: (0291) 123456 @endif</p>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-zinc-900">SURAT PERINTAH KERJA</h2>
                <p class="text-sm text-zinc-800 font-mono mt-1">{{ $po->po_number }}</p>
                <p class="text-sm text-zinc-800 mt-1">Tanggal: {{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d M Y') : '-' }}</p>
            </div>
        </div>

        <!-- Info -->
        <div class="flex justify-between mb-8">
            <div>
                <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Kepada Vendor / Mitra:</h3>
                <p class="text-base font-bold text-zinc-900">{{ $po->vendor->name }}</p>
                <p class="text-sm text-zinc-800">{{ $po->vendor->address ?? 'Alamat tidak tersedia' }}</p>
                <p class="text-sm text-zinc-800">{{ $po->vendor->phone ?? 'Telp: -' }}</p>
            </div>
            <div class="text-right">
                <h3 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-1">Dibuat Oleh:</h3>
                <p class="text-base font-medium text-zinc-900">{{ $po->creator->name ?? 'Admin' }}</p>
                <p class="text-sm text-zinc-800 mt-1">Tenggat Waktu: <strong class="text-red-700 bg-red-100 border border-red-200 px-1.5 py-0.5 rounded font-black print:text-black print:bg-transparent print:border-none">{{ $po->expected_delivery_date ? \Carbon\Carbon::parse($po->expected_delivery_date)->format('d M Y') : '-' }}</strong></p>
            </div>
        </div>

        <!-- Items Table -->
        <table class="w-full text-left border-collapse mb-8">
            <thead>
                <tr class="border-y-2 border-black">
                    <th class="py-3 px-2 text-sm font-bold text-black">No</th>
                    <th class="py-3 px-2 text-sm font-bold text-black">Nama Barang</th>
                    <th class="py-3 px-2 text-sm font-bold text-black text-center">Fase Pekerjaan</th>
                    <th class="py-3 px-2 text-sm font-bold text-black text-center">Qty</th>
                    <th class="py-3 px-2 text-sm font-bold text-black text-right">Ongkos/Unit</th>
                    <th class="py-3 px-2 text-sm font-bold text-black text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-300">
                @foreach($po->items as $index => $item)
                <tr>
                    <td class="py-3 px-2 text-sm text-black align-top">{{ $index + 1 }}</td>
                    <td class="py-3 px-2 text-sm font-medium text-black align-top">
                        <div class="flex gap-3">
                            <div class="w-12 h-12 bg-zinc-200 border border-zinc-300 rounded overflow-hidden shrink-0 print:border-black">
                                @if(!empty($item->custom_attachments))
                                    <img src="{{ Storage::url($item->custom_attachments[0]) }}" class="w-full h-full object-cover">
                                @elseif(isset($item->item->image) && $item->item->image)
                                    <img src="{{ Storage::url($item->item->image) }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-zinc-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    </div>
                                @endif
                            </div>
                            <div class="pt-0.5">
                                @if($item->item->alias)
                                    <span class="font-bold">{{ $item->item->alias }}</span> <span class="text-xs text-zinc-600 normal-case font-normal print:text-zinc-800">- {{ $item->item->name }}</span>
                                @else
                                    {{ $item->item->name }}
                                @endif
                                @if($item->item->code)
                                    <div class="font-mono text-[10px] text-zinc-500 normal-case mt-0.5 print:text-zinc-700">{{ $item->item->code }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-2 text-sm text-black text-center capitalize align-top">{{ $po->vendor?->type ?? 'Jasa Luar' }}</td>
                    <td class="py-3 px-2 text-sm text-black text-center align-top">{{ $item->quantity }}</td>
                    <td class="py-3 px-2 text-sm text-black text-right align-top">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="py-3 px-2 text-sm font-medium text-black text-right align-top">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-black">
                    <td colspan="5" class="py-3 px-2 text-right text-sm font-bold text-black">Grand Total</td>
                    <td class="py-3 px-2 text-right text-base font-black text-black">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Notes -->
        <div class="mb-12">
            <h4 class="text-sm font-bold text-black mb-1">Catatan Tambahan:</h4>
            <div class="text-sm text-zinc-800 p-3 print:bg-transparent border border-zinc-300 rounded min-h-[80px] prose prose-sm max-w-none prose-p:my-0 prose-table:my-2 prose-td:p-1.5 prose-th:p-1.5 prose-td:border prose-td:border-black prose-th:border prose-th:border-black whitespace-pre-wrap">
                {!! $po->notes ?: 'Tolong dikerjakan sesuai standar kualitas perusahaan. Terima kasih.' !!}
            </div>
        </div>

        <!-- Signatures -->
        <div class="flex justify-between items-end">
            <div class="w-1/3"></div>
            <div class="flex gap-16 text-center">
                <div>
                    <p class="text-sm text-zinc-800 mb-16">Diterima Oleh (Vendor),</p>
                    <div class="w-40 border-b border-black"></div>
                    <p class="text-sm font-medium text-black mt-1">{{ $po->vendor->name }}</p>
                </div>
                <div>
                    <p class="text-sm text-zinc-800 mb-16">Hormat Kami,</p>
                    <div class="w-40 border-b border-black"></div>
                    <p class="text-sm font-medium text-black mt-1">{{ $po->creator->brand ? $po->creator->brand->name : 'PT. Agung Laksono' }}</p>
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
        <div class="break-before-page mt-4 print:mt-0">
            <h3 class="text-xl font-bold text-black mb-4 border-b-2 border-black pb-2">Lampiran: Panduan Pengerjaan Detail</h3>
            
            <div class="space-y-4">
                @foreach($itemsWithNotes as $item)
                <div class="print:bg-transparent border border-zinc-300 rounded-lg p-4 break-inside-avoid
                    {{ $printMode === 'half' ? 'min-h-[125mm]' : 'min-h-[125mm]' }}
                    {{ $printMode === 'full' ? 'min-h-[260mm] break-before-page' : '' }}
                ">
                    <div class="flex gap-6">
                        <!-- Image Column -->
                        <div class="w-1/3 shrink-0">
                            @if(!empty($item->custom_attachments))
                                <img src="{{ Storage::url($item->custom_attachments[0]) }}" class="w-full h-auto object-contain rounded-lg border border-zinc-200 print:border-black shadow-sm" style="max-height: 200px;">
                            @elseif(isset($item->item->image) && $item->item->image)
                                <img src="{{ Storage::url($item->item->image) }}" class="w-full h-auto object-contain rounded-lg border border-zinc-200 print:border-black shadow-sm" style="max-height: 200px;">
                            @else
                                <div class="w-full h-40 bg-zinc-100 print:bg-transparent flex items-center justify-center text-zinc-400 rounded-lg border border-zinc-200 print:border-black">
                                    <svg class="w-12 h-12 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Details Column -->
                        <div class="flex-1">
                            <h4 class="text-xl font-bold text-black mb-1 leading-tight">
                                @if($item->item->alias)
                                    {{ $item->item->alias }} <span class="text-base text-zinc-600 normal-case font-medium ml-1">- {{ $item->item->name }}</span>
                                @else
                                    {{ $item->item->name }}
                                @endif
                            </h4>
                            <div class="text-lg text-zinc-800 mb-4 border-b border-zinc-300 pb-3 mt-2 flex items-center gap-4">
                                <div>Qty: <strong class="text-black text-2xl">{{ $item->quantity }}</strong></div>
                                @if($item->item->code)
                                    <div class="border-l-2 border-zinc-300 pl-4">Kode: <strong class="font-mono text-black text-xl">{{ $item->item->code }}</strong></div>
                                @endif
                            </div>
                            
                            <div class="prose prose-sm max-w-none text-black prose-p:my-1 prose-table:my-2 prose-td:p-1.5 prose-th:p-1.5 prose-td:border prose-td:border-black prose-th:border prose-th:border-black whitespace-pre-wrap">
                                {!! preg_replace('/<p><strong>Jasa Vendor Fase:<\/strong>.*?<\/p>/i', '', $item->notes) !!}
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        
    @endif
</div>
</body>
</html>
