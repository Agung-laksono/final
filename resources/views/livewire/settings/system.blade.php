<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Modules\Inventory\Models\ItemWarehouse;
use Modules\Finance\Models\FinanceAccount;
use Carbon\Carbon;

new class extends Component {
    use WithFileUploads;

    public $backups = [];
    public $confirmPassword = '';
    public $uploadedBackupFile;
    
    // Checklists
    public $wipeFinance = false;
    public $wipeSales = false;
    public $wipePurchase = false;
    public $wipeInventory = false;
    public $wipeProduction = false;
    public $wipeMaster = false;
    public $wipeImages = false;
    public $wipeUsers = false;

    // Auto Backup Settings
    public $autoBackupEnabled = false;
    public $autoBackupSchedule = 'daily';
    public $autoBackupTime = '23:59';
    public $autoBackupRetention = 30;

    public function mount() {
        $this->loadBackups();
        
        $this->autoBackupEnabled = \App\Models\Setting::where('key', 'backup_auto_enabled')->value('value') === 'true';
        $this->autoBackupSchedule = \App\Models\Setting::where('key', 'backup_auto_schedule')->value('value') ?? 'daily';
        $this->autoBackupTime = \App\Models\Setting::where('key', 'backup_auto_time')->value('value') ?? '23:59';
        $this->autoBackupRetention = \App\Models\Setting::where('key', 'backup_auto_retention')->value('value') ?? 30;
    }

    public function saveAutoBackupSettings() {
        $this->validate([
            'autoBackupSchedule' => 'required|in:daily,weekly,monthly',
            'autoBackupTime' => 'required|date_format:H:i',
            'autoBackupRetention' => 'required|integer|min:1|max:365',
        ]);

        \App\Models\Setting::updateOrCreate(['key' => 'backup_auto_enabled'], ['value' => $this->autoBackupEnabled ? 'true' : 'false']);
        \App\Models\Setting::updateOrCreate(['key' => 'backup_auto_schedule'], ['value' => $this->autoBackupSchedule]);
        \App\Models\Setting::updateOrCreate(['key' => 'backup_auto_time'], ['value' => $this->autoBackupTime]);
        \App\Models\Setting::updateOrCreate(['key' => 'backup_auto_retention'], ['value' => $this->autoBackupRetention]);

        \Flux::toast('Pengaturan backup otomatis berhasil disimpan.', variant: 'success');
    }

    public function loadBackups() {
        if (!Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }
        
        $files = Storage::disk('local')->files('backups');
        $this->backups = collect($files)
            ->filter(fn($file) => str_ends_with($file, '.zip') || str_ends_with($file, '.sqlite'))
            ->map(function($file) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => round(Storage::disk('local')->size($file) / 1024 / 1024, 2) . ' MB',
                    'date' => Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file))->format('Y-m-d H:i:s')
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->toArray();
    }

    public function createBackup() {
        if (!class_exists('ZipArchive')) {
            // Fallback to SQLite only if ZipArchive is missing
            $dbPath = database_path('database.sqlite');
            $backupName = 'backups/backup-' . now()->format('Y-m-d_H-i-s') . '.sqlite';
            
            if (file_exists($dbPath)) {
                Storage::disk('local')->put($backupName, file_get_contents($dbPath));
                \Flux::toast('Ekstensi ZipArchive PHP tidak aktif! Hanya Database yang diamankan, tanpa gambar.', variant: 'warning');
                $this->loadBackups();
            } else {
                \Flux::toast('File database utama tidak ditemukan!', variant: 'danger');
            }
            return;
        }

        $backupName = 'backups/backup-' . now()->format('Y-m-d_H-i-s') . '.zip';
        $backupPath = Storage::disk('local')->path($backupName);
        
        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
            
            // Add Database
            $dbPath = database_path('database.sqlite');
            if (file_exists($dbPath)) {
                $zip->addFile($dbPath, 'database.sqlite');
            }

            // Add Public Storage Images
            $publicStoragePath = storage_path('app/public');
            if (is_dir($publicStoragePath)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($publicStoragePath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'public/' . substr($filePath, strlen($publicStoragePath) + 1);
                        $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                    }
                }
            }
            
            $zip->close();
            \Flux::toast('Backup total (Database & Gambar) berhasil dibuat.', variant: 'success');
            $this->loadBackups();
        } else {
            \Flux::toast('Gagal membuat arsip ZIP!', variant: 'danger');
        }
    }

    public function restoreBackup($name) {
        $backupPath = Storage::disk('local')->path('backups/' . $name);
        
        if (file_exists($backupPath)) {
            // Backward compatibility for old .sqlite backups
            if (str_ends_with($name, '.sqlite')) {
                copy($backupPath, database_path('database.sqlite'));
                \Flux::toast('Database berhasil direstore. Halaman dimuat ulang.', variant: 'success');
                return redirect()->route('settings.system');
            }

            if (!class_exists('ZipArchive')) {
                \Flux::toast('Ekstensi ZipArchive PHP tidak aktif, gagal me-restore gambar!', variant: 'danger');
                return;
            }

            // Unzip process
            $zip = new \ZipArchive();
            if ($zip->open($backupPath) === TRUE) {
                // Extract database
                if ($zip->locateName('database.sqlite') !== false) {
                    $zip->extractTo(database_path(), 'database.sqlite');
                }
                
                // Extract images to storage/app/public
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (str_starts_with($filename, 'public/')) {
                        $zip->extractTo(storage_path('app'), $filename);
                    }
                }
                $zip->close();
                
                \Flux::toast('Database & Gambar berhasil direstore secara total.', variant: 'success');
                return redirect()->route('settings.system');
            } else {
                \Flux::toast('Gagal membuka file backup ZIP.', variant: 'danger');
            }
        }
    }

    public function updatedUploadedBackupFile() {
        $this->uploadBackup();
    }

    public function uploadBackup() {
        $this->validate([
            'uploadedBackupFile' => 'required|file|max:204800', // max 200MB limit via Livewire
        ]);

        $extension = $this->uploadedBackupFile->getClientOriginalExtension();
        if (!in_array($extension, ['zip', 'sqlite'])) {
            $this->addError('uploadedBackupFile', 'Format file tidak didukung. Harus .zip atau .sqlite');
            return;
        }

        $originalName = $this->uploadedBackupFile->getClientOriginalName();
        // Prevent overwriting existing backups directly by appending timestamp if it already exists
        if (Storage::disk('local')->exists('backups/' . $originalName)) {
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
            $originalName = $nameWithoutExt . '_uploaded_' . now()->timestamp . '.' . $extension;
        }

        $this->uploadedBackupFile->storeAs('backups', $originalName, 'local');
        
        $this->reset('uploadedBackupFile');
        \Flux::toast('File backup berhasil diunggah.', variant: 'success');
        $this->loadBackups();
    }

    public function deleteBackup($name) {
        Storage::disk('local')->delete('backups/' . $name);
        \Flux::toast('Backup dihapus.', variant: 'success');
        $this->loadBackups();
    }

    public function executeReset() {
        if (trim($this->confirmPassword) !== 'RESET') {
            $this->addError('confirmPassword', 'Ketik "RESET" (huruf besar semua) untuk mengonfirmasi penghapusan.');
            return;
        }

        try {
            DB::statement('PRAGMA foreign_keys = OFF;');
            
            // 1. Finance
            if ($this->wipeFinance) {
                DB::table('finance_transactions')->truncate();
                DB::table('finance_transfers')->truncate();
                DB::table('finance_accounts')->update(['current_balance' => 0]);
            }
            
            // 2. Sales
            if ($this->wipeSales) {
                DB::table('sales_orders')->truncate();
                DB::table('sales_order_items')->truncate();
                DB::table('sales_order_fulfillments')->truncate();
                DB::table('sales_payments')->truncate();
                DB::table('sales_returns')->truncate();
                DB::table('sales_return_items')->truncate();
                DB::table('quotations')->truncate();
                DB::table('quotation_items')->truncate();
            }

            // 3. Purchase
            if ($this->wipePurchase) {
                DB::table('purchase_orders')->truncate();
                DB::table('purchase_order_items')->truncate();
                DB::table('purchase_queues')->truncate();
                DB::table('purchase_queue_fulfillments')->truncate();
                DB::table('purchase_receipts')->truncate();
                DB::table('purchase_receipt_items')->truncate();
                DB::table('purchase_returns')->truncate();
                DB::table('purchase_return_items')->truncate();
                DB::table('purchase_payments')->truncate();
            }

            // 4. Inventory
            if ($this->wipeInventory) {
                DB::table('stock_movements')->truncate();
                DB::table('stock_adjustments')->truncate();
                DB::table('stock_transfers')->truncate();
                DB::table('stock_transfer_items')->truncate();
                DB::table('inventory_requests')->truncate();
                DB::table('item_warehouse')->update(['stock' => 0]);
            }

            // 5. Production
            if ($this->wipeProduction) {
                DB::table('production_orders')->truncate();
                DB::table('production_order_histories')->truncate();
            }

            // 6. Master Data
            if ($this->wipeMaster) {
                DB::table('items')->truncate();
                DB::table('categories')->truncate();
                DB::table('sub_categories')->truncate();
                DB::table('brands')->truncate();
                DB::table('types')->truncate();
                DB::table('units')->truncate();
                DB::table('customers')->truncate();
                DB::table('vendors')->truncate();
                DB::table('warehouses')->truncate();
                DB::table('cms_posts')->truncate();
                DB::table('cms_categories')->truncate();
                // We should also delete items from item_warehouse since warehouses are truncated
                DB::table('item_warehouse')->truncate();
            }

            // 7. Users
            if ($this->wipeUsers) {
                // Hapus semua user kecuali yang punya role Super Admin
                User::whereDoesntHave('roles', function($q){
                    $q->where('name', 'Super Admin');
                })->delete();
            }

            DB::statement('PRAGMA foreign_keys = ON;');

            // 8. Images
            if ($this->wipeImages) {
                $directories = Storage::disk('public')->directories();
                foreach ($directories as $dir) {
                    Storage::disk('public')->deleteDirectory($dir);
                }
            }

            \Flux::toast('Operasi pembersihan data berhasil dieksekusi!', variant: 'success');
            
            $this->reset(['wipeFinance', 'wipeSales', 'wipePurchase', 'wipeInventory', 'wipeProduction', 'wipeMaster', 'wipeImages', 'wipeUsers', 'confirmPassword']);
            
        } catch (\Exception $e) {
            DB::statement('PRAGMA foreign_keys = ON;');
            \Flux::toast('Terjadi kesalahan: ' . $e->getMessage(), variant: 'danger');
        }
    }
};
?>

