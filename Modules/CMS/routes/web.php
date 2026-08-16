<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('cms')->name('cms.')->group(function () {
        Volt::route('/posts', 'post-index')->name('posts.index')->middleware('permission:cms.posts.view');
        Volt::route('/posts/create', 'post-form')->name('posts.create')->middleware('permission:cms.posts.create');
        Volt::route('/posts/{post}/edit', 'post-form')->name('posts.edit')->middleware('permission:cms.posts.update');
    });

    // Public / Karyawan Reader UI
    Route::prefix('docs')->name('docs.')->group(function () {
        Volt::route('/', 'docs-index')->name('index')->middleware('permission:docs.view');
        Volt::route('/{post}', 'docs-show')->name('show')->middleware('permission:docs.view');
    });
});
