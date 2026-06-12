<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Modules\Inventory\Models\ItemLabel;

new #[Layout('layouts.empty')] class extends Component {
    public $labels = [];
    public $copies = [];

    public function mount() {
        // copies array input from URL: ?copies[1]=5&copies[2]=1
        $copiesInput = request()->query('copies', []);
        
        if (is_array($copiesInput) && !empty($copiesInput)) {
            $idArray = array_keys($copiesInput);
            $this->copies = array_map('intval', $copiesInput);
            $this->labels = ItemLabel::with('item')->whereIn('id', $idArray)->get();
        }
    }
};
?>

<div>
    <style>
        /* Ukuran mutlak kertas label printer thermal 50x20mm */
        @page { 
            size: 50mm 20mm; 
            margin: 0; 
        }
        
        body { 
            margin: 0; 
            padding: 0; 
            background: white; 
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; 
        }
        
        .label-card { 
            width: 50mm; 
            height: 20mm; 
            box-sizing: border-box; 
            padding: 0 0 0 2mm; /* Geser semua ke kanan sejauh 2mm */
            page-break-after: always; 
            display: flex; 
            flex-direction: row; 
            align-items: flex-end; /* Menempel ke bawah */
            justify-content: flex-start; 
            overflow: hidden; 
        }
        
        .qr-container { 
            flex-shrink: 0; 
            display: flex; 
            justify-content: center; 
            align-items: flex-end; 
            height: 55px; /* Sesuaikan dengan tinggi QR code */
        }
        
        .text-container { 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; /* Menyebarkan teks agar pas dengan tinggi QR */
            flex: 1; 
            width: 100%; 
            min-width: 0; /* Penting untuk line-clamp/ellipsis di dalam flex */
            height: 55px; /* Samakan dengan tinggi QR code agar top/bottom sejajar */
            padding-left: 2mm; 
            padding-right: 1mm;
        }
        
        .print-name { 
            font-size: 8pt; 
            font-weight: bold; 
            line-height: 1.1; 
            margin: 0; 
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis; 
            color: black; 
        }
        
        .print-code { 
            font-size: 6pt; 
            margin: 0.5mm 0 0 0; 
            font-family: monospace; 
            color: #222; 
        }
        
        .print-date { 
            font-size: 5pt; 
            margin: 0; 
            font-family: monospace; 
            letter-spacing: 0.5px; 
            color: #444; 
        }
        
        /* Menyembunyikan segalanya saat layar biasa, hanya tampil di layar cetak (opsional) */
        @media screen {
            body { background: #f4f4f5; display: flex; flex-direction: column; align-items: center; padding-top: 2rem; }
            .label-card { background: white; margin-bottom: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        }
    </style>

    @if(count($labels) == 0)
        <div style="padding: 20px; text-align: center; font-family: sans-serif;">
            Tidak ada label yang dipilih untuk dicetak. Tutup jendela ini.
        </div>
    @endif

    @foreach($labels as $label)
        @for($i = 0; $i < ($copies[$label->id] ?? 1); $i++)
            <div class="label-card">
                <!-- Area QR Code (Kiri) -->
                <div class="qr-container">
                     <div x-data="{ code: '{{ $label->label_code }}' }" 
                          x-init="
                              let attempt = 0;
                              let renderQR = () => {
                                  if(typeof QRCode !== 'undefined') {
                                      // Ukuran 55px disesuaikan dengan tinggi 20mm
                                      new QRCode($el, { text: code, width: 55, height: 55, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
                                  } else if (attempt < 20) {
                                      attempt++;
                                      setTimeout(renderQR, 150);
                                  }
                              };
                              $nextTick(renderQR);
                          ">
                     </div>
                </div>
                
                <!-- Area Teks (Kanan) -->
                <div class="text-container">
                    <div class="print-name" title="{{ $label->item->name }}">{{ strtoupper($label->item->name) }}</div>
                    <div class="print-code">code: {{ $label->label_code }}</div>
                    <div class="print-date">{{ \Carbon\Carbon::parse($label->created_at)->format('m-Y') }}</div>
                </div>
            </div>
        @endfor
    @endforeach

    <script>
        // Tunggu QR code selesai dirender menggunakan Alpine x-init sebelum memanggil print
        window.onload = function() {
            if({{ count($labels) }} > 0) {
                setTimeout(function() {
                    window.print();
                    // Setelah dialog print ditutup, beri sedikit jeda lalu tutup otomatis tab ini
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }, 800); // Beri waktu 800ms agar QR script diunduh & dirender
            }
        }
    </script>
</div>
