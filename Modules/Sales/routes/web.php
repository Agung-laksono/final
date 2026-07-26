<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('sales')->name('sales.')->group(function () {
        // Customer Routes
        Route::middleware(['permission:sales.customer.view'])->group(function () {
            Volt::route('customers', 'customer.customer-list')->name('customers.index');
        });
        
        // Quotation Routes
        Route::middleware(['permission:sales.quotation.view'])->group(function () {
            Volt::route('quotations', 'quotation.index')->name('quotations.index');
            Volt::route('quotations/create', 'quotation.create')->name('quotations.create');
            Volt::route('quotations/{id}', 'quotation.show')->name('quotations.show');
            Route::get('quotations/{id}/print', [\Modules\Sales\Http\Controllers\QuotationPrintController::class, 'show'])->name('quotations.print');
        });
        
        // Order Routes
        Route::middleware(['permission:sales.order.view'])->group(function () {
            Volt::route('orders', 'sales-order.index')->name('orders.index');
        });
        
        // Order Create Route
        Route::middleware(['permission:sales.order.create'])->group(function () {
            Volt::route('orders/create/{id?}', 'sales-order.create')->name('orders.create');
        });
        
        // Invoice and Print Route
        Route::middleware(['permission:sales.order.view'])->group(function () {
            Route::get('orders/{id}/invoice', [\Modules\Sales\Http\Controllers\InvoiceController::class, 'show'])->name('orders.invoice');
            Route::get('orders/{id}/print', [\Modules\Sales\Http\Controllers\SalesOrderPrintController::class, 'show'])->name('orders.print');
        });
        
        // Return Routes
        Route::middleware(['permission:sales.return.view'])->group(function () {
            Volt::route('returns', 'returns.index')->name('returns.index');
        });
        Route::middleware(['permission:sales.return.create'])->group(function () {
            Volt::route('returns/create', 'returns.form')->name('returns.create');
            Volt::route('returns/{id}', 'returns.form')->name('returns.show');
        });
    });
});
