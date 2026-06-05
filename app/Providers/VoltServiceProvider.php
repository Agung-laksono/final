<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;
use Nwidart\Modules\Facades\Module;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Volt::mount([
            config('livewire.view_path', resource_path('views/livewire')),
            resource_path('views/pages'),
        ]);

        // Memberitahu Volt agar ikut memantau dan mencari file komponen di dalam folder setiap Modul
        // Looping semua modul yang statusnya aktif
        foreach (Module::allEnabled() as $module) {
            $livewirePath = module_path($module->getName(), 'resources/views/livewire');
            $pagesPath = module_path($module->getName(), 'resources/views/pages');
            // Kita cek dulu foldernya ada atau tidak agar tidak terjadi error
            $paths = [];
            if (is_dir($livewirePath)) {
                $paths[] = $livewirePath;
            }
            if (is_dir($pagesPath)) {
                $paths[] = $pagesPath;
            }
            // Jika foldernya ada, beritahu Volt untuk memantaunya
            if (!empty($paths)) {
                Volt::mount($paths);
            }
        }
    }
}
