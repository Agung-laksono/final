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

    // Mendaftarkan jadwal Laporan Eksekutif WA Otomatis
    $waReportEnabled = Setting::where('key', 'wa_report_enabled')->value('value') === 'true';
    if ($waReportEnabled) {
        $frequencyWa = Setting::where('key', 'wa_report_frequency')->value('value') ?? 'daily';
        $timeWa = Setting::where('key', 'wa_report_time')->value('value') ?? '23:59';
        
        $scheduleWa = Schedule::command('app:send-executive-report');
        
        if ($frequencyWa === 'daily') {
            $scheduleWa->dailyAt($timeWa)->timezone('Asia/Jakarta')->withoutOverlapping();
        } elseif ($frequencyWa === 'weekly') {
            $scheduleWa->weekly()->at($timeWa)->timezone('Asia/Jakarta')->withoutOverlapping();
        } elseif ($frequencyWa === 'monthly') {
            $scheduleWa->monthly()->at($timeWa)->timezone('Asia/Jakarta')->withoutOverlapping();
        }
    }
} catch (\Exception $e) {
    // Abaikan jika migrasi/database belum siap
}
