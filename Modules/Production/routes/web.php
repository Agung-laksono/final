<?php

use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/production/orders', 'work-order.kanban')->name('production.orders')->middleware('permission:production.order.view');
    Volt::route('/production/recipes', 'recipe.index')->name('production.recipes')->middleware('permission:production.order.view');
});
