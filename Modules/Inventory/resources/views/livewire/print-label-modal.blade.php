<?php

use function Livewire\Volt\{state, on};
use Modules\Inventory\Models\ItemLabel;

state([
    'show' => false,
    'labels' => [],
]);

on(['open-print-labels' => function ($labelIds) {
    $this->labels = ItemLabel::with('item')->whereIn('id', $labelIds)->get();
    $this->show = true;
}]);

?>

<div>
    <flux:modal wire:model="show" class="w-full max-w-[800px] space-y-6 !p-0 sm:!p-6">
        <div class="px-6 pt-6 sm:p-0">
            <flux:heading size="lg">🖨️ Cetak Label QR Code</flux:heading>
            <flux:subheading>Pastikan printer label Anda menyala. Tekan tombol Cetak di bawah ini.</flux:subheading>
        </div>

        <div class="px-6 sm:p-0 flex gap-4">
            <flux:button variant="primary" icon="printer" @click="window.open('/inventory/print-labels?ids={{ implode(',', collect($labels)->pluck('id')->toArray()) }}', '_blank')">Cetak Label Sekarang</flux:button>
            <flux:button wire:click="$set('show', false)">Tutup</flux:button>
        </div>

        <div class="px-6 pb-6 sm:p-0">
            <div class="mt-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 bg-zinc-50 dark:bg-zinc-800/50 max-h-[400px] overflow-y-auto">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-4">Pratinjau Label ({{ count($labels) }} stiker)</div>
                
                <div class="print-area flex flex-wrap gap-4 justify-center sm:justify-start">
                    @foreach($labels as $label)
                        <div class="label-card bg-white text-black border border-zinc-300 rounded-md shadow-sm flex flex-row items-end justify-start text-left overflow-hidden" style="width: 50mm; height: 20mm; padding: 0 0 0 2mm;">
                            <!-- Area QR Code (Kiri) -->
                            <div class="shrink-0 flex justify-center items-end h-[55px]" wire:ignore>
                                 <div x-data="{ code: '{{ $label->label_code }}' }" 
                                      x-init="
                                          let attempt = 0;
                                          let renderQR = () => {
                                              if(typeof QRCode !== 'undefined') {
                                                  // Render di ukuran 55x55 pixel agar tajam tapi muat di tinggi 20mm
                                                  new QRCode($el, { text: code, width: 55, height: 55, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
                                              } else if (attempt < 20) {
                                                  attempt++;
                                                  setTimeout(renderQR, 150); // Tunggu library selesai di-download
                                              }
                                          };
                                          $nextTick(renderQR);
                                      "
                                      class="flex justify-center items-end h-full">
                                 </div>
                            </div>
                            
                            <!-- Area Teks (Kanan) -->
                            <div class="flex flex-col justify-between flex-1 w-full h-[55px] pl-[2mm] pr-[1mm]">
                                <div class="text-[8pt] font-bold leading-[1.1] text-zinc-900 line-clamp-2" style="margin:0;" title="{{ $label->item->name }}">{{ strtoupper($label->item->name) }}</div>
                                <div class="text-[6pt] text-zinc-800 mt-[0.5mm] font-mono" style="margin:0.5mm 0 0 0;">code: {{ $label->label_code }}</div>
                                <div class="text-[5pt] text-zinc-600 font-mono tracking-wider" style="margin:0;">{{ \Carbon\Carbon::parse($label->created_at)->format('m-Y') }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </flux:modal>
    <!-- CSS khusus dihapus karena pencetakan kini sepenuhnya dilayani oleh halaman /inventory/print-labels yang steril -->
</div>
