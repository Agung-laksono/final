<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\PurchaseController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('purchases', PurchaseController::class)->names('purchase');
    
    // Rute Volt untuk Vendor
    \Livewire\Volt\Volt::route('purchase/vendors', 'vendor.index')->name('purchase.vendors.index');
    
    // Rute Volt untuk Dual Kanban Board
    \Livewire\Volt\Volt::route('purchase/queues', 'queue.kanban')->name('purchase.queues.kanban');
    \Livewire\Volt\Volt::route('purchase/orders', 'order.kanban')->name('purchase.orders.kanban');
});
