{{-- SN Selection Modal --}}
<flux:modal name="sn-modal" class="md:max-w-xl max-md:w-[95vw]" @open-sn-modal.window="$el.showModal()">
    <div class="flex flex-col gap-4">
        <div>
            <flux:heading size="lg">Pilih Serial Number</flux:heading>
            <flux:subheading>Centang Serial Number fisik yang berhasil ditemukan.</flux:subheading>
        </div>
        
        <div class="max-h-[50vh] overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg p-2 divide-y divide-zinc-100 dark:divide-zinc-800">
            @if($activeSnItemId)
                @forelse($availableLabels as $sn)
                    <div wire:click="toggleSn('{{ $sn }}')" wire:key="sn-{{ $sn }}" wire:loading.class="opacity-50 pointer-events-none" wire:target="toggleSn('{{ $sn }}')" class="flex items-center justify-between p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer rounded transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-5 h-5 rounded border {{ in_array($sn, $scanned_labels[$activeSnItemId] ?? []) ? 'bg-blue-600 border-blue-600' : 'bg-white border-zinc-300 dark:bg-zinc-900 dark:border-zinc-700' }}">
                                @if(in_array($sn, $scanned_labels[$activeSnItemId] ?? []))
                                    <flux:icon.check class="w-3.5 h-3.5 text-white" />
                                @endif
                            </div>
                            <span class="font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ $sn }}</span>
                        </div>
                        
                        <div wire:loading wire:target="toggleSn('{{ $sn }}')" class="flex shrink-0">
                            <svg class="animate-spin h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-zinc-500 text-sm">Tidak ada Serial Number tercatat untuk barang ini di gudang terpilih.</div>
                @endforelse
            @endif
        </div>

        <div class="flex justify-between items-center pt-4">
            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Total Dipilih: <span class="text-blue-600 dark:text-blue-400 font-bold">{{ count($scanned_labels[$activeSnItemId] ?? []) }}</span>
            </div>
            <flux:modal.close>
                <flux:button variant="primary">Selesai</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
