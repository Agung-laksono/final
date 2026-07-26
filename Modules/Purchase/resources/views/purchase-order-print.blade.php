<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ strtolower($purchaseOrder->po_number) }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6; /* gray-100 background for screen */
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
                margin: 0;
            }
            .page-container {
                width: 100%;
                margin: 0 !important;
                padding: 1cm;
                box-shadow: none !important;
                min-height: auto !important;
                background-color: transparent !important;
            }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
        .page-container {
            width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto 2cm auto; /* Adds a gap between pages on screen */
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); /* Adds depth */
            position: relative;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .table-custom th {
            color: #666;
            font-weight: 600;
            padding: 8px 4px;
            border-top: 2px solid #eaeaea;
            border-bottom: 1px solid #eaeaea;
            text-align: left;
        }
        .table-custom th.text-center { text-align: center; }
        .table-custom th.text-right { text-align: right; }
        .table-custom td {
            padding: 12px 4px;
            border-bottom: 1px solid #eaeaea;
            vertical-align: top;
        }
        
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 4px;
            text-align: right;
            border-bottom: 1px solid #f0f0f0;
        }
        .totals-table tr.bg-gray td {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        .totals-table td:first-child {
            color: #555;
            font-weight: 600;
            width: 80%;
        }
        .totals-table td:last-child {
            font-weight: 700;
            color: #333;
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
    <button onclick="window.print()" class="px-4 py-2 bg-zinc-900 text-white rounded-md text-sm font-medium hover:bg-zinc-800 flex items-center gap-2 shadow-sm transition-all">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        Cetak Purchase Order
    </button>
</div>

<!-- PAGE 1: Purchase Order MAIN -->
<div class="page-container px-8 py-8 relative flex flex-col">
    <!-- Header Section -->
    <div class="flex justify-between items-start mb-8">
        <div>
            @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->logo)
                <img src="{{ Storage::url($purchaseOrder->creator->brand->logo) }}" alt="Logo" class="h-20 w-auto object-contain">
            @else
                <h1 class="text-3xl font-bold text-amber-700 font-serif tracking-tighter">
                    {{ $purchaseOrder->creator->brand ? substr($purchaseOrder->creator->brand->name, 0, 2) : 'SO' }}
                </h1>
                <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">PRODUSEN KAMI</div>
            @endif
        </div>
        <div class="text-right">
            <div class="text-2xl mb-1"><span class="text-zinc-900 font-medium">Purchase Order</span> <span class="font-black">{{ $purchaseOrder->po_number }}</span></div>
            <div class="text-sm text-zinc-900">Tgl: <span class="font-bold">{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d-m-Y') }}</span></div>
            @if($purchaseOrder->deadline)
                <div class="text-sm text-red-600 mt-0.5">Tenggat: <span class="font-bold">{{ \Carbon\Carbon::parse($purchaseOrder->deadline)->format('d-m-Y') }}</span></div>
            @endif
        </div>
    </div>

    <!-- Pihak Pertama / Pihak Kedua Section -->
    <div class="flex gap-8 mb-8 border-t-2 border-black pt-4">
        <!-- Pihak Pertama (Pengirim) -->
        <div class="w-1/2 text-[13px] leading-relaxed">
            <div class="font-semibold text-zinc-500 mb-1">DARI :</div>
            @php 
                $senderPhone = null;
                if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->phone) { $senderPhone = $purchaseOrder->creator->brand->phone; }
                elseif($purchaseOrder->creator->phone) { $senderPhone = $purchaseOrder->creator->phone; }
            @endphp
            <div class="font-bold text-base uppercase text-black">
                {{ $purchaseOrder->creator->brand ? $purchaseOrder->creator->brand->name : 'PERUSAHAAN' }}
                @if($senderPhone) <span class="font-normal text-[13px] normal-case text-zinc-600 ml-1">({{ $senderPhone }})</span> @endif
            </div>
            @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->address)
                <div class="whitespace-pre-line">{{ $purchaseOrder->creator->brand->address }}</div>
            @endif
            
            <div class="mt-1.5 text-zinc-700">
                @php $contacts = []; @endphp
                @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->email) @php $contacts[] = $purchaseOrder->creator->brand->email; @endphp @endif
                @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->website) @php $contacts[] = $purchaseOrder->creator->brand->website; @endphp @endif
                
                @if(!empty($contacts))
                    <div>{{ implode(' • ', $contacts) }}</div>
                @endif
                
                @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->npwp)
                    <div>NPWP: {{ $purchaseOrder->creator->brand->npwp }}</div>
                @endif
            </div>
        </div>

        <!-- Pihak Kedua (Penerima) -->
        <div class="w-1/2 text-[13px] leading-relaxed">
            <div class="font-semibold text-zinc-500 mb-1">KEPADA :</div>
            <div class="font-bold text-base uppercase text-black">
                {{ $purchaseOrder->vendor->name ?? $purchaseOrder->vendor_name ?? 'Pelanggan Umum' }}
                @if($purchaseOrder->vendor && $purchaseOrder->vendor->phone) 
                    <span class="font-normal text-[13px] normal-case text-zinc-600 ml-1">({{ $purchaseOrder->vendor->phone }})</span> 
                @elseif($purchaseOrder->vendor_phone)
                    <span class="font-normal text-[13px] normal-case text-zinc-600 ml-1">({{ $purchaseOrder->vendor_phone }})</span>
                @endif
            </div>
            @php
                $vendorRegion = $purchaseOrder->vendor ? collect([
                    $purchaseOrder->vendor->province,
                    $purchaseOrder->vendor->city,
                    $purchaseOrder->vendor->district,
                    $purchaseOrder->vendor->village
                ])->filter()->implode(', ') : '';
                $vendorDetail = $purchaseOrder->shipping_address ?? ($purchaseOrder->vendor ? $purchaseOrder->vendor->address : '-');
            @endphp
            @if($vendorRegion)
                <div>{{ $vendorRegion }}</div>
            @endif
            <div class="whitespace-pre-line">{{ $vendorDetail }}</div>
        </div>
    </div>

    <!-- Table Barang -->
    <div class="flex-grow">
        <table class="table-custom">
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 50%">Nama Barang</th>
                <th class="text-center" style="width: 15%">Jumlah</th>
                <th class="text-right" style="width: 15%">Harga/unit</th>
                <th class="text-right" style="width: 15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->items as $index => $item)
            <tr>
                <td class="text-zinc-500">{{ $index + 1 }}</td>
                <td>
                    <div class="flex gap-3">
                        <div class="w-12 h-12 bg-zinc-200 border border-zinc-300 rounded overflow-hidden shrink-0">
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
                        <div class="text-zinc-600 font-medium uppercase text-xs pt-1">
                            {{ $item->item->name }}
                            @if($item->item->code)
                                <br><span class="font-mono text-[10px] text-zinc-400 normal-case">{{ $item->item->code }}</span>
                            @endif
                            @if($item->notes)
                                <br><span class="text-[10px] text-zinc-400 normal-case">{!! $item->notes !!}</span>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="text-center text-zinc-600">{{ $item->qty }} {{ $item->item->unit->name ?? 'Set' }}</td>
                <td class="text-right text-zinc-600">{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="text-right text-zinc-600">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="flex justify-between mt-2">
        <div class="w-1/2 pr-8">
            @if($purchaseOrder->notes)
                <div class="text-xs text-zinc-700 bg-zinc-50 border border-zinc-200 p-3 rounded mt-2">
                    <div class="font-bold mb-1 text-zinc-900">Catatan Khusus:</div>
                    <div class="whitespace-pre-wrap">{{ $purchaseOrder->notes }}</div>
                </div>
            @endif
        </div>
        <div class="w-1/2">
            @php
                $subtotal = collect($purchaseOrder->items)->sum('subtotal');
            @endphp
            <table class="totals-table text-sm">
                <tr>
                    <td>Subtotal</td>
                    <td>{{ number_format($subtotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Biaya pengiriman</td>
                    <td>{{ number_format($purchaseOrder->shipping_fee + $purchaseOrder->packing_fee, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Diskon</td>
                    <td>{{ number_format($purchaseOrder->discount, 0, ',', '.') }}</td>
                </tr>
                <tr class="bg-gray font-bold text-lg">
                    <td>Total</td>
                    <td>{{ number_format($purchaseOrder->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>
    </div> <!-- Close flex-grow wrapper -->
</div>

<!-- PAGE 2, 3..: DETAILS -->
@foreach($purchaseOrder->items as $index => $item)
<div style="page-break-before: always;"></div>
<div class="page-container px-8 py-8 relative">
    <div class="watermark">
        {{ strtoupper($purchaseOrder->creator->brand ? $purchaseOrder->creator->brand->name : 'PERUSAHAAN') }}
    </div>

    <!-- Header -->
    <div class="flex justify-between items-start mb-8">
        <div>
            @if($purchaseOrder->creator->brand && $purchaseOrder->creator->brand->logo)
                <img src="{{ Storage::url($purchaseOrder->creator->brand->logo) }}" alt="Logo" class="h-16 w-auto object-contain">
            @else
                <h1 class="text-3xl font-bold text-amber-700 font-serif tracking-tighter">
                    {{ $purchaseOrder->creator->brand ? substr($purchaseOrder->creator->brand->name, 0, 2) : 'SO' }}
                </h1>
                <div class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">PRODUSEN KAMI</div>
            @endif
        </div>
        <div class="text-right">
            <div class="text-2xl font-black mb-1">Detail No {{ $index + 1 }}</div>
            <div class="text-sm text-zinc-600">Code: <span class="font-bold text-zinc-900">{{ $purchaseOrder->po_number }}</span></div>
        </div>
    </div>

    <!-- Title -->
    <h2 class="text-3xl font-black text-black uppercase leading-tight mb-8">
        {{ $item->item->name }}
    </h2>

    <!-- Image -->
    <div class="w-full flex justify-center mb-12">
        <div class="w-[500px] h-[500px] border border-zinc-200 bg-zinc-100 rounded flex items-center justify-center overflow-hidden shadow-sm relative">
            @if(!empty($item->custom_attachments))
                <img src="{{ Storage::url($item->custom_attachments[0]) }}" class="w-full h-full object-cover">
            @elseif(isset($item->item->image) && $item->item->image)
                <img src="{{ Storage::url($item->item->image) }}" class="w-full h-full object-cover">
            @else
                <div class="text-center text-zinc-400 z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <p class="text-sm">Tidak ada gambar detail</p>
                </div>
            @endif
            <!-- Image Overlay Text (simulating the PDF) -->
            <div class="absolute z-20 text-center text-white drop-shadow-md text-sm font-semibold" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">
                L. ?? T. ??<br>
                Pintu ??<br>
                Rp. {{ number_format($item->unit_price, 0, ',', '.') }},-<br><br><br>
                Free Ongkir Pulau<br>
                Jawa, Bali dan Madura.<br>
                COD/Bayar ditempat
            </div>
        </div>
    </div>

    <!-- Small Detail Table -->
    <table class="w-full border border-black border-collapse text-lg mt-8">
        @if(!empty($item->custom_attributes))
        <tr>
            <td class="border border-black p-3 w-48 font-medium text-amber-700">Spesifikasi Custom</td>
            <td class="border border-black p-3 text-base">
                <ul class="list-none space-y-1">
                @foreach($item->custom_attributes as $attr)
                    <li><span class="font-medium text-zinc-600">{{ $attr['key'] ?? '-' }} :</span> <span class="font-bold">{{ $attr['value'] ?? '-' }}</span></li>
                @endforeach
                </ul>
            </td>
            <td class="border border-black p-3 w-16"></td>
        </tr>
        @elseif($item->item->description)
        <tr>
            <td class="border border-black p-3 w-48 font-medium">Spesifikasi</td>
            <td class="border border-black p-3 whitespace-pre-wrap text-base">{{ $item->item->description }}</td>
            <td class="border border-black p-3 w-16"></td>
        </tr>
        @endif
        @if($item->notes)
        <tr>
            <td class="border border-black p-3 w-48 font-medium text-amber-700">Catatan Custom</td>
            <td class="border border-black p-3 whitespace-pre-wrap text-amber-800 text-base font-semibold prose prose-sm prose-p:my-0">{!! $item->notes !!}</td>
            <td class="border border-black p-3 w-16"></td>
        </tr>
        @endif
        <tr>
            <td class="border border-black p-3 w-48 font-medium">Jumlah</td>
            <td class="border border-black p-3">{{ $item->qty }} {{ $item->item->unit->name ?? 'Set' }}</td>
            <td class="border border-black p-3 w-16"></td>
        </tr>
        <tr>
            <td class="border border-black p-3 font-medium">Harga/Unit</td>
            <td class="border border-black p-3">Rp. {{ number_format($item->unit_price, 0, ',', '.') }}</td>
            <td class="border border-black p-3"></td>
        </tr>
    </table>
</div>
@endforeach

</body>
</html>

