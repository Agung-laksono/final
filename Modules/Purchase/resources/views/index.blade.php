<x-layouts::app>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Dashboard Pembelian</flux:heading>
            <flux:button variant="primary" icon="plus">Buat PO Baru</flux:button>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                        <flux:icon.document-text class="w-5 h-5" />
                    </div>
                    <h3 class="font-medium text-zinc-500 dark:text-zinc-400 text-xs uppercase">Total PO (Bulan Ini)</h3>
                </div>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">24</p>
            </div>
            
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="rounded-md bg-orange-50 dark:bg-orange-500/10 p-2 text-orange-600 dark:text-orange-400">
                        <flux:icon.clock class="w-5 h-5" />
                    </div>
                    <h3 class="font-medium text-zinc-500 dark:text-zinc-400 text-xs uppercase">PO Menunggu Persetujuan</h3>
                </div>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">5</p>
            </div>

            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg p-5">
                <div class="flex items-center gap-3 mb-2">
                    <div class="rounded-md bg-emerald-50 dark:bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                        <flux:icon.banknotes class="w-5 h-5" />
                    </div>
                    <h3 class="font-medium text-zinc-500 dark:text-zinc-400 text-xs uppercase">Total Pembelanjaan</h3>
                </div>
                <p class="text-2xl font-bold text-zinc-900 dark:text-white">Rp 45.200.000</p>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <h3 class="font-bold text-zinc-800 dark:text-zinc-200">Daftar Purchase Order Terbaru</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr class="text-zinc-500 dark:text-zinc-400 text-xs uppercase tracking-wider">
                            <th class="px-4 py-3 font-semibold">Nomor PO</th>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Supplier</th>
                            <th class="px-4 py-3 font-semibold">Total</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-sm">
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-indigo-600 dark:text-indigo-400">PO-202610-001</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">10 Okt 2026</td>
                            <td class="px-4 py-3 text-zinc-900 dark:text-white">PT. Indofood Sukses Makmur</td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">Rp 12.500.000</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-[10px] font-bold rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400 uppercase tracking-wider">Approved</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" icon="eye" class="w-8 h-8 !p-0"></flux:button>
                            </td>
                        </tr>
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-indigo-600 dark:text-indigo-400">PO-202610-002</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">10 Okt 2026</td>
                            <td class="px-4 py-3 text-zinc-900 dark:text-white">CV. Sumber Rejeki</td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">Rp 4.200.000</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-[10px] font-bold rounded bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400 uppercase tracking-wider">Pending</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" icon="eye" class="w-8 h-8 !p-0"></flux:button>
                            </td>
                        </tr>
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-indigo-600 dark:text-indigo-400">PO-202610-003</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">09 Okt 2026</td>
                            <td class="px-4 py-3 text-zinc-900 dark:text-white">PT. Mayora Indah</td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">Rp 28.500.000</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-[10px] font-bold rounded bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 uppercase tracking-wider">Draft</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" variant="ghost" icon="eye" class="w-8 h-8 !p-0"></flux:button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
