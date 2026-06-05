<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\InventoryController;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])
    ->prefix('inventory') // <-- Ini yang membuat URL-nya menjadi domain.com/inventory
    ->name('inventory.')  // <-- Ini penamaan otomatis untuk route (misal: route('inventory.index'))
    ->group(function () {
        
        // 1. Route untuk Halaman Utama Inventory (domain.com/inventory)
        // Pastikan Anda sudah membuat file 'index.blade.php' di Modules/Inventory/resources/views/livewire/
        Volt::route('/', 'index')->name('index');
        // (Opsional) Jika nanti Anda membuat halaman daftar barang (domain.com/inventory/barang)
        // Volt::route('/barang', 'barang-list')->name('barang');
        
    });