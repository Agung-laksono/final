<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SPK Maklon - {{ $po->po_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 8px;
        }
        .items-table th {
            background-color: #f0f0f0;
            text-align: left;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            float: left;
            width: 30%;
            text-align: center;
            margin-top: 20px;
        }
        .signature-line {
            margin-top: 60px;
            border-bottom: 1px solid #000;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .clear { clear: both; }
        @media print {
            @page {
                size: A4 portrait;
                margin: 1cm;
            }
            body { padding: 0; min-height: auto !important; }
            .no-print { display: none !important; }
            .btn-print { display: none; }
        }
        .btn-print {
            padding: 10px 20px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 20px;
            cursor: pointer;
            border: none;
        }
    </style>
</head>
<body>

    <div style="display: flex; gap: 10px; margin-bottom: 20px;" class="no-print">
        <button onclick="window.close()" class="btn-print" style="background: #dc2626; margin-bottom: 0;">✖️ Tutup Tab</button>
        <button onclick="window.print()" class="btn-print" style="margin-bottom: 0;">🖨️ Cetak Dokumen</button>
    </div>

    <div class="header">
        <h1>PT. AGUNG LAKSONO</h1>
        <p>Jl. Industri No. 123, Kota Manufaktur, Indonesia</p>
        <p>Telp: (021) 1234567 | Email: info@agunglaksono.com</p>
    </div>

    <h2 class="text-center" style="margin-bottom: 20px; text-decoration: underline;">SURAT PERINTAH KERJA (MAKLON)</h2>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>No. SPK</strong></td>
            <td width="35%">: {{ $po->po_number }}</td>
            <td width="15%"><strong>Kepada Yth.</strong></td>
            <td width="35%">: {{ $po->vendor->name }}</td>
        </tr>
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>: {{ \Carbon\Carbon::parse($po->order_date)->format('d F Y') }}</td>
            <td><strong>Kontak</strong></td>
            <td>: {{ $po->vendor->phone ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>: {{ strtoupper($po->status) }}</td>
            <td><strong>Alamat</strong></td>
            <td>: {{ $po->vendor->address ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="45%">Nama Barang / Pekerjaan</th>
                <th width="10%" class="text-center">Qty</th>
                <th width="20%" class="text-right">Biaya/Unit</th>
                <th width="20%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->item->name }}</strong><br>
                        <small style="color: #666;">{!! $item->notes !!}</small>
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-right">TOTAL BIAYA:</th>
                <th class="text-right">Rp {{ number_format($po->total_amount, 0, ',', '.') }}</th>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 20px; border: 1px dashed #000; padding: 10px;">
        <strong>Catatan Khusus:</strong><br>
        {{ $po->notes ?: 'Tidak ada catatan.' }}
    </div>

    <div class="footer">
        <div class="signature-box">
            Dibuat Oleh,<br><br>
            <div class="signature-line"></div>
            ( {{ $po->creator?->name ?? 'Admin' }} )
        </div>
        <div class="signature-box" style="float: right;">
            Diterima Oleh (Vendor),<br><br>
            <div class="signature-line"></div>
            ( ___________________ )
        </div>
        <div class="clear"></div>
    </div>

</body>
</html>
