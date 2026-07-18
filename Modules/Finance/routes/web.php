<?php

use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->prefix('finance')->name('finance.')->group(function () {
    Volt::route('dashboard', 'finance.dashboard')->name('dashboard');
    Volt::route('inbox', 'finance.inbox')->name('inbox');
    Volt::route('accounts', 'finance.accounts')->name('accounts');
    Volt::route('categories', 'finance.categories')->name('categories');
    Volt::route('ledger', 'finance.ledger')->name('ledger');
    Volt::route('transactions', 'finance.transactions')->name('transactions');
    Volt::route('transfers', 'finance.transfers')->name('transfers');
    Volt::route('payables', 'finance.payables')->name('payables');
});
