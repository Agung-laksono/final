<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use Carbon\Carbon;

class AutoBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Melakukan backup database dan gambar secara otomatis berdasarkan jadwal, lalu membersihkan file lama.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai proses Auto-Backup...');

        // 1. Cek apakah fitur diaktifkan
        $isEnabled = Setting::where('key', 'backup_auto_enabled')->value('value') === 'true';
        if (!$isEnabled) {
            $this->warn('Auto-backup dimatikan via pengaturan. Membatalkan proses.');
            return;
        }

        if (!Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        // 2. Lakukan Zipping Database + Storage (Logika Darurat)
        if (!class_exists('ZipArchive')) {
            // Fallback
            $dbPath = database_path('database.sqlite');
            $backupName = 'backups/backup-' . now()->format('Y-m-d_H-i-s') . '.sqlite';
            if (file_exists($dbPath)) {
                Storage::disk('local')->put($backupName, file_get_contents($dbPath));
                $this->info('Ekstensi ZipArchive PHP tidak aktif. Backup Database (.sqlite) saja berhasil dibuat.');
            }
        } else {
            // Zip Process
            $backupName = 'backups/backup-' . now()->format('Y-m-d_H-i-s') . '.zip';
            $backupPath = Storage::disk('local')->path($backupName);
            
            $zip = new \ZipArchive();
            if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                // Database
                $dbPath = database_path('database.sqlite');
                if (file_exists($dbPath)) {
                    $zip->addFile($dbPath, 'database.sqlite');
                }
                // Images
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
                $this->info('Backup total (Database & Gambar) berhasil dibuat: ' . $backupName);
                
                // --- Upload ke Google Drive ---
                $isGoogleDriveEnabled = Setting::where('key', 'backup_google_drive_enabled')->value('value') === 'true';
                if ($isGoogleDriveEnabled) {
                    try {
                        $folderId = Setting::where('key', 'backup_google_drive_folder')->value('value');
                        if ($folderId) {
                            \Illuminate\Support\Facades\Config::set('filesystems.disks.google.folder', $folderId);
                        }
                        
                        $this->info('Mengunggah backup ke Google Drive...');
                        Storage::disk('google')->put($backupName, file_get_contents($backupPath));
                        $this->info('Upload ke Google Drive berhasil.');
                    } catch (\Exception $e) {
                        $this->error('Gagal upload ke Google Drive: ' . $e->getMessage());
                        \Illuminate\Support\Facades\Log::error('Gagal upload ke Google Drive (Cron): ' . $e->getMessage());
                    }
                }
                // --- End Upload ---
            } else {
                $this->error('Gagal membuat arsip ZIP.');
                return;
            }
        }

        // 3. Proses Auto-Clean (Menghapus file yang melewati retensi)
        $retentionDays = (int) (Setting::where('key', 'backup_auto_retention')->value('value') ?? 30);
        $thresholdDate = Carbon::now()->subDays($retentionDays);

        $files = Storage::disk('local')->files('backups');
        $deletedCount = 0;
        foreach ($files as $file) {
            // Hanya periksa format zip dan sqlite
            if (str_ends_with($file, '.zip') || str_ends_with($file, '.sqlite')) {
                $lastModified = Carbon::createFromTimestamp(Storage::disk('local')->lastModified($file));
                
                // Jika file lebih tua dari usia maksimal (retention)
                if ($lastModified->lessThan($thresholdDate)) {
                    Storage::disk('local')->delete($file);
                    $deletedCount++;
                }
            }
        }

        if ($deletedCount > 0) {
            $this->info("Auto-Clean: {$deletedCount} file backup lama (di atas {$retentionDays} hari) berhasil dihapus.");
        }

        $this->info('Proses Auto-Backup & Auto-Clean selesai.');
    }
}
