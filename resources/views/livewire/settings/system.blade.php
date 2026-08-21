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
    // Wipe Master Data
    public $wipeMasterItems = false;
    public $wipeMasterCategories = false;
    public $wipeMasterWarehouses = false;
    public $wipeMasterCustomers = false;
    public $wipeMasterSuppliers = false;

    // Wipe Images
    public $wipeImageItems = false;
    public $wipeImageSalesPayments = false;
    public $wipeImagePurchasePayments = false;
    public $wipeImageReceipts = false;
    public $wipeImageProfiles = false;
    public $wipeUsers = false;

    public $autoBackupEnabled = false;
    public $autoBackupSchedule = 'daily';
    public $autoBackupTime = '23:59';
    public $autoBackupRetention = 30;
    
    // Google Drive
    public $googleDriveEnabled = false;
    public $googleDriveFolderId = '';
    public $uploadedJsonFile;
    public $testDriveResult = null; // Menyimpan pesan hasil test koneksi

    public $originalBackupSettings = [];

    public $counts = [];

    public $githubRepo = '';
    public $githubBranch = 'main';
    public $availableBranches = [];
    public $updateCheckResult = null;
    public $isUpdating = false;

    public function mount() {
        $this->loadBackups();
        $this->loadDataCounts();
        
        $this->autoBackupEnabled = \App\Models\Setting::where('key', 'backup_auto_enabled')->value('value') === 'true';
        $this->autoBackupSchedule = \App\Models\Setting::where('key', 'backup_auto_schedule')->value('value') ?? 'daily';
        $this->autoBackupTime = \App\Models\Setting::where('key', 'backup_auto_time')->value('value') ?? '23:59';
        $this->autoBackupRetention = \App\Models\Setting::where('key', 'backup_auto_retention')->value('value') ?? 30;

        $this->googleDriveEnabled = \App\Models\Setting::where('key', 'google_drive_enabled')->value('value') === 'true';
        $this->googleDriveFolderId = \App\Models\Setting::where('key', 'google_drive_folder')->value('value') ?? '';

        $this->githubRepo = \App\Models\Setting::where('key', 'github_repo')->value('value') ?? 'Agung-laksono/final';
        $this->githubBranch = \App\Models\Setting::where('key', 'github_branch')->value('value') ?? 'main';

        $this->originalBackupSettings = [
            'enabled' => $this->autoBackupEnabled,
            'schedule' => $this->autoBackupSchedule,
            'time' => $this->autoBackupTime,
            'retention' => $this->autoBackupRetention,
            'gdrive_enabled' => $this->googleDriveEnabled,
            'gdrive_folder' => $this->googleDriveFolderId,
            'github_repo' => $this->githubRepo,
            'github_branch' => $this->githubBranch,
        ];

        if (!empty($this->githubRepo)) {
            $this->fetchBranches();
        }
    }

    public function hasUnsavedChanges() {
        return $this->autoBackupEnabled !== $this->originalBackupSettings['enabled'] ||
               $this->autoBackupSchedule !== $this->originalBackupSettings['schedule'] ||
               $this->autoBackupTime !== $this->originalBackupSettings['time'] ||
               $this->autoBackupRetention != $this->originalBackupSettings['retention'] ||
               $this->googleDriveEnabled !== $this->originalBackupSettings['gdrive_enabled'] ||
               $this->googleDriveFolderId !== $this->originalBackupSettings['gdrive_folder'] ||
               $this->githubRepo !== $this->originalBackupSettings['github_repo'] ||
               $this->githubBranch !== $this->originalBackupSettings['github_branch'];
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
        
        \App\Models\Setting::updateOrCreate(['key' => 'google_drive_enabled'], ['value' => $this->googleDriveEnabled ? 'true' : 'false']);
        \App\Models\Setting::updateOrCreate(['key' => 'google_drive_folder'], ['value' => $this->googleDriveFolderId]);
        
        \App\Models\Setting::updateOrCreate(['key' => 'github_repo'], ['value' => $this->githubRepo]);
        \App\Models\Setting::updateOrCreate(['key' => 'github_branch'], ['value' => $this->githubBranch]);

        // Perbarui config dinamis
        \Illuminate\Support\Facades\Config::set('filesystems.disks.google.folder', $this->googleDriveFolderId);

        $this->originalBackupSettings = [
            'enabled' => $this->autoBackupEnabled,
            'schedule' => $this->autoBackupSchedule,
            'time' => $this->autoBackupTime,
            'retention' => $this->autoBackupRetention,
            'gdrive_enabled' => $this->googleDriveEnabled,
            'gdrive_folder' => $this->googleDriveFolderId,
            'github_repo' => $this->githubRepo,
            'github_branch' => $this->githubBranch,
        ];

        \Flux::toast('Pengaturan berhasil disimpan.', variant: 'success');
        
        // Coba refresh branch saat repo di-save
        if (!empty($this->githubRepo)) {
            $this->fetchBranches();
        }
    }

    public function fetchBranches()
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'ERP-Update-System',
            ])->get("https://api.github.com/repos/{$this->githubRepo}/branches");
            
            if ($response->successful()) {
                $this->availableBranches = collect($response->json())->pluck('name')->toArray();
                // Jika branch saat ini tidak ada di daftar, reset ke yang pertama (biasanya main/master)
                if (!in_array($this->githubBranch, $this->availableBranches) && count($this->availableBranches) > 0) {
                    $this->githubBranch = $this->availableBranches[0];
                }
            } else {
                $this->availableBranches = [];
            }
        } catch (\Exception $e) {
            $this->availableBranches = [];
        }
    }

    public function checkSystemUpdate()
    {
        $this->updateCheckResult = null;
        if (empty($this->githubRepo)) {
            \Flux::toast('Repository GitHub belum diatur!', variant: 'danger');
            return;
        }

        try {
            $updateService = app(\App\Services\UpdateService::class);
            $result = $updateService->checkLatestUpdate($this->githubRepo, $this->githubBranch);
            
            if ($result['status'] === 'success') {
                $this->updateCheckResult = $result;
                \Flux::toast('Pengecekan update berhasil.', variant: 'success');
            } else {
                \Flux::toast($result['message'], variant: 'danger');
            }
        } catch (\Exception $e) {
            \Flux::toast('Gagal mengecek update: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function performSystemUpdate()
    {
        if (empty($this->githubRepo)) return;
        
        $this->isUpdating = true;
        
        try {
            // 1. Buat Backup sebelum Update
            $this->createBackup();

            // 2. Lakukan Update via UpdateService
            $updateService = app(\App\Services\UpdateService::class);
            $result = $updateService->performUpdate($this->githubRepo, $this->githubBranch);
            
            if ($result['status'] === 'success') {
                \Flux::toast($result['message'], variant: 'success');
                $this->updateCheckResult = null;
            } else {
                \Flux::toast('Gagal Update: ' . $result['message'], variant: 'danger');
            }
        } catch (\Exception $e) {
            \Flux::toast('Terjadi Kesalahan Kritis: ' . $e->getMessage(), variant: 'danger');
        }
        
        $this->isUpdating = false;
        // Opsional: Reload window
        $this->js('setTimeout(() => window.location.reload(), 2000)');
    }

    public function updatedUploadedJsonFile() {
        $this->validate([
            'uploadedJsonFile' => 'required|file|max:1024',
        ]);
        
        $this->uploadedJsonFile->storeAs('', 'google-drive-credentials.json', 'local');
        $this->reset('uploadedJsonFile');
        \Flux::toast('File JSON Kredensial berhasil diunggah.', variant: 'success');
    }

    public function testGoogleDrive() {
        $this->testDriveResult = null;
        try {
            if (!Storage::disk('local')->exists('google-drive-credentials.json')) {
                $this->testDriveResult = ['status' => 'error', 'message' => 'File Kredensial JSON belum diunggah!'];
                return;
            }
            if (empty($this->googleDriveFolderId)) {
                $this->testDriveResult = ['status' => 'error', 'message' => 'Folder ID harus diisi!'];
                return;
            }
            
            \Illuminate\Support\Facades\Config::set('filesystems.disks.google.folder', $this->googleDriveFolderId);
            app('filesystem')->forgetDisk('google');
            
            // Coba tulis file dummy
            Storage::disk('google')->put('test-koneksi.txt', 'Ini file uji coba dari sistem aplikasi Anda pada ' . now()->format('Y-m-d H:i:s'));
            
            $this->testDriveResult = ['status' => 'success', 'message' => 'Koneksi Google Drive BERHASIL! File test dibuat di Drive Anda.'];
        } catch (\Exception $e) {
            $this->testDriveResult = ['status' => 'error', 'message' => 'Koneksi GAGAL: ' . $e->getMessage()];
        }
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
            
            // Upload to Google Drive if enabled
            if ($this->googleDriveEnabled) {
                try {
                    \Illuminate\Support\Facades\Config::set('filesystems.disks.google.folder', $this->googleDriveFolderId);
                    app('filesystem')->forgetDisk('google');
                    $driveFileName = basename($backupPath);
                    $fileContent = file_get_contents($backupPath);
                    Storage::disk('google')->put($driveFileName, $fileContent);
                    \Flux::toast('Berhasil mengunggah backup ke Google Drive!', variant: 'success');
                } catch (\Exception $e) {
                    \Flux::toast('Gagal upload ke Google Drive: ' . $e->getMessage(), variant: 'danger');
                }
            }

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
            DB::statement('PRAGMA foreign_keys = ON;');
            
            // 1. Finance
            if ($this->wipeFinance) {
                DB::table('finance_transactions')->delete();
                DB::table('finance_transfers')->delete();
                DB::table('finance_accounts')->update(['current_balance' => 0]);
            }
            
            // 2. Sales
            if ($this->wipeSales) {
                DB::table('sales_orders')->delete();
                DB::table('sales_order_items')->delete();
                DB::table('sales_order_fulfillments')->delete();
                DB::table('sales_payments')->delete();
                DB::table('sales_returns')->delete();
                DB::table('sales_return_items')->delete();
                DB::table('quotations')->delete();
                DB::table('quotation_items')->delete();
            }

            // 3. Purchase
            if ($this->wipePurchase) {
                DB::table('purchase_orders')->delete();
                DB::table('purchase_order_items')->delete();
                DB::table('purchase_queues')->delete();
                DB::table('purchase_queue_fulfillments')->delete();
                DB::table('purchase_receipts')->delete();
                DB::table('purchase_receipt_items')->delete();
                DB::table('purchase_returns')->delete();
                DB::table('purchase_return_items')->delete();
                DB::table('purchase_payments')->delete();
            }

            // 4. Inventory
            if ($this->wipeInventory) {
                DB::table('stock_movements')->delete();
                DB::table('stock_adjustments')->delete();
                DB::table('stock_transfers')->delete();
                DB::table('stock_transfer_items')->delete();
                DB::table('inventory_requests')->delete();
                DB::table('item_warehouse')->update(['stock' => 0]);
            }

            // 5. Production
            if ($this->wipeProduction) {
                DB::table('production_orders')->delete();
                DB::table('production_order_histories')->delete();
            }

            // 6. Master Data
            if ($this->wipeMasterItems) {
                DB::table('items')->delete();
                DB::table('brands')->delete();
                DB::table('types')->delete();
                DB::table('units')->delete();
            }
            if ($this->wipeMasterCategories) {
                DB::table('categories')->delete();
                DB::table('sub_categories')->delete();
            }
            if ($this->wipeMasterCustomers) {
                DB::table('customers')->delete();
            }
            if ($this->wipeMasterSuppliers) {
                DB::table('vendors')->delete();
            }
            if ($this->wipeMasterWarehouses) {
                DB::table('warehouses')->delete();
                DB::table('item_warehouse')->delete();
            }

            // 6.b CMS Data (Optional, we'll keep it with items for now or separate it later if needed, we'll put it in Categories for now or just truncate it with categories)
            if ($this->wipeMasterCategories) {
                DB::table('cms_posts')->delete();
                DB::table('cms_categories')->delete();
            }

            // 7. Users
            if ($this->wipeUsers) {
                // Hapus semua user kecuali yang punya role Super Admin
                User::whereDoesntHave('roles', function($q){
                    $q->where('name', 'Super Admin');
                })->delete();
            }

            // 8. Images
            if ($this->wipeImageItems) {
                if (Storage::disk('public')->exists('items')) Storage::disk('public')->deleteDirectory('items');
                if (Storage::disk('public')->exists('brands')) Storage::disk('public')->deleteDirectory('brands');
            }
            if ($this->wipeImageSalesPayments) {
                if (Storage::disk('public')->exists('sales_payments')) Storage::disk('public')->deleteDirectory('sales_payments');
            }
            if ($this->wipeImagePurchasePayments) {
                if (Storage::disk('public')->exists('purchase_payments')) Storage::disk('public')->deleteDirectory('purchase_payments');
            }
            if ($this->wipeImageReceipts) {
                if (Storage::disk('public')->exists('receipts')) Storage::disk('public')->deleteDirectory('receipts');
                if (Storage::disk('public')->exists('custom_attachments')) Storage::disk('public')->deleteDirectory('custom_attachments');
            }
            if ($this->wipeImageProfiles) {
                $dirs = ['avatars', 'customers', 'vendors', 'warehouses'];
                foreach ($dirs as $dir) {
                    if (Storage::disk('public')->exists($dir)) Storage::disk('public')->deleteDirectory($dir);
                }
            }
            
            \Flux::toast('Operasi pembersihan data berhasil dieksekusi!', variant: 'success');
            
            $this->reset([
                'wipeFinance', 'wipeSales', 'wipePurchase', 'wipeInventory', 'wipeProduction', 
                'wipeMasterItems', 'wipeMasterCategories', 'wipeMasterWarehouses', 'wipeMasterCustomers', 'wipeMasterSuppliers', 
                'wipeImageItems', 'wipeImageSalesPayments', 'wipeImagePurchasePayments', 'wipeImageReceipts', 'wipeImageProfiles', 
                'wipeUsers', 'confirmPassword'
            ]);
            $this->loadDataCounts();
            
            // Reload halaman secara penuh agar seluruh state Livewire (termasuk floating menu) ikut kereset
            $this->js('setTimeout(() => window.location.reload(), 1500)');
            
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'foreign key constraint failed') || str_contains($message, 'Integrity constraint violation')) {
                \Flux::toast('Gagal: Tidak dapat menghapus data Master karena masih ada data Transaksi yang terikat. Harap hapus transaksi terkait terlebih dahulu.', variant: 'danger');
            } else {
                \Flux::toast('Terjadi kesalahan: ' . $message, variant: 'danger');
            }
        }
    }

    public function loadDataCounts() {
        $this->counts = [
            // Transactions
            'sales' => DB::table('sales_orders')->count() + DB::table('sales_payments')->count() + DB::table('sales_returns')->count() + DB::table('quotations')->count(),
            'purchase' => DB::table('purchase_orders')->count() + DB::table('purchase_receipts')->count() + DB::table('purchase_payments')->count() + DB::table('purchase_returns')->count() + DB::table('purchase_queues')->count(),
            'inventory' => DB::table('stock_movements')->count() + DB::table('stock_adjustments')->count() + DB::table('stock_transfers')->count() + DB::table('inventory_requests')->count(),
            'production' => DB::table('production_orders')->count() + DB::table('production_order_histories')->count(),
            'finance' => DB::table('finance_transactions')->count() + DB::table('finance_transfers')->count(),
            
            // Master Data
            'master_items' => DB::table('items')->count() + DB::table('brands')->count() + DB::table('types')->count() + DB::table('units')->count(),
            'master_categories' => DB::table('categories')->count() + DB::table('sub_categories')->count(),
            'master_warehouses' => DB::table('warehouses')->count(),
            'master_customers' => DB::table('customers')->count(),
            'master_suppliers' => DB::table('vendors')->count(),
            'users' => User::whereDoesntHave('roles', function($q) { $q->where('name', 'Super Admin'); })->count(),

            // Images (File counts)
            'img_items' => count(Storage::disk('public')->allFiles('items')) + count(Storage::disk('public')->allFiles('brands')),
            'img_sales_pay' => count(Storage::disk('public')->allFiles('sales_payments')),
            'img_purchase_pay' => count(Storage::disk('public')->allFiles('purchase_payments')),
            'img_receipts' => count(Storage::disk('public')->allFiles('receipts')) + count(Storage::disk('public')->allFiles('custom_attachments')),
            'img_profiles' => count(Storage::disk('public')->allFiles('avatars')) + count(Storage::disk('public')->allFiles('customers')) + count(Storage::disk('public')->allFiles('vendors')) + count(Storage::disk('public')->allFiles('warehouses')),
        ];
    }
};
?>

