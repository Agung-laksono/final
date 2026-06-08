<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // URL-nya langsung '/inventory', namanya langsung 'inventory'
    Volt::route('/inventory', 'index')->name('inventory')->middleware('permission:inventory.item.view');
    Volt::route('/inventory-settings', 'index')->name('inventory.settings')->middleware('role:Super Admin');
    Volt::route('/inventory/warehouses', 'warehouse.index')->name('inventory.warehouses')->middleware('permission:inventory.warehouse.view');
    Volt::route('/inventory-stock-opname', 'stock-opname.index')->name('inventory.stock-opname')->middleware('permission:inventory.opname.view');
    Volt::route('/inventory/transfers', 'transfer.transfer-list')->name('inventory.transfers')->middleware('permission:inventory.transfer.view');
    Volt::route('/inventory/movements', 'movement.movement-list')->name('inventory.movements')->middleware('permission:inventory.movement.view');
    
});
