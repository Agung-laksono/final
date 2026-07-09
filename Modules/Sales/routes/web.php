<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('sales')->name('sales.')->group(function () {
        // Customer Routes
        Route::middleware(['permission:sales.customer.view'])->group(function () {
            Volt::route('customers', 'customer.customer-list')->name('customers.index');
        });
        
        // Order Routes
        Route::middleware(['permission:sales.order.view'])->group(function () {
            Volt::route('orders', 'sales-order.index')->name('orders.index');
        });
        
        // Order Create Route
        Route::middleware(['permission:sales.order.create'])->group(function () {
            Volt::route('orders/create/{id?}', 'sales-order.create')->name('orders.create');
        });
    });
});
