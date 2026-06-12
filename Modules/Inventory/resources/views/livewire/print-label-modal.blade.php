<?php

use function Livewire\Volt\{state, on};
use Modules\Inventory\Models\ItemLabel;

state([
    'show' => false,
    'labels' => [],
    'copies' => [],
]);

on(['open-print-labels' => function ($labelIds) {
    $this->labels = ItemLabel::with('item')->whereIn('id', $labelIds)->get();
    
    // Initialize copies to 1 for all selected labels
    $copies = [];
    foreach ($labelIds as $id) {
        $copies[$id] = 1;
    }
    $this->copies = $copies;
    
    $this->show = true;
}]);

?>

<div>
    <flux:modal wire:model="show" class="w-full max-w-[800px] space-y-6 !p-0 sm:!p-6">
        <div class="px-6 pt-6 sm:p-0">
            <flux:heading size="lg">🖨️ Cetak Label QR Code</flux:heading>
            <flux:subheading>Atur jumlah stiker untuk masing-masing barang.</flux:subheading>
            
            <div class="mt-4 border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden max-h-[300px] overflow-y-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-100 dark:bg-zinc-800 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-2 font-medium text-zinc-700 dark:text-zinc-300">Barang</th>
                            <th class="px-4 py-2 font-medium text-zinc-700 dark:text-zinc-300">Serial</th>
                            <th class="px-4 py-2 font-medium text-zinc-700 dark:text-zinc-300 w-32">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($labels as $label)
                            <tr>
                                <td class="px-4 py-3 text-zinc-900 dark:text-white truncate max-w-[200px]" title="{{ $label->item->name }}">{{ $label->item->name }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-500">{{ $label->label_code }}</td>
                                <td class="px-4 py-2">
                                    <flux:input type="number" min="1" max="100" wire:model.live="copies.{{ $label->id }}" class="!w-24" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="px-6 sm:p-0 flex gap-4">
            <flux:button variant="primary" icon="printer" 
                x-data
                @click="
                    let query = Object.entries($wire.copies).map(([id, qty]) => `copies[${id}]=${qty}`).join('&');
                    window.open('/inventory/print-labels?' + query, '_blank');
                ">
                Cetak Label Sekarang
            </flux:button>
            <flux:button wire:click="$set('show', false)">Tutup</flux:button>
        </div>

        <div class="px-6 pb-6 sm:p-0">
            <div class="mt-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 bg-zinc-50 dark:bg-zinc-800/50 max-h-[400px] overflow-y-auto">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-4">Pratinjau Label ({{ collect($copies)->sum() }} stiker)</div>
                
                <div class="print-area flex flex-wrap gap-4 justify-center sm:justify-start">
                    @foreach($labels as $label)
                        @for($i = 0; $i < ($copies[$label->id] ?? 1); $i++)
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
                        @endfor
                    @endforeach
                </div>
            </div>
        </div>
    </flux:modal>
    <!-- CSS khusus dihapus karena pencetakan kini sepenuhnya dilayani oleh halaman /inventory/print-labels yang steril -->
</div>