<x-pages::settings.layout
    heading="Sistem & Zona Bahaya"
    subheading="Kelola cadangan database (Backup) dan pembersihan data transaksi untuk keperluan reset pabrik.">

    <div class="space-y-10">
        
        {{-- BACKUP SECTION --}}
        <section>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Cadangkan Database</h3>
                    <p class="text-sm text-zinc-500">Buat salinan data Anda sebagai jaring pengaman.</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Upload Input -->
                    <div class="relative flex items-center">
                        <label class="cursor-pointer bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-sm font-medium h-8 px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 transition-colors flex items-center gap-2 whitespace-nowrap shadow-sm">
                            <flux:icon.arrow-up-tray class="w-4 h-4" />
                            <span>Unggah (.zip)</span>
                            <input type="file" wire:model="uploadedBackupFile" class="hidden" accept=".zip,.sqlite" />
                        </label>
                        <div wire:loading wire:target="uploadedBackupFile" class="absolute right-0 -bottom-6 w-max text-xs font-semibold text-zinc-500 animate-pulse">Mengunggah...</div>
                        @error('uploadedBackupFile') <div class="absolute right-0 -bottom-6 w-max text-xs font-semibold text-red-500">{{ $message }}</div> @enderror
                    </div>

                    <flux:button wire:click="createBackup" variant="primary" icon="document-duplicate" size="sm">
                        Buat Backup Sekarang
                    </flux:button>
                </div>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white dark:bg-zinc-900">
                <table class="w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nama File</th>
                            <th class="px-4 py-3 font-medium">Tanggal</th>
                            <th class="px-4 py-3 font-medium">Ukuran</th>
                            <th class="px-4 py-3 font-medium text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($backups as $backup)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-mono text-xs">{{ $backup['name'] }}</td>
                                <td class="px-4 py-3">{{ $backup['date'] }}</td>
                                <td class="px-4 py-3">{{ $backup['size'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button as="a" href="{{ route('settings.backup.download', $backup['name']) }}" variant="subtle" size="sm" class="!px-2" tooltip="Download ke Komputer">
                                            <flux:icon.arrow-down-tray class="w-4 h-4 text-zinc-500" />
                                        </flux:button>
                                        <flux:button wire:click="restoreBackup('{{ $backup['name'] }}')" wire:confirm="PERINGATAN: Me-restore sistem ini akan MENIMPA dan MENGHAPUS semua database dan gambar saat ini. Anda yakin?" variant="subtle" size="sm" icon="arrow-path">
                                            Restore
                                        </flux:button>
                                        <flux:button wire:click="deleteBackup('{{ $backup['name'] }}')" wire:confirm="Hapus file backup ini?" variant="danger" size="sm" class="!px-2">
                                            <flux:icon.trash class="w-4 h-4" />
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-zinc-500">
                                    Belum ada file backup. Sangat disarankan untuk membuat backup sebelum mereset data.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <flux:separator />

        {{-- AUTO BACKUP SETTINGS SECTION --}}
        <section>
            <form wire:submit="saveAutoBackupSettings" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Pengaturan Backup Otomatis</h3>
                        <p class="text-sm text-zinc-500">Jadwalkan backup otomatis (Cron) dan tentukan usia retensi penghapusan (Auto-Clean).</p>
                    </div>
                    <flux:switch wire:model.live="autoBackupEnabled" />
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 transition-opacity {{ $autoBackupEnabled ? 'opacity-100' : 'opacity-50 pointer-events-none' }}">
                    <flux:select wire:model="autoBackupSchedule" label="Frekuensi">
                        <option value="daily">Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </flux:select>
                    
                    <flux:input type="time" wire:model="autoBackupTime" label="Jam Eksekusi" />
                    
                    <flux:input type="number" min="1" max="365" wire:model="autoBackupRetention" label="Simpan Selama (Hari)" />
                </div>
                
                <div class="mt-6 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check" size="sm">Simpan Jadwal</flux:button>
                </div>

                @if($autoBackupEnabled)
                <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700 transition-opacity">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Panduan Integrasi Server (cPanel / VPS)</p>
                    <p class="text-xs text-zinc-500 mb-3">Salin dan tempel perintah Master Cron Job di bawah ini ke pengaturan server Anda. Pengaturan ini hanya perlu ditambahkan <strong>satu kali saja</strong>:</p>
                    
                    <div class="relative group mt-3">
                        <flux:input 
                            readonly 
                            value="cd {{ base_path() }} && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1"
                            class="font-mono text-xs !bg-zinc-50 dark:!bg-zinc-800"
                        />
                        <div class="absolute inset-y-0 right-1 flex items-center">
                            <flux:button 
                                variant="subtle" 
                                size="sm" 
                                icon="clipboard-document" 
                                class="!px-2 h-8"
                                onclick="navigator.clipboard.writeText('cd {{ addslashes(base_path()) }} && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1'); alert('Disalin ke clipboard!')"
                                tooltip="Salin"
                            />
                        </div>
                    </div>

                    <details class="mt-4 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-800/20 text-sm cursor-pointer [&_summary::-webkit-details-marker]:hidden">
                        <summary class="font-medium text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                            Lihat Panduan Pemasangan (cPanel)
                            <flux:icon.chevron-down class="w-4 h-4 text-zinc-400 transition-transform duration-200 group-open:-rotate-180" />
                        </summary>
                        <div class="mt-3 pt-3 border-t border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 space-y-2 cursor-auto">
                            <p><strong>Langkah 1:</strong> Login ke dashboard cPanel *hosting* Anda.</p>
                            <p><strong>Langkah 2:</strong> Gulir ke bawah ke bagian <em>Advanced</em> dan temukan menu <strong>Cron Jobs</strong>.</p>
                            <p><strong>Langkah 3:</strong> Pada bagian <em>Common Settings</em>, pilih opsi <strong>Once Per Minute (* * * * *)</strong>.</p>
                            <p><strong>Langkah 4:</strong> Salin perintah yang telah di-<em>generate</em> di atas dan tempelkan ke dalam kotak isian <strong>Command</strong>.</p>
                            <p><strong>Langkah 5:</strong> Klik tombol <strong>Add New Cron Job</strong>. Selesai!</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 italic">* Catatan: Anda hanya perlu melakukan pengaturan ini satu kali. Seluruh jadwal Backup Anda akan diatur langsung dari halaman ini oleh sistem.</p>
                        </div>
                    </details>
                </div>
                @endif
            </form>
        </section>

        <flux:separator />

        {{-- DANGER ZONE SECTION --}}
        <section class="border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/10 rounded-xl p-6">
            <div class="flex gap-4 items-start mb-6">
                <div class="bg-red-100 dark:bg-red-900/30 p-2 rounded-full shrink-0">
                    <flux:icon.exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-red-700 dark:text-red-400">Pembersihan Data (Data Wipe)</h3>
                    <p class="text-sm text-red-600/80 dark:text-red-400/80 mt-1">Pilih modul-modul yang ingin Anda hapus datanya. Operasi ini bersifat permanen dan tidak dapat dibatalkan kecuali Anda memiliki backup.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mb-8 bg-white dark:bg-zinc-900 p-5 rounded-lg border border-red-100 dark:border-red-900/30">
                <flux:checkbox wire:model="wipeSales" label="Transaksi Penjualan (SO, Faktur, Retur)" />
                <flux:checkbox wire:model="wipePurchase" label="Transaksi Pembelian (PO, Penerimaan, Retur)" />
                <flux:checkbox wire:model="wipeInventory" label="Transaksi Gudang (Mutasi, Adjustment, Transfer)" />
                <flux:checkbox wire:model="wipeProduction" label="Transaksi Produksi (Work Orders)" />
                <flux:checkbox wire:model="wipeFinance" label="Transaksi Keuangan (Pembayaran, Setel saldo = 0)" />
                <flux:checkbox wire:model="wipeImages" label="Data Gambar & Lampiran (Hapus semua file upload)" />
                
                <div class="col-span-1 md:col-span-2 mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                    <flux:checkbox wire:model="wipeMaster" label="Master Data (Barang, Kontak, Gudang, Kategori)" />
                    <flux:checkbox wire:model="wipeUsers" label="Akun Pengguna (Kecuali Super Admin)" />
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-4">
                <div class="w-full sm:w-64">
                    <flux:input wire:model="confirmPassword" label="Ketik 'RESET' untuk konfirmasi" placeholder="RESET" />
                </div>
                <flux:button wire:click="executeReset" variant="danger" icon="trash" class="w-full sm:w-auto">
                    Eksekusi Penghapusan
                </flux:button>
            </div>
            @error('confirmPassword') 
                <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
            @enderror
        </section>

    </div>
</x-pages::settings.layout>
