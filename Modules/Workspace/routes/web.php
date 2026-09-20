<?php

use Illuminate\Support\Facades\Route;
use Modules\Workspace\Livewire\BoardList;
use Livewire\Volt\Volt;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/workspaces', BoardList::class)->name('workspaces.index');
    // Using volt for the actual kanban board view
    Volt::route('/workspaces/{workspace}', 'workspace.board-kanban')->name('workspaces.show');
});
