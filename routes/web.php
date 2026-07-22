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
        'id'               => '/',
        'start_url'        => '/',
        'scope'            => '/',
        'display'          => 'standalone',
        'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
        'background_color' => $get('pwa_theme_color_light', '#ffffff'),
        'theme_color'      => $get('pwa_theme_color_light', '#ffffff'),
        'orientation'      => 'any',
        'icons'            => [
            ['src' => $iconUrl, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => $iconUrl, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/png'],
            ['src' => $iconUrl, 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'apple touch icon'],
        ],
    ];

    return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('notifications', 'notifications.index')->name('notifications.index');

    // Pusher Beams - generate auth token for authenticated user
    Route::get('/beams/auth', function () {
        $userId = auth()->id();
        $beams  = new \App\Services\BeamsService();
        $token  = $beams->generateToken($userId);
        return response()->json($token);
    })->name('beams.auth');
    // TEST ROUTE - hapus setelah selesai testing
    Route::get('/beams/test-all', function () {
        $beams = new \App\Services\BeamsService();
        $ok = $beams->sendToAll(
            '🔔 Test Broadcast',
            'Halo semua! Ini adalah test notifikasi ke seluruh staf.',
            [],
            '/dashboard'
        );
        return response()->json(['success' => $ok, 'target' => 'all-users']);
    })->name('beams.test.all');

    Route::get('/beams/test-me', function () {
        $beams = new \App\Services\BeamsService();
        $ok = $beams->sendToUser(
            auth()->id(),
            '👋 Test Personal',
            'Halo ' . auth()->user()->name . '! Ini notifikasi khusus untuk Anda.',
            [],
            '/dashboard'
        );
        return response()->json(['success' => $ok, 'target' => 'user-' . auth()->id()]);
    })->name('beams.test.me');
});
require __DIR__.'/settings.php';


