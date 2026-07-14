<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

// Dynamic PWA Manifest — reads name, icon, colors from settings table
Route::get('/manifest.json', function () {
    $get = fn($key, $default) => \App\Models\Setting::where('key', $key)->value('value') ?? $default;

    $iconPath = $get('pwa_icon', null);
    $iconUrl  = $iconPath ? asset('storage/' . $iconPath) : asset('apple-touch-icon.png');

    $manifest = [
        'name'             => $get('pwa_name', 'Inventory System'),
        'short_name'       => $get('pwa_short_name', 'Inventory'),
        'description'      => $get('pwa_description', 'Sistem Manajemen Inventaris'),
        'start_url'        => '/',
        'display'          => 'standalone',
        'background_color' => $get('pwa_theme_color_light', '#ffffff'),
        'theme_color'      => $get('pwa_theme_color_light', '#ffffff'),
        'orientation'      => 'portrait-primary',
        'icons'            => [
            ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => $iconUrl, 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'apple touch icon'],
        ],
    ];

    return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('notifications', 'notifications.index')->name('notifications.index');
});
require __DIR__.'/settings.php';


