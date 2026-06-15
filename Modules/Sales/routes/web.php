<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('sales')->name('sales.')->group(function () {
        // Customer Routes
        Route::middleware(['permission:sales.customer.view'])->group(function () {
            Volt::route('customers', 'customer.customer-list')->name('customers.index');
        });
        
        // Order Routes (Kanban)
        Route::middleware(['permission:sales.order.view'])->group(function () {
            Volt::route('orders/kanban', 'sales-order.kanban')->name('orders.kanban');
        });
        
        // Order Create Route
        Route::middleware(['permission:sales.order.create'])->group(function () {
            Volt::route('orders/create', 'sales-order.create')->name('orders.create');
        });
    });
});
