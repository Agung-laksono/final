<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // URL-nya langsung '/inventory', namanya langsung 'inventory'
    Volt::route('/inventory', 'index')->name('inventory');
    
});
