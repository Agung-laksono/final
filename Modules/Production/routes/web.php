<?php

use Livewire\Volt\Volt;
use Modules\Production\Http\Controllers\MaklonMediaController;

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/production/orders', 'work-order.kanban')->name('production.orders')->middleware('permission:production.order.view');
    Volt::route('/production/recipes', 'recipe.index')->name('production.recipes')->middleware('permission:production.order.view');

    // Maklon Media Library
    Route::post('/maklon/media/upload', [MaklonMediaController::class, 'upload'])->name('maklon.media.upload');
    Route::get('/maklon/media', [MaklonMediaController::class, 'list'])->name('maklon.media.list');
    Route::delete('/maklon/media', [MaklonMediaController::class, 'delete'])->name('maklon.media.delete');
});
