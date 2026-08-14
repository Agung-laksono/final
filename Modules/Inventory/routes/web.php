<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // URL-nya langsung '/inventory', namanya langsung 'inventory'
    Volt::route('/inventory', 'dashboard.index')->name('inventory')->middleware('permission:inventory.dashboard.view');
    Volt::route('/inventory/items', 'item-input.index')->name('inventory.items')->middleware('permission:inventory.item.view');

    Volt::route('/inventory/warehouses', 'warehouse.index')->name('inventory.warehouses')->middleware('permission:inventory.warehouse.view');
    Volt::route('/inventory-stock-opname', 'item-opname.index')->name('inventory.stock-opname')->middleware('permission:inventory.opname.view');
    Volt::route('/inventory/transfers', 'item-transfer.index')->name('inventory.transfers')->middleware('permission:inventory.transfer.view');
    Volt::route('/inventory/movements', 'item-history-movement.index')->name('inventory.movements')->middleware('permission:inventory.movement.view');
    Volt::route('/inventory/requests', 'request.kanban')->name('inventory.requests')->middleware('permission:inventory.request.view');
    Volt::route('/inventory/fulfillments', 'fulfillments')->name('inventory.fulfillments')->middleware('permission:inventory.production.fulfillment');
    Volt::route('/inventory/production-receipts', 'production-receipts.index')->name('inventory.production-receipts')->middleware('permission:inventory.receipt.view');
    Volt::route('/inventory/dispatch', 'dispatch.index')->name('inventory.dispatch')->middleware('permission:inventory.dispatch.view');
    Volt::route('/inventory/sales-deliveries', 'sales-deliveries.index')->name('inventory.sales-deliveries')->middleware('permission:inventory.sales.delivery');

    // Route khusus cetak murni tanpa layout dashboard
    Volt::route('/inventory/print-labels', 'print-labels')->name('inventory.print-labels')->middleware('permission:inventory.item.view');
    
});

// Public Route for Sales Catalog
Volt::route('/c/{hash}', 'catalog.show')->name('catalog.show');
