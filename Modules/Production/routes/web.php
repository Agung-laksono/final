<?php

use Livewire\Volt\Volt;
use Modules\Production\Http\Controllers\MaklonMediaController;

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/production/orders', 'work-order.kanban')->name('production.orders')->middleware('permission:production.order.view');
    Volt::route('/production/recipes', 'recipe.index')->name('production.recipes')->middleware('permission:production.order.view');

    Volt::route('/production/orders/maklon/create', 'work-order.create-maklon')->name('production.orders.maklon.create')->middleware('permission:production.order.create');

    // Maklon Media Library
    Route::post('/maklon/media/upload', [MaklonMediaController::class, 'upload'])->name('maklon.media.upload');
    Route::get('/maklon/media', [MaklonMediaController::class, 'list'])->name('maklon.media.list');
    Route::delete('/maklon/media', [MaklonMediaController::class, 'delete'])->name('maklon.media.delete');

    // Print SPK
    Route::get('/production/work-orders/{id}/print', [\Modules\Production\Http\Controllers\WorkOrderPrintController::class, 'show'])
        ->name('production.work-orders.print')
        ->middleware('permission:production.order.view');
});