<x-pages::settings.layout
    heading="Sistem & Zona Bahaya"
    subheading="Kelola cadangan database (Backup) dan pembersihan data transaksi untuk keperluan reset pabrik.">

    <div class="space-y-10">
        
        {{-- BACKUP SECTION --}}
        <section>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Cadangkan Database</h3>
                    <p class="text-sm text-zinc-500">Buat salinan data Anda sebagai jaring pengaman.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Upload Input -->
                    <div class="relative flex items-center"
                         x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true; progress = 0"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false; Flux.toast({text: 'Gagal mengunggah file. Ukuran file mungkin melebihi batas server (upload_max_filesize).', variant: 'danger'})"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                        <label class="cursor-pointer bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-sm font-medium h-8 px-3 rounded-lg border border-zinc-200 dark:border-zinc-700 transition-colors flex items-center gap-2 whitespace-nowrap shadow-sm">
                            <flux:icon.arrow-up-tray class="w-4 h-4" />
                            <span>Unggah (.zip)</span>
                            <input type="file" wire:model="uploadedBackupFile" class="hidden" accept=".zip,.sqlite" />
                        </label>
                        
                        <!-- Progress Bar (Alpine) -->
                        <div x-show="isUploading" class="absolute right-0 -bottom-5 w-[120px]" style="display: none;">
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5">
                                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-150" x-bind:style="`width: ${progress}%`"></div>
                            </div>
                            <div class="text-[10px] text-zinc-500 text-right mt-0.5" x-text="`${progress}%`"></div>
                        </div>

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

        {{-- SYSTEM UPDATE SECTION --}}
        <section>
            <div class="bg-indigo-50/50 dark:bg-indigo-900/10 border border-indigo-200 dark:border-indigo-900/50 rounded-xl p-6">
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="w-full md:w-1/3">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="bg-indigo-100 dark:bg-indigo-900/30 p-2 rounded-full shrink-0">
                                <flux:icon.arrow-path class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <h3 class="text-lg font-bold text-indigo-800 dark:text-indigo-300">Pembaruan Sistem</h3>
                        </div>
                        <p class="text-sm text-indigo-700/80 dark:text-indigo-400/80 mb-4">
                            Perbarui sistem langsung dari repositori GitHub. Sistem akan otomatis melakukan backup sebelum memperbarui file.
                        </p>
                        
                        <div class="space-y-4 bg-white dark:bg-zinc-900 p-4 rounded-lg border border-indigo-100 dark:border-indigo-900/30">
                            <flux:input wire:model.defer="githubRepo" label="Repository GitHub" placeholder="Contoh: user/repo" />
                            
                            @if(count($availableBranches) > 0)
                                <flux:select wire:model.defer="githubBranch" label="Pilih Branch">
                                    @foreach($availableBranches as $branch)
                                        <flux:select.option value="{{ $branch }}">{{ $branch }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input wire:model.defer="githubBranch" label="Branch" placeholder="Contoh: main" />
                                <div class="text-xs text-amber-600 mt-1">Daftar branch otomatis gagal dimuat. Ketik manual.</div>
                            @endif
                            
                            <flux:button wire:click="saveAutoBackupSettings" variant="outline" size="sm" class="w-full">Simpan & Muat Branch</flux:button>
                        </div>
                    </div>
                    
                    <div class="w-full md:w-2/3">
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-5 h-full flex flex-col justify-center">
                            
                            @if(!$updateCheckResult)
                                <div class="text-center space-y-4">
                                    <flux:icon.cloud-arrow-down class="w-12 h-12 text-zinc-300 mx-auto" />
                                    <div>
                                        <p class="font-medium text-zinc-900 dark:text-white">Belum Mengecek Pembaruan</p>
                                        <p class="text-sm text-zinc-500">Cek apakah ada pembaruan kode terbaru di GitHub.</p>
                                    </div>
                                    <flux:button wire:click="checkSystemUpdate" variant="primary">Cek Pembaruan</flux:button>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h4 class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                                <flux:icon.check-circle class="w-5 h-5 text-emerald-500" /> Pembaruan Ditemukan!
                                            </h4>
                                            <p class="text-sm text-zinc-500 mt-1">Commit terbaru di repository Anda:</p>
                                        </div>
                                        <flux:button wire:click="checkSystemUpdate" variant="subtle" size="sm" icon="arrow-path">Cek Ulang</flux:button>
                                    </div>
                                    
                                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-md p-4 border border-zinc-100 dark:border-zinc-700 font-mono text-sm">
                                        <div class="text-zinc-600 dark:text-zinc-400 mb-2 truncate">
                                            <span class="font-semibold">Pesan:</span> "{{ $updateCheckResult['commit_message'] }}"
                                        </div>
                                        <div class="text-zinc-500 text-xs">
                                            <span class="font-semibold">SHA:</span> {{ substr($updateCheckResult['commit_sha'], 0, 7) }} &bull; 
                                            <span class="font-semibold">Tanggal:</span> {{ \Carbon\Carbon::parse($updateCheckResult['date'])->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700 flex justify-end">
                                        <flux:button 
                                            wire:click="performSystemUpdate" 
                                            wire:confirm="PERINGATAN: Sistem akan menimpa (replace) source code dengan versi terbaru dari GitHub. Pastikan tidak ada perubahan kode manual di server. Lanjutkan?"
                                            variant="primary" 
                                            icon="arrow-down-tray">
                                            <span wire:loading.remove wire:target="performSystemUpdate">Update Sistem Sekarang</span>
                                            <span wire:loading wire:target="performSystemUpdate">Mengunduh & Memperbarui...</span>
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                            
                        </div>
                    </div>
                </div>
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
                    <flux:select wire:model.live="autoBackupSchedule" label="Frekuensi">
                        <option value="daily">Harian</option>
                        <option value="weekly">Mingguan</option>
                        <option value="monthly">Bulanan</option>
                    </flux:select>
                    
                    <flux:input type="time" wire:model.live="autoBackupTime" label="Jam Eksekusi" />
                    
                    <flux:input type="number" min="1" max="365" wire:model.live="autoBackupRetention" label="Simpan Selama (Hari)" />
                </div>
                
                <div class="mt-6 flex justify-end">
                    <flux:button type="submit" :variant="$this->hasUnsavedChanges() ? 'primary' : 'outline'" icon="check" size="sm" :disabled="!$this->hasUnsavedChanges()">Simpan Jadwal</flux:button>
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

        {{-- GOOGLE DRIVE SECTION --}}
        <section>
            <form wire:submit="saveAutoBackupSettings" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Upload ke Google Drive</h3>
                            @if(Storage::disk('local')->exists('google-drive-credentials.json'))
                                <flux:badge size="sm" variant="success" icon="check-circle">Kredensial OK</flux:badge>
                            @else
                                <flux:badge size="sm" variant="danger" icon="exclamation-circle">Kredensial Kosong</flux:badge>
                            @endif
                        </div>
                        <p class="text-sm text-zinc-500 mt-1">Otomatis kirim salinan file backup Anda ke Google Drive sebagai penyimpanan aman di luar server.</p>
                    </div>
                    <flux:switch wire:model.live="googleDriveEnabled" />
                </div>
                
                <div class="grid grid-cols-1 gap-6 transition-opacity {{ $googleDriveEnabled ? 'opacity-100' : 'opacity-50 pointer-events-none' }}">
                    
                    <div class="space-y-4 bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <div class="flex flex-col sm:flex-row gap-4 items-end">
                            <div class="w-full">
                                <flux:input wire:model.live="googleDriveFolderId" label="Folder ID Google Drive" placeholder="Contoh: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs" />
                            </div>
                            
                            <div class="w-full sm:w-auto shrink-0 relative pb-1">
                                <div class="text-sm font-medium mb-2 flex items-center justify-between">
                                    File JSON Service Account
                                </div>
                                <label class="cursor-pointer bg-zinc-100 hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300 text-sm font-medium h-9 px-4 rounded-lg border border-zinc-200 dark:border-zinc-700 transition-colors flex items-center justify-center gap-2 shadow-sm">
                                    <flux:icon.document-text class="w-4 h-4" />
                                    <span>{{ Storage::disk('local')->exists('google-drive-credentials.json') ? 'Ganti File JSON' : 'Unggah File JSON' }}</span>
                                    <input type="file" wire:model.live="uploadedJsonFile" class="hidden" accept=".json" />
                                </label>
                                @if(Storage::disk('local')->exists('google-drive-credentials.json'))
                                    <div class="mt-2 flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium">
                                        <flux:icon.check-circle class="w-3.5 h-3.5" />
                                        <span>File JSON sudah terisi</span>
                                    </div>
                                @else
                                    <div class="mt-2 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                        <flux:icon.exclamation-triangle class="w-3.5 h-3.5" />
                                        <span>Belum ada file JSON</span>
                                    </div>
                                @endif
                                <div wire:loading wire:target="uploadedJsonFile" class="absolute -bottom-4 left-0 text-xs text-zinc-500">Mengunggah...</div>
                                @error('uploadedJsonFile') <div class="absolute -bottom-4 left-0 text-xs text-red-500">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <p class="text-xs text-zinc-500 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                            <strong>Cara setup:</strong> 1. Buat Service Account di Google Cloud Console. 2. Buat folder di Google Drive Anda. 3. Bagikan folder tersebut ke email Service Account (akses Editor). 4. Salin ID folder dari URL dan tempel di atas.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3">
                        @if($testDriveResult)
                            <div class="p-3 text-sm rounded-lg border flex items-start gap-2 {{ $testDriveResult['status'] === 'success' ? 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300' : 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-800 dark:text-red-300' }}">
                                @if($testDriveResult['status'] === 'success')
                                    <flux:icon.check-circle class="w-5 h-5 shrink-0" />
                                @else
                                    <flux:icon.exclamation-circle class="w-5 h-5 shrink-0" />
                                @endif
                                <div class="font-medium">{{ $testDriveResult['message'] }}</div>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <flux:button wire:click="testGoogleDrive" variant="outline" icon="bolt" size="sm">Test Koneksi</flux:button>
                                <flux:button wire:click="createBackup" variant="primary" icon="document-duplicate" size="sm">Buat Backup & Unggah</flux:button>
                            </div>
                            <flux:button type="submit" :variant="$this->hasUnsavedChanges() ? 'primary' : 'outline'" icon="check" size="sm" :disabled="!$this->hasUnsavedChanges()">Simpan Konfigurasi</flux:button>
                        </div>
                    </div>
                </div>
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

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white dark:bg-zinc-900 p-6 space-y-6 mb-6">
                <!-- Transaction Data -->
                <div>
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3 flex items-center gap-2">
                        <flux:icon.document-text class="w-4 h-4 text-zinc-400" /> Data Transaksi
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 ml-6">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeSales" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Penjualan (SO, Faktur, Retur) <flux:badge size="sm" variant="pill" :color="($counts['sales'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['sales'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipePurchase" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Pembelian (PO, Penerimaan, Retur) <flux:badge size="sm" variant="pill" :color="($counts['purchase'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['purchase'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeInventory" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Gudang (Mutasi, Adjustment, Transfer) <flux:badge size="sm" variant="pill" :color="($counts['inventory'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['inventory'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeProduction" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Produksi (Work Orders) <flux:badge size="sm" variant="pill" :color="($counts['production'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['production'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeFinance" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Keuangan (Pembayaran, Setel saldo = 0) <flux:badge size="sm" variant="pill" :color="($counts['finance'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['finance'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Master Data -->
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3 flex items-center gap-2">
                        <flux:icon.circle-stack class="w-4 h-4 text-zinc-400" /> Data Master
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 ml-6">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeMasterItems" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Barang, Merek, & Unit <flux:badge size="sm" variant="pill" :color="($counts['master_items'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['master_items'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeMasterCategories" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Kategori Barang & Artikel <flux:badge size="sm" variant="pill" :color="($counts['master_categories'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['master_categories'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeMasterWarehouses" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Daftar Gudang <flux:badge size="sm" variant="pill" :color="($counts['master_warehouses'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['master_warehouses'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeMasterCustomers" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Pelanggan <flux:badge size="sm" variant="pill" :color="($counts['master_customers'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['master_customers'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeMasterSuppliers" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Supplier / Pemasok <flux:badge size="sm" variant="pill" :color="($counts['master_suppliers'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['master_suppliers'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeUsers" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Akun Pengguna <span class="text-xs text-zinc-400 font-normal">(Kecuali Super Admin)</span> <flux:badge size="sm" variant="pill" :color="($counts['users'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['users'] ?? 0 }}</flux:badge></div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Images & Attachments -->
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3 flex items-center gap-2">
                        <flux:icon.photo class="w-4 h-4 text-zinc-400" /> Gambar & Lampiran
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 ml-6">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeImageItems" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Gambar Barang <flux:badge size="sm" variant="pill" :color="($counts['img_items'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['img_items'] ?? 0 }} file</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeImageSalesPayments" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Bukti Bayar Penjualan <flux:badge size="sm" variant="pill" :color="($counts['img_sales_pay'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['img_sales_pay'] ?? 0 }} file</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeImagePurchasePayments" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Bukti Bayar Pembelian <flux:badge size="sm" variant="pill" :color="($counts['img_purchase_pay'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['img_purchase_pay'] ?? 0 }} file</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeImageReceipts" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Surat Jalan & Dokumen <flux:badge size="sm" variant="pill" :color="($counts['img_receipts'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['img_receipts'] ?? 0 }} file</flux:badge></div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <flux:checkbox wire:model="wipeImageProfiles" />
                            <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-[-2px]">
                                <div class="flex items-center gap-2">Logo (Customer, Supplier, User) <flux:badge size="sm" variant="pill" :color="($counts['img_profiles'] ?? 0) > 0 ? 'danger' : 'zinc'">{{ $counts['img_profiles'] ?? 0 }} file</flux:badge></div>
                            </div>
                        </label>
                    </div>
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
