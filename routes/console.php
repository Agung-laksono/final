<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mendaftarkan jadwal backup otomatis hanya jika database sudah siap dan ada tabel settings
try {
    $isEnabled = Setting::where('key', 'backup_auto_enabled')->value('value') === 'true';
    if ($isEnabled) {
        $frequency = Setting::where('key', 'backup_auto_schedule')->value('value') ?? 'daily';
        $time = Setting::where('key', 'backup_auto_time')->value('value') ?? '23:59';
        
        $schedule = Schedule::command('app:auto-backup');
        
        if ($frequency === 'daily') {
            $schedule->dailyAt($time)->withoutOverlapping();
        } elseif ($frequency === 'weekly') {
            $schedule->weekly()->at($time)->withoutOverlapping();
        } elseif ($frequency === 'monthly') {
            $schedule->monthly()->at($time)->withoutOverlapping();
        }
    }
} catch (\Exception $e) {
    // Abaikan jika migrasi/database belum siap
}
