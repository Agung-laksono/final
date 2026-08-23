<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;
use Exception;

class UpdateService
{
    private $tempZipPath;
    private $extractPath;

    public function __construct()
    {
        $this->tempZipPath = storage_path('app/temp_update.zip');
        $this->extractPath = storage_path('app/temp_update_extracted');
    }

    /**
     * Cek versi terbaru dari GitHub.
     */
    public function checkLatestUpdate($repo, $branch = 'main')
    {
        $timestamp = now()->timestamp;
        $response = Http::withHeaders([
            'User-Agent' => 'ERP-Update-System',
            'Cache-Control' => 'no-cache',
        ])->get("https://api.github.com/repos/{$repo}/commits/{$branch}?t={$timestamp}");

        if ($response->successful()) {
            $data = $response->json();
            $latestSha = $data['sha'] ?? null;
            
            // Dapatkan SHA terpasang saat ini di aplikasi
            $localSha = \App\Models\Setting::where('key', 'system_current_sha')->value('value');
            if (!$localSha && file_exists(base_path('.git'))) {
                @exec('git rev-parse HEAD', $gitOutput);
                $localSha = $gitOutput[0] ?? null;
            }

            $isUpToDate = !empty($localSha) && !empty($latestSha) && (substr($localSha, 0, 7) === substr($latestSha, 0, 7));

            return [
                'status' => 'success',
                'commit_sha' => $latestSha,
                'local_sha' => $localSha,
                'is_up_to_date' => $isUpToDate,
                'commit_message' => $data['commit']['message'] ?? '',
                'date' => $data['commit']['author']['date'] ?? '',
                'url' => $data['html_url'] ?? '',
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Gagal terhubung ke GitHub API. Pastikan repository publik dan nama valid (User/Repo).',
        ];
    }

    /**
     * Tahap 1: Unduh file ZIP dari GitHub
     */
    public function downloadUpdate($repo, $branch = 'main')
    {
        $zipUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        
        $response = Http::withHeaders([
            'User-Agent' => 'ERP-Update-System',
        ])->timeout(300)->get($zipUrl);

        if (!$response->successful()) {
            throw new Exception("Gagal mengunduh file update dari GitHub (HTTP " . $response->status() . ").");
        }

        File::put($this->tempZipPath, $response->body());
        return true;
    }

    /**
     * Tahap 2: Ekstrak dan timpa file kode
     */
    public function extractAndApplyUpdate()
    {
        if (!File::exists($this->tempZipPath)) {
            throw new Exception("File ZIP update tidak ditemukan.");
        }

        $zip = new ZipArchive();
        if ($zip->open($this->tempZipPath) !== true) {
            throw new Exception("Gagal membuka file ZIP pembaruan.");
        }

        if (File::exists($this->extractPath)) {
            File::deleteDirectory($this->extractPath);
        }
        
        $zip->extractTo($this->extractPath);
        $zip->close();

        $extractedFolders = File::directories($this->extractPath);
        if (count($extractedFolders) === 0) {
            throw new Exception("Struktur file ZIP tidak valid.");
        }

        $sourcePath = $extractedFolders[0];
        $this->copyDirectoryAndReplace($sourcePath, base_path());

        // Bersihkan direktori temporary
        File::deleteDirectory($this->extractPath);
        File::delete($this->tempZipPath);

        return true;
    }

    /**
     * Tahap 3: Migrasi database, bersihkan cache, dan simpan SHA commit aktif
     */
    public function finalizeUpdate($commitSha = null)
    {
        // Clear caches
        Artisan::call('optimize:clear');
        
        // Migrate database (force untuk production)
        Artisan::call('migrate', ['--force' => true]);

        // Simpan SHA commit terpasang ke tabel Settings
        if ($commitSha) {
            \App\Models\Setting::updateOrCreate(
                ['key' => 'system_current_sha'],
                ['value' => $commitSha]
            );
        }

        return true;
    }

    /**
     * Melakukan proses update sekaligus (Full)
     */
    public function performUpdate($repo, $branch = 'main')
    {
        try {
            $this->downloadUpdate($repo, $branch);
            $this->extractAndApplyUpdate();
            $this->finalizeUpdate();

            return ['status' => 'success', 'message' => 'Sistem berhasil diperbarui!'];
        } catch (Exception $e) {
            if (File::exists($this->tempZipPath)) File::delete($this->tempZipPath);
            if (File::exists($this->extractPath)) File::deleteDirectory($this->extractPath);
            
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Copy dan replace direktori secara rekursif
     */
    private function copyDirectoryAndReplace($source, $destination)
    {
        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $items = new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $target = $destination . '/' . $item->getBasename();

            if ($item->isDir()) {
                $this->copyDirectoryAndReplace($item->getPathname(), $target);
            } else {
                if (in_array($item->getBasename(), ['.env', '.env.example'])) {
                    continue;
                }
                File::copy($item->getPathname(), $target);
            }
        }
    }
}
