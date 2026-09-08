<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman {{ $salesOrder->so_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap');

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @media print {
            body {
                background-color: transparent !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }
            .a4-page {
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                background-color: transparent !important;
            }
            .label-container-half, .label-container-full {
                border-radius: 0 !important;
            }
            .page-break {
                page-break-after: always !important;
                break-after: page !important;
            }
            thead {
                display: table-header-group;
            }
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        /* Lembar A4 Container */
        .a4-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            box-sizing: border-box;
            padding: 0;
        }

        /* Mode 1: Pas ½ A4 (Tinggi Fix 145mm untuk <= 4 item) */
        .label-container-half {
            width: 100%;
            height: 145mm;
            box-sizing: border-box;
            padding: 5mm 6mm;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        /* Mode 2: Full Page / Multi-Page (Dinamis untuk > 4 item) */
        .label-container-full {
            width: 100%;
            min-height: 285mm;
            box-sizing: border-box;
            padding: 6mm 8mm;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-after: always;
            break-after: page;
        }

        .table-label th {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 8px;
            border-bottom: 2px solid #0f172a;
        }
        .table-label td {
            padding: 8px 8px;
            border-bottom: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .cut-line {
            border-bottom: 1.5px dashed #64748b;
            margin: 0;
            position: relative;
            text-align: center;
            height: 1px;
        }
        .cut-line-icon {
            position: absolute;
            top: -8px;
            left: 15mm;
            background: #ffffff;
            padding: 0 6px;
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body class="bg-white text-slate-900 text-xs">

@php
    $itemsCount = $salesOrder->items->count();
    // Jika barang <= 4 item: Mode ½ A4 (2 label dalam 1 lembar A4 atas & bawah)
    // Jika barang > 4 item: Mode Full Page (1 label mengambil 1 lembar A4 full, tidak dibuat 2 lembar)
    $isMultiPage = $itemsCount > 4;
    $totalLabels = $isMultiPage ? 1 : 2;
@endphp

<!-- Action Bar (Screen Only) -->
<div class="flex justify-between items-center mb-4 no-print p-4 bg-slate-900 text-white shadow-lg">
    <div class="flex items-center gap-3">
        <span class="font-black text-sm tracking-wide">LABEL PENGIRIMAN</span>
        @if($isMultiPage)
            <span class="bg-indigo-600 text-white text-[11px] font-mono px-2.5 py-0.5 rounded font-bold">
                MODE FULL PAGE ({{ $itemsCount }} ITEM - 1 LEMBAR FULL)
            </span>
        @else
            <span class="bg-emerald-600 text-white text-[11px] font-mono px-2.5 py-0.5 rounded font-bold">
                MODE ½ A4 ({{ $itemsCount }} ITEM - 2 LABEL / LEMBAR)
            </span>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <button onclick="window.close()" class="px-3.5 py-1.5 bg-slate-800 text-slate-200 rounded-lg text-xs font-semibold hover:bg-slate-700 transition-all border border-slate-700">
            Tutup Tab
        </button>
        <button onclick="window.print()" class="px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-500 transition-all flex items-center gap-1.5 shadow-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Cetak Label Alamat (A4)
        </button>
    </div>
</div>

<!-- LEMBAR A4 -->
<div class="a4-page">
    @for ($labelIndex = 1; $labelIndex <= $totalLabels; $labelIndex++)
        
        <!-- LABEL CONTAINER (DINAMIS SESUAI JUMLAH ITEM) -->
        <div class="{{ $isMultiPage ? 'label-container-full' : 'label-container-half' }}">
            <div>
                <!-- HEADER PENGIRIM, KOLI & NO SO (DIPERBESAR UTAMA) -->
                <div class="flex justify-between items-center border-b-2 border-slate-900 pb-2 mb-2">
                    <!-- PENGIRIM -->
                    <div class="flex items-center gap-3">
                        @if($salesOrder->creator?->brand && $salesOrder->creator->brand->logo)
                            <img src="{{ Storage::url($salesOrder->creator->brand->logo) }}" alt="Logo" class="h-11 w-auto object-contain" style="max-height: 44px; height: 44px; width: auto; object-fit: contain;">
                        @endif
                        <div>
                            <span class="text-[8.5px] font-black text-slate-500 tracking-wider uppercase block">PENGIRIM:</span>
                            <h1 class="text-base font-black tracking-tight text-slate-950 uppercase leading-none mt-0.5">
                                {{ $salesOrder->creator?->brand?->name ?? config('app.name', 'PENGIRIM') }}
                            </h1>
                            @if($salesOrder->creator?->brand?->phone)
                                <p class="text-[10.5px] text-slate-800 font-mono mt-0.5 font-bold">
                                    📞 {{ $salesOrder->creator->brand->phone }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- KANAN: KOLI & NO SO (DIPERBESAR SANGAT JELAS) -->
                    <div class="flex items-center gap-4">
                        <!-- BOX KETERANGAN KOLI MINIMALIS & WIDE -->
                        <div class="border-2 border-slate-900 text-slate-950 px-4 py-1 rounded text-center min-w-[95px]">
                            <span class="text-[8.5px] font-black tracking-widest uppercase block text-slate-800 leading-none">KOLI KE</span>
                            <span class="text-lg font-black font-mono leading-none tracking-widest mt-1 block">
                                &nbsp;&nbsp;&nbsp; / &nbsp;&nbsp;&nbsp;
                            </span>
                        </div>

                        <!-- NO SO (SANGAT BESAR & MENONJOL) -->
                        <div class="text-right flex flex-col items-end border-l-2 border-slate-300 pl-4">
                            <span class="text-[9px] font-black text-slate-500 tracking-wider uppercase block">NO. PESANAN:</span>
                            <div class="text-2xl font-black font-mono text-slate-950 tracking-wider leading-none mt-0.5">
                                {{ $salesOrder->so_number }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOX PENERIMA (2 KOLOM - SPASUS & JELAS) -->
                <div class="border border-slate-900 rounded p-2.5 mb-2 bg-white">
                    <div class="flex justify-between items-center mb-1.5 border-b border-slate-300 pb-1">
                        <span class="text-[9.5px] font-black uppercase text-slate-900 tracking-wider">PENERIMA / TUJUAN PENGIRIMAN:</span>
                        @if(!empty($salesOrder->shipping_address))
                            <span class="text-[8.5px] font-bold border border-slate-900 px-1.5 py-0.2 rounded uppercase">Tujuan Khusus</span>
                        @endif
                    </div>

                    <!-- 2 KOLOM PENERIMA -->
                    <div class="grid grid-cols-12 gap-3 items-start">
                        <!-- KOLOM KIRI (4 col): NAMA & KONTAK PENERIMA -->
                        <div class="col-span-4 p-1">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-wider block">NAMA & KONTAK:</span>
                            <div class="text-base font-black text-slate-950 uppercase leading-snug mt-1">
                                {{ $salesOrder->shipping_recipient_name }}
                            </div>
                            <div class="text-sm font-black text-slate-950 font-mono mt-1.5 flex items-center gap-1">
                                <span>📞</span> <span>{{ $salesOrder->shipping_recipient_phone }}</span>
                            </div>
                        </div>

                        <!-- KOLOM KANAN (8 col): ALAMAT LENGKAP & WILAYAH -->
                        <div class="col-span-8 p-1 border-l border-slate-300 pl-3">
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-wider block mb-1">DETAIL ALAMAT PENGIRIMAN:</span>
                            
                            @if(!empty($salesOrder->shipping_address))
                                <div class="text-xs font-black text-slate-950 leading-relaxed">
                                    {!! nl2br(e($salesOrder->shipping_address)) !!}
                                </div>
                            @else
                                @php $cust = $salesOrder->customer; @endphp
                                <div class="space-y-1">
                                    <!-- Baris 1: Detail Alamat Jalan -->
                                    @if($cust?->address)
                                        <div class="text-xs font-black text-slate-950 leading-snug">
                                            {{ $cust->address }}
                                        </div>
                                    @endif

                                    <!-- Baris 2: Kelurahan & Kecamatan -->
                                    @if($cust?->village || $cust?->district)
                                        <div class="text-[11px] font-bold text-slate-800">
                                            @if($cust?->village) Kel. {{ $cust->village }} @endif
                                            @if($cust?->village && $cust?->district) • @endif
                                            @if($cust?->district) Kec. {{ $cust->district }} @endif
                                        </div>
                                    @endif

                                    <!-- Baris 3: Kota/Kabupaten & Provinsi -->
                                    @if($cust?->city || $cust?->province)
                                        <div class="text-xs font-black text-slate-950 uppercase tracking-wide pt-0.5 border-t border-slate-200 mt-1">
                                            @if($cust?->city) {{ $cust->city }} @endif
                                            @if($cust?->city && $cust?->province) , @endif
                                            @if($cust?->province) {{ $cust->province }} @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- TABEL RINCIAN ITEM BARANG (PADDING SPASUS & PROPORSIAL) -->
                <div class="border border-slate-900 rounded overflow-hidden bg-white mb-2">
                    <table class="w-full text-left table-label border-collapse">
                        <thead>
                            <tr>
                                <th class="w-7 text-center">No.</th>
                                <th class="w-10 text-center">Gambar</th>
                                <th>Kode/SKU, Alias & Nama Produk</th>
                                <th class="w-20 text-center">Jumlah</th>
                                <th class="w-20 text-center">Jml Koli</th>
                                <th class="w-12 text-center">Cek (✓)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salesOrder->items as $index => $soItem)
                                @php
                                    $item = $soItem->item;
                                    $code = $item?->code;
                                    $unitName = $item?->unit?->name ?? 'pcs';
                                    $alias = $item?->alias;
                                @endphp
                                <tr>
                                    <!-- NO -->
                                    <td class="text-center font-mono font-bold text-xs text-slate-800">
                                        {{ $index + 1 }}
                                    </td>

                                    <!-- GAMBAR -->
                                    <td class="text-center p-1">
                                        @if($item?->image)
                                            <img src="{{ Storage::url($item->image) }}" alt="Img" class="w-9 h-9 object-cover rounded border border-slate-300 mx-auto">
                                        @else
                                            <div class="w-9 h-9 rounded border border-slate-300 font-bold text-slate-500 text-[9.5px] flex items-center justify-center mx-auto">
                                                {{ substr($item?->name ?? '?', 0, 2) }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- SKU, ALIAS & NAMA -->
                                    <td>
                                        <div class="flex items-center gap-1.5 flex-wrap py-0.5">
                                            @if($code)
                                                <span class="font-mono text-[9.5px] font-bold border border-slate-400 text-slate-900 px-1 py-0.5 rounded">
                                                    {{ $code }}
                                                </span>
                                            @endif
                                            @if($alias)
                                                <span class="border border-slate-900 text-slate-950 text-[9px] font-black px-1.5 py-0.5 rounded tracking-wide uppercase">
                                                    {{ $alias }}
                                                </span>
                                            @endif
                                            <span class="font-bold text-slate-950 text-xs">
                                                {{ $item?->name ?? 'Produk' }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- JUMLAH & SATUAN -->
                                    <td class="text-center font-mono font-bold text-xs text-slate-950">
                                        <span class="px-2 py-1 border border-slate-400 rounded inline-block">
                                            {{ $soItem->qty }} {{ strtoupper($unitName) }}
                                        </span>
                                    </td>

                                    <!-- JUMLAH KOLI (KOSONG DENGAN BORDER DOTTED PADA TABEL) -->
                                    <td class="text-center">
                                        <div class="border border-dashed border-slate-400 rounded h-7 w-full mx-auto"></div>
                                    </td>

                                    <!-- CHECKLIST -->
                                    <td class="text-center">
                                        <div class="w-5.5 h-5.5 border-2 border-slate-950 rounded mx-auto"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER BAR: DISCLAIMER FULL-WIDTH -->
            <div class="mt-2 pt-1.5 border-t-2 border-slate-900">
                <div class="text-[9.5px] leading-relaxed text-slate-900 w-full">
                    <span class="font-black text-slate-950 uppercase">⚠️ PERHATIAN (WAJIB UNBOXING):</span>
                    <span class="font-semibold text-slate-800">Harap buka & periksa barang segera setelah diterima. Rekam video unboxing dari awal membuka paket sebagai syarat utama klaim komplain.</span>
                    @if($salesOrder->notes)
                        <div class="mt-1 text-[10px] font-bold text-slate-900">
                            <span>Catatan:</span> {{ $salesOrder->notes }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- GARIS POTONG HANYA DITAMPILKAN JIKA MODE ½ A4 DAN PADA LABEL PERTAMA -->
        @if (!$isMultiPage && $labelIndex == 1)
            <div class="cut-line">
                <span class="cut-line-icon">✂</span>
            </div>
        @endif

    @endfor
</div>

</body>
</html>
