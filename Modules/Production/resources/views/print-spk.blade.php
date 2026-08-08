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
            html, body {
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
                background-color: transparent !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin-top: 1cm;
                margin-left: 1cm;
                margin-right: 1cm;
                margin-bottom: 0.3cm; /* Make it as flush to bottom as printer allows */
            }
            .page-container {
                width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                min-height: 99vh !important; /* Forces stretch to bottom, 99vh prevents accidental extra blank page */
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
        .page-flex {
            display: flex;
            flex-direction: column;
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

<div class="page-container page-flex">
    @if($po->status === 'pending_approval')
        <div class="flex flex-col items-center justify-center h-[50vh] text-center">
            <div class="w-20 h-20 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mb-4">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-zinc-900 mb-2">Menunggu Persetujuan Finance</h2>
            <p class="text-zinc-500 max-w-md">Dokumen SPK ini belum bisa dicetak atau diberikan ke Vendor karena sedang menunggu validasi dan persetujuan (ACC) dari departemen Keuangan.</p>
        </div>
    @else
        <!-- HALAMAN 1: SUMMARY -->
        <div class="flex flex-col flex-1 relative">
            {{-- Document Header --}}
            <div class="flex justify-end items-start border-b-2 border-zinc-900 pb-6 mb-6">
                <div class="text-right">
                    <h1 class="text-3xl font-black text-zinc-900 tracking-tight uppercase">SPK <span class="text-md normal-case">(Surat perintah kerja)</span></h1>
                    <div class="text-zinc-600 font-medium mt-1 font-mono">{{ $po->po_number }}</div>
                </div>
            </div>

            {{-- Meta Info --}}
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-[10px] font-bold text-zinc-500 uppercase tracking-wider mb-1">Ditujukan Kepada (Vendor):</h3>
                    <div class="font-bold text-lg text-zinc-900 leading-none">{{ $po->vendor->name ?? '-' }}</div>
                    <div class="text-xs text-zinc-600 mt-1">
                        @if($po->vendor && $po->vendor->phone)
                            <span class="mr-2"><flux:icon.phone class="w-3 h-3 inline -mt-0.5" /> {{ $po->vendor->phone }}</span>
                        @endif
                        @if($po->vendor && ($po->vendor->district || $po->vendor->city))
                            <span>{{ $po->vendor->district ?? '' }} {{ $po->vendor->district && $po->vendor->city ? ',' : '' }} {{ $po->vendor->city ?? '' }}</span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 mt-3">
                    <div class="flex flex-col border border-dashed border-zinc-300 rounded-lg py-1.5 px-3 bg-zinc-50/50">
                        <span class="text-[9px] text-zinc-500 font-bold uppercase tracking-wider mb-0.5">Waktu Pengerjaan</span>
                        <div class="font-bold text-sm text-zinc-900 flex items-center gap-2">
                            <span>{{ $po->order_date ? \Carbon\Carbon::parse($po->order_date)->format('d M Y') : '-' }}</span>
                            <span class="text-[10px] text-zinc-400 font-normal italic">s/d</span> 
                            <span class="text-red-600">{{ $po->expected_delivery_date ? \Carbon\Carbon::parse($po->expected_delivery_date)->format('d M Y') : '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Items Table --}}
            <table class="w-full text-left border-collapse mb-8">
                <thead>
                    <tr class="border-y-2 border-zinc-900">
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900">No</th>
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900">Daftar Barang</th>
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900 text-center">Fase Pekerjaan</th>
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900 text-center">Kuantitas</th>
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900 text-right">Harga Jasa</th>
                        <th class="py-3 px-2 text-sm font-bold text-zinc-900 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach($po->items as $index => $item)
                        <tr>
                            <td class="py-3 px-2 text-sm text-zinc-800 align-top">{{ $index + 1 }}</td>
                            <td class="py-3 px-2 text-sm font-medium text-zinc-900 align-top">
                                @if($item->item->alias)
                                    <span class="font-bold">{{ $item->item->alias }}</span> <span class="text-xs text-zinc-500 normal-case font-normal">- {{ $item->item->name }}</span>
                                @else
                                    {{ $item->item->name }}
                                @endif
                            </td>
                            <td class="py-3 px-2 text-sm text-zinc-800 text-center capitalize align-top">{{ \Modules\Production\Models\ProductionOrder::where('purchase_order_id', $po->id)->where('item_id', $item->item_id)->value('phase_type') ?? 'Jasa Luar' }}</td>
                            <td class="py-3 px-2 text-sm text-zinc-800 text-center align-top">{{ $item->quantity }}</td>
                            <td class="py-3 px-2 text-sm text-zinc-800 text-right align-top">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                            <td class="py-3 px-2 text-sm font-medium text-zinc-900 text-right align-top">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-zinc-900">
                        <td colspan="5" class="p-3 text-right font-bold uppercase tracking-wider text-sm text-zinc-900">Grand Total Biaya Jasa:</td>
                        <td class="p-3 text-right text-xl font-black bg-zinc-100 text-zinc-900">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>

            @if($po->notes)
                <div class="mb-12 border border-zinc-200 rounded-lg p-5 bg-zinc-50 relative">
                    <div class="absolute -top-3 left-4 bg-white px-2 text-xs font-bold text-zinc-500 uppercase tracking-wider border border-zinc-200 rounded">Catatan Global SPK</div>
                    <div class="text-sm text-zinc-800 leading-relaxed whitespace-pre-wrap">{!! nl2br(strip_tags($po->notes)) !!}</div>
                </div>
            @endif

            {{-- Signatures --}}
            <div class="grid grid-cols-3 gap-8 mt-auto pt-8 text-center text-sm text-zinc-900 break-inside-avoid">
                <div>
                    <div class="mb-16 font-medium text-zinc-600">Dibuat Oleh,</div>
                    <div class="border-b border-zinc-400 mx-8 pb-1 font-bold">{{ $po->creator->name ?? 'Admin' }}</div>
                    <div class="text-xs text-zinc-500 mt-1">Admin Produksi</div>
                </div>
                <div>
                    <div class="mb-16 font-medium text-zinc-600">Disetujui Oleh,</div>
                    <div class="border-b border-zinc-400 mx-8 pb-1 text-zinc-300">(....................................)</div>
                    <div class="text-xs text-zinc-500 mt-1">Manajer Produksi</div>
                </div>
                <div>
                    <div class="mb-16 font-medium text-zinc-600">Diterima Oleh Vendor,</div>
                    <div class="border-b border-zinc-400 mx-8 pb-1 font-bold">{{ $po->vendor->name ?? '' }}</div>
                    <div class="text-xs text-zinc-500 mt-1">Tanda Tangan & Cap</div>
                </div>
            </div>
        </div>

        {{-- HALAMAN 2: LAMPIRAN (Hanya muncul jika ada notes item) --}}
        @php
            $itemsWithNotes = $po->items->filter(function($item) {
                return !empty($item->notes);
            });
        @endphp
        
        @if($itemsWithNotes->count() > 0)
        </div>
        <div class="page-container break-before-page">
            <div class="text-center border-b-2 border-zinc-900 pb-6 mb-8">
                <h2 class="text-2xl font-black uppercase tracking-wider text-zinc-900">Lampiran SPK Vendor</h2>
                <div class="text-zinc-600 mt-1">Rincian Pekerjaan & Catatan Khusus Instruksi Vendor</div>
            </div>

            <div class="space-y-8">
                @foreach($itemsWithNotes as $item)
                    <div class="border border-zinc-200 rounded-lg overflow-hidden flex flex-col break-inside-avoid">
                        <div class="bg-zinc-100 px-4 py-3 border-b border-zinc-200 flex justify-between items-center">
                            <h4 class="font-bold text-zinc-900 text-lg">
                                @if($item->item->alias)
                                    {{ $item->item->alias }} <span class="text-sm text-zinc-500 normal-case font-medium ml-1">- {{ $item->item->name }}</span>
                                @else
                                    {{ $item->item->name }}
                                @endif
                            </h4>
                            <span class="text-sm font-medium bg-white px-2 py-1 rounded border border-zinc-200 shadow-sm">Kuantitas: {{ max(1, $item->quantity) }} {{ $item->item->unit->name ?? 'pcs' }}</span>
                        </div>
                        <div class="flex p-6 gap-6 bg-white">
                            @if($item->item->image)
                                <div class="w-1/3 shrink-0">
                                    <div class="aspect-square w-full bg-zinc-50 border border-zinc-200 rounded-lg flex items-center justify-center p-2 shadow-inner">
                                        <img src="{{ asset('storage/' . $item->item->image) }}" class="max-w-full max-h-full object-contain rounded drop-shadow-sm" alt="{{ $item->item->name }}">
                                    </div>
                                </div>
                            @else
                                <div class="w-1/3 shrink-0">
                                    <div class="aspect-square w-full bg-zinc-50 border border-zinc-200 rounded-lg flex flex-col items-center justify-center p-4 text-zinc-400 shadow-inner">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-xs text-center font-medium">Tidak ada gambar</span>
                                    </div>
                                </div>
                            @endif
                            <div class="w-2/3 prose prose-sm max-w-none text-zinc-800 leading-relaxed">
                                <h5 class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-3 border-b border-zinc-100 pb-2">Instruksi Khusus Vendor:</h5>
                                {!! nl2br(strip_tags($item->notes)) !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        
    @endif
</div>
</body>
</html>
