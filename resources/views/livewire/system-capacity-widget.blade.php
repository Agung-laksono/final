<div>
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 h-full flex flex-col justify-center">
        <div class="flex items-center gap-2 mb-4">
            <div class="bg-blue-100 dark:bg-blue-900/30 p-2 rounded-full shrink-0">
                <flux:icon.server class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <h3 class="text-lg font-bold text-blue-800 dark:text-blue-300">Kapasitas Penyimpanan</h3>
        </div>
        
        <p class="text-sm text-zinc-500 mb-6">
            Rincian penggunaan kapasitas data aplikasi Anda.
        </p>

        <div class="space-y-4">
            <!-- Total Size -->
            <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg border border-zinc-100 dark:border-zinc-700">
                <div class="flex items-center gap-2">
                    <flux:icon.chart-pie class="w-5 h-5 text-indigo-500" />
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">Total Keseluruhan</span>
                </div>
                <span class="font-bold text-lg text-indigo-600 dark:text-indigo-400">{{ $this->formatBytes($totalSize) }}</span>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <!-- Database Size -->
                <div class="flex justify-between items-center p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <flux:icon.circle-stack class="w-4 h-4 text-emerald-500" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">Database SQLite</span>
                    </div>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->formatBytes($databaseSize) }}</span>
                </div>

                <!-- Storage Size -->
                <div class="flex justify-between items-center p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <flux:icon.photo class="w-4 h-4 text-amber-500" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">File Unggahan (Storage)</span>
                    </div>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->formatBytes($storageSize) }}</span>
                </div>

                <!-- Backup Size -->
                <div class="flex justify-between items-center p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <flux:icon.archive-box class="w-4 h-4 text-purple-500" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">File Backup Sistem</span>
                    </div>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->formatBytes($backupSize) }}</span>
                </div>

                <!-- App Size -->
                <div class="flex justify-between items-center p-2 rounded hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                    <div class="flex items-center gap-2">
                        <flux:icon.folder class="w-4 h-4 text-blue-500" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">File Sistem Aplikasi</span>
                    </div>
                    <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->formatBytes($appSize) }}</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-700 text-center">
                <flux:button wire:click="calculateSizes" wire:loading.attr="disabled" size="sm" variant="subtle" icon="arrow-path">
                    <span wire:loading.remove wire:target="calculateSizes">Hitung Ulang</span>
                    <span wire:loading wire:target="calculateSizes">Menghitung...</span>
                </flux:button>
            </div>
        </div>
    </div>
</div>
