<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SystemCapacityWidget extends Component
{
    public $appSize = 0;
    public $databaseSize = 0;
    public $storageSize = 0;
    public $backupSize = 0;
    public $totalSize = 0;

    public function mount()
    {
        $this->calculateSizes();
    }

    public function calculateSizes()
    {
        // To avoid long load times if app has node_modules or vendor,
        // we might want to use a shell command or calculate specific folders instead of all files.
        // But for now, we can calculate 'app', 'public', 'resources', 'database', etc.
        // Let's use a shell command to get the base_path size on windows/linux for faster results.
        
        // Actually, getting all files in base_path() using PHP could be slow due to node_modules/vendor.
        // Let's just calculate the specific important folders: app, resources, public (excluding storage), vendor, etc.
        // Or we can rely on a simpler approach. Since it's Windows, `dir /s /a` or similar can be used.
        // To keep it simple and cross-platform:
        $this->appSize = $this->getDirectorySize(base_path('app')) 
                       + $this->getDirectorySize(base_path('resources'))
                       + $this->getDirectorySize(base_path('public'))
                       + $this->getDirectorySize(base_path('database'))
                       + $this->getDirectorySize(base_path('config'))
                       + $this->getDirectorySize(base_path('routes'));

        $this->databaseSize = $this->getDatabaseSize();
        $this->storageSize = $this->getDirectorySize(storage_path('app/public'));
        $this->backupSize = $this->getDirectorySize(storage_path('app/private/backups'));
        
        $this->totalSize = $this->appSize + $this->databaseSize + $this->storageSize + $this->backupSize;
    }

    private function getDirectorySize($directory)
    {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        try {
            $files = File::allFiles($directory);
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        } catch (\Exception $e) {
            // Ignore if directory cannot be read
        }
        
        return $size;
    }

    private function getDatabaseSize()
    {
        $dbPath = database_path('database.sqlite');
        if (file_exists($dbPath)) {
            return filesize($dbPath);
        }
        return 0;
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function render()
    {
        return view('livewire.system-capacity-widget');
    }
}
