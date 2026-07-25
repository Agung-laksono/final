<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    \Livewire\Volt\Volt::route('purchases', 'dashboard')->name('purchase.index')->middleware('permission:purchase.dashboard.view');
    
    // Rute Volt untuk Vendor
    \Livewire\Volt\Volt::route('purchase/vendors', 'vendor.index')->name('purchase.vendors.index')->middleware('permission:purchase.vendor.view');
    
    // Rute Volt untuk Purchase Order
    \Livewire\Volt\Volt::route('purchase/orders/create', 'purchase.purchase-form')
        ->name('purchase.orders.create')
        ->middleware('permission:purchase.order.create');
        
    \Livewire\Volt\Volt::route('purchase/orders/{id}/edit', 'purchase.purchase-form')
        ->name('purchase.orders.edit')
        ->middleware('permission:purchase.order.update');
    
    // Rute Volt untuk Dual Kanban Board
    \Livewire\Volt\Volt::route('purchase/queues', 'queue.kanban')->name('purchase.queues.kanban')->middleware('permission:purchase.queue.view');
    \Livewire\Volt\Volt::route('purchase/orders', 'order.kanban')->name('purchase.orders.kanban')->middleware('permission:purchase.order.view');
    
    // Rute Volt untuk Retur Pembelian
    \Livewire\Volt\Volt::route('purchase/returns', 'returns.index')->name('purchase.returns.index')->middleware('permission:purchase.return.view');
    \Livewire\Volt\Volt::route('purchase/returns/create', 'returns.form')->name('purchase.returns.create')->middleware('permission:purchase.return.create');
    \Livewire\Volt\Volt::route('purchase/returns/{id}', 'returns.form')->name('purchase.returns.show')->middleware('permission:purchase.return.create');
});
