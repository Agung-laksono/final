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
    /**
     * Cek versi terbaru dari GitHub.
     */
    public function checkLatestUpdate($repo, $branch = 'main')
    {
        // Menggunakan endpoint GitHub API untuk mendapatkan commit terakhir dari branch
        $response = Http::withHeaders([
            'User-Agent' => 'ERP-Update-System',
        ])->get("https://api.github.com/repos/{$repo}/commits/{$branch}");

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => 'success',
                'commit_sha' => $data['sha'] ?? null,
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
     * Melakukan proses unduh dan ekstraksi pembaruan.
     */
    public function performUpdate($repo, $branch = 'main')
    {
        $zipUrl = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        $tempZipPath = storage_path('app/temp_update.zip');
        $extractPath = storage_path('app/temp_update_extracted');

        try {
            // 1. Download ZIP
            $response = Http::withHeaders([
                'User-Agent' => 'ERP-Update-System',
            ])->timeout(300)->get($zipUrl);

            if (!$response->successful()) {
                throw new Exception("Gagal mengunduh file update dari GitHub.");
            }

            File::put($tempZipPath, $response->body());

            // 2. Ekstrak ZIP
            $zip = new ZipArchive();
            if ($zip->open($tempZipPath) === true) {
                // Hapus folder ekstrak lama jika ada
                if (File::exists($extractPath)) {
                    File::deleteDirectory($extractPath);
                }
                
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new Exception("Gagal mengekstrak file ZIP.");
            }

            // 3. Pindahkan file dari folder hasil ekstrak ke base_path()
            // GitHub mengekstrak ke dalam subfolder bernama repo-branch (misal: my-erp-main)
            $extractedFolders = File::directories($extractPath);
            if (count($extractedFolders) === 0) {
                throw new Exception("Struktur file ZIP tidak valid.");
            }

            $sourcePath = $extractedFolders[0]; // Folder root dari zip
            
            // Kita pindahkan isinya satu per satu untuk menimpa file lama
            $this->copyDirectoryAndReplace($sourcePath, base_path());

            // 4. Bersihkan file sementara
            File::deleteDirectory($extractPath);
            File::delete($tempZipPath);

            // 5. Jalankan Post-Update Scripts
            $this->runPostUpdateCommands();

            return ['status' => 'success', 'message' => 'Sistem berhasil diperbarui!'];
            
        } catch (Exception $e) {
            // Cleanup
            if (File::exists($tempZipPath)) File::delete($tempZipPath);
            if (File::exists($extractPath)) File::deleteDirectory($extractPath);
            
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
                // Abaikan file .env atau konfigurasi sensitif lainnya jika diperlukan
                // File yang sering tidak boleh ditimpa jika ada di repo (sebaiknya diabaikan)
                if (in_array($item->getBasename(), ['.env', '.env.example'])) {
                    continue;
                }
                File::copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Jalankan perintah paska-update
     */
    private function runPostUpdateCommands()
    {
        // Clear caches
        Artisan::call('optimize:clear');
        
        // Migrate database (force untuk bypass interaksi di production)
        Artisan::call('migrate', ['--force' => true]);
        
    }
}
