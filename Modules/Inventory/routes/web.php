<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // URL-nya langsung '/inventory', namanya langsung 'inventory'
    Volt::route('/inventory', 'dashboard.index')->name('inventory')->middleware('permission:inventory.view');
    Volt::route('/inventory/items', 'item-input.index')->name('inventory.items')->middleware('permission:inventory.item.view');

    Volt::route('/inventory/warehouses', 'warehouse.index')->name('inventory.warehouses')->middleware('permission:inventory.warehouse.view');
    Volt::route('/inventory-stock-opname', 'item-opname.index')->name('inventory.stock-opname')->middleware('permission:inventory.opname.view');
    Volt::route('/inventory/transfers', 'item-transfer.index')->name('inventory.transfers')->middleware('permission:inventory.transfer.view');
    Volt::route('/inventory/movements', 'item-history-movement.index')->name('inventory.movements')->middleware('permission:inventory.movement.view');
    
});
