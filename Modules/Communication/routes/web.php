<?php

use Illuminate\Support\Facades\Route;
use Modules\Communication\Http\Controllers\CommunicationController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', \App\Livewire\Communication\ChatApp::class)->name('chat.index');
});
