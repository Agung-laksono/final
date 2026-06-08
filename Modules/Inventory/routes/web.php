<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // URL-nya langsung '/inventory', namanya langsung 'inventory'
    Volt::route('/inventory', 'index')->name('inventory');
    Volt::route('/inventory-settings', 'index')->name('inventory.settings');
    Volt::route('/inventory/warehouses', 'warehouse.index')->name('inventory.warehouses');
    Volt::route('/inventory-stock-opname', 'stock-opname.index')->name('inventory.stock-opname');
    Volt::route('/inventory/transfers', 'transfer.transfer-list')->name('inventory.transfers');
    Volt::route('/inventory/movements', 'movement.movement-list')->name('inventory.movements');
    
});
