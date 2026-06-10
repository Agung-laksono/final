<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', function () {
        if (auth()->user()->can('profile.view')) {
            return redirect()->route('profile.edit');
        }
        return redirect()->route('security.edit');
    })->name('settings.index');

    Route::middleware('can:profile.view')->group(function () {
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');

    Route::middleware(['can:users.view'])->group(function () {
        \Livewire\Volt\Volt::route('settings/users', 'settings.users')->name('settings.users');
    });

    Route::middleware(['role:Super Admin'])->group(function () {
        \Livewire\Volt\Volt::route('settings/integrations', 'settings.integrations')->name('settings.integrations');
    });
});
