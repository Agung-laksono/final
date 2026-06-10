    {{-- History Modal --}}
    <flux:modal name="history-modal" class="md:max-w-4xl max-md:w-[95vw]" @open-history-modal.window="$el.showModal()">
        <div class="flex flex-col gap-4">
            <div class="flex justify-between items-center">
                <div>
                    <flux:heading size="lg">Riwayat Gudang</flux:heading>
                    <flux:subheading>Daftar riwayat pergerakan stok dan dokumen opname (50 terakhir).</flux:subheading>
                </div>
            </div>

            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
                <button wire:click="setTab('opname')" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'opname' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    Dokumen Opname
                </button>
                <button wire:click="setTab('movement')" class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'movement' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
                    Kartu Stok (Pergerakan)
                </button>
            </div>

            <div class="overflow-y-auto max-h-[60vh] -mx-4 px-4">
                @if($activeTab === 'opname')
                    <div>
                        <flux:table class="block md:table">
                            <flux:table.columns class="hidden md:table-header-group">
                                <flux:table.column>Tanggal</flux:table.column>
                                <flux:table.column>No. Dokumen</flux:table.column>
                                <flux:table.column>Gudang</flux:table.column>
                                <flux:table.column class="text-center">Jml. Barang</flux:table.column>
                                <flux:table.column class="text-center">Total Selisih</flux:table.column>
                                <flux:table.column>Petugas</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows class="block md:table-row-group space-y-4 md:space-y-0">
                                @forelse($historyAdjustments as $doc)
                                    <flux:table.row class="block md:table-row bg-white dark:bg-zinc-900 md:bg-transparent rounded-xl md:rounded-none shadow-sm md:shadow-none border border-zinc-200 dark:border-zinc-800 md:border-none p-4 md:p-0 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-2 md:pb-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Tanggal</span>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ \Carbon\Carbon::parse($doc['adjustment_date'])->format('d M Y') }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">No. Dokumen</span>
                                                <span class="font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $doc['reference_number'] }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Gudang</span>
                                                <span>{{ $doc['warehouse'] }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:justify-center items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Jml. Barang</span>
                                                <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-semibold px-2 py-0.5 rounded-full">
                                                    {{ $doc['total_items'] }} barang
                                                </span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:justify-center items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Total Selisih</span>
                                                <span class="font-bold {{ $doc['total_selisih'] > 0 ? 'text-emerald-500' : ($doc['total_selisih'] < 0 ? 'text-rose-500' : 'text-zinc-400') }}">
                                                    {{ $doc['total_selisih'] > 0 ? '+' : '' }}{{ $doc['total_selisih'] }}
                                                </span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-3 md:pb-3 mb-2 md:mb-0">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Petugas</span>
                                                <span class="text-zinc-500">{{ $doc['petugas'] }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-0 md:py-3 pt-3 md:pt-3">
                                            <flux:button wire:click="loadDocumentDetail('{{ $doc['reference_number'] }}')" size="xs" variant="outline" icon="eye" class="w-full md:w-auto">Detail Dokumen</flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">Belum ada riwayat opname.</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @else
                    <div>
                        <flux:table class="block md:table">
                            <flux:table.columns class="hidden md:table-header-group">
                                <flux:table.column>Waktu</flux:table.column>
                                <flux:table.column>No. Dokumen</flux:table.column>
                                <flux:table.column>Tipe</flux:table.column>
                                <flux:table.column>Barang</flux:table.column>
                                <flux:table.column class="text-center">S. Awal</flux:table.column>
                                <flux:table.column class="text-center">Jumlah</flux:table.column>
                                <flux:table.column class="text-center">S. Akhir</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows class="block md:table-row-group space-y-4 md:space-y-0">
                                @forelse($historyMovements as $mov)
                                    <flux:table.row class="block md:table-row bg-white dark:bg-zinc-900 md:bg-transparent rounded-xl md:rounded-none shadow-sm md:shadow-none border border-zinc-200 dark:border-zinc-800 md:border-none p-4 md:p-0 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-2 md:pb-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Waktu</span>
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $mov->created_at->format('d M Y H:i') }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">No. Dokumen</span>
                                                <span class="font-mono text-xs text-zinc-500">{{ $mov->reference_number ?? '-' }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Tipe</span>
                                                <span class="uppercase text-[11px] font-bold text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">{{ $mov->type }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-3 md:pb-3 mb-2 md:mb-0">
                                            <div class="flex w-full justify-between md:block items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Barang</span>
                                                <span>{{ $mov->item?->name ?? 'Barang Dihapus' }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:justify-center items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">S. Awal</span>
                                                <span class="text-zinc-500">{{ $mov->stock_before }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:justify-center items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Jumlah</span>
                                                <span class="font-bold {{ $mov->quantity > 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                                    {{ $mov->quantity > 0 ? '+' : '' }}{{ $mov->quantity }}
                                                </span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                            <div class="flex w-full justify-between md:justify-center items-center">
                                                <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">S. Akhir</span>
                                                <span class="font-bold">{{ $mov->stock_after }}</span>
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @empty
                                    <flux:table.row>
                                        <flux:table.cell colspan="7" class="text-center text-zinc-500 py-8">Belum ada riwayat pergerakan stok.</flux:table.cell>
                                    </flux:table.row>
                                @endforelse
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Document Detail Popup Modal --}}
    <flux:modal name="document-detail-modal" class="md:max-w-3xl max-md:w-[95vw]" @open-document-detail-modal.window="$el.showModal()">
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">Detail Dokumen Opname</flux:heading>
                <div class="mt-1 inline-flex items-center gap-2 font-mono text-xs bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-2.5 py-1 rounded-md border border-blue-200 dark:border-blue-700">
                    <flux:icon.document-text class="w-3.5 h-3.5" />
                    {{ $selectedDocument }}
                </div>
            </div>

            <div class="overflow-y-auto max-h-[60vh] -mx-4 px-4">
                <div>
                    <flux:table class="block md:table">
                        <flux:table.columns class="hidden md:table-header-group">
                            <flux:table.column>Barang</flux:table.column>
                            <flux:table.column class="text-center">Stok Sistem</flux:table.column>
                            <flux:table.column class="text-center">Stok Aktual</flux:table.column>
                            <flux:table.column class="text-center">Selisih</flux:table.column>
                            <flux:table.column>Alasan</flux:table.column>
                            <flux:table.column>Catatan</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows class="block md:table-row-group space-y-4 md:space-y-0">
                            @foreach($documentDetail as $row)
                                <flux:table.row class="block md:table-row bg-white dark:bg-zinc-900 md:bg-transparent rounded-xl md:rounded-none shadow-sm md:shadow-none border border-zinc-200 dark:border-zinc-800 md:border-none p-4 md:p-0 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-2 md:pb-3">
                                        <div class="flex w-full justify-between md:block items-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Barang</span>
                                            <div class="text-right md:text-left">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['item']['name'] ?? 'Barang Dihapus' }}</div>
                                                <div class="text-[10px] font-mono text-zinc-400">{{ $row['item']['code'] ?? '' }}</div>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full justify-between md:justify-center items-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Stok Sistem</span>
                                            <span class="text-zinc-500">{{ $row['system_stock'] }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full justify-between md:justify-center items-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Stok Aktual</span>
                                            <span class="font-semibold">{{ $row['actual_stock'] }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3 border-b border-dashed border-zinc-200 dark:border-zinc-700 md:border-b-0 pb-3 md:pb-3 mb-2 md:mb-0">
                                        <div class="flex w-full justify-between md:justify-center items-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium">Selisih</span>
                                            <span class="font-bold text-sm {{ $row['difference'] > 0 ? 'text-emerald-500' : ($row['difference'] < 0 ? 'text-rose-500' : 'text-zinc-400') }}">
                                                {{ $row['difference'] > 0 ? '+' : '' }}{{ $row['difference'] }}
                                            </span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full justify-between md:block items-center">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium shrink-0">Alasan</span>
                                            <span class="text-right md:text-left">{{ $row['reason'] }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="block md:table-cell py-1.5 md:py-3">
                                        <div class="flex w-full justify-between md:block items-start">
                                            <span class="md:hidden text-[11px] text-zinc-500 uppercase font-medium shrink-0 mt-0.5">Catatan</span>
                                            <span class="text-xs text-zinc-500 text-right md:text-left max-w-[200px] md:max-w-none line-clamp-2 md:line-clamp-none">{{ $row['notes'] ?: '-' }}</span>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

            <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:modal.close>
                    <flux:button variant="ghost">Tutup</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
