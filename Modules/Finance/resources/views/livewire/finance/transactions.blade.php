<?php

use function Livewire\Volt\{state, layout, title, computed, rules, updated};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCategory;
use Illuminate\Support\Facades\DB;


state([
    'accountId' => '',
    'transactionType' => 'expense',
    'categoryId' => '',
    'isAddingCategory' => false,
    'newCategoryName' => '',
    'amount' => '',
    'transactionDate' => date('Y-m-d'),
    'description' => '',
]);

rules([
    'accountId' => 'required|exists:finance_accounts,id',
    'transactionType' => 'required|in:income,expense',
    'amount' => 'required|numeric|min:1',
    'transactionDate' => 'required|date',
    'description' => 'required|string|max:255',
]);

$accounts = computed(function () {
    return FinanceAccount::where('is_active', true)->orderBy('name')->get();
});

$categories = computed(function () {
    return FinanceCategory::where('is_active', true)
        ->where('type', $this->transactionType)
        ->orderBy('name')
        ->get();
});

updated(['transactionType' => function () {
    $this->categoryId = '';
}]);



$saveTransaction = function () {
    $this->validate([
        'categoryId' => 'required|exists:finance_categories,id',
    ]);
    
    $this->validate();

    DB::transaction(function () {
        $amount = str_replace('.', '', $this->amount);
        
        $financeService = app(\Modules\Finance\Services\FinanceService::class);
        $financeService->recordTransaction(
            accountId: $this->accountId,
            type: $this->transactionType,
            amount: $amount,
            date: $this->transactionDate,
            description: $this->description,
            reference: null,
            categoryId: $this->categoryId,
            createdBy: auth()->id()
        );

        \Flux::toast('Transaksi berhasil dicatat.', variant: 'success');
        $this->reset(['amount', 'description', 'categoryId']);
        $this->transactionDate = date('Y-m-d');
        $this->dispatch('transaction-saved');
    });
};

?>

<div class="space-y-6">
    <form wire:submit="saveTransaction" class="overflow-hidden">
        <div class="space-y-6 pb-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Akun & Jenis Transaksi -->
                <div class="space-y-6">
                    <flux:select wire:model="accountId" label="Asal / Tujuan Dana (Akun Kas)" required>
                        <flux:select.option value="">Pilih Akun...</flux:select.option>
                        @foreach($this->accounts as $acc)
                            <flux:select.option value="{{ $acc->id }}">{{ $acc->name }} (Rp {{ number_format($acc->current_balance, 0, ',', '.') }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    
                    <flux:radio.group wire:model.live="transactionType" label="Jenis Transaksi" class="flex gap-4">
                        <flux:radio value="expense" label="Uang Keluar" />
                        <flux:radio value="income" label="Uang Masuk" />
                    </flux:radio.group>
                    
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:select wire:model="categoryId" label="Kategori" required wire:key="category-select-{{ $transactionType }}">
                                <flux:select.option value="">Pilih Kategori...</flux:select.option>
                                @foreach($this->categories as $cat)
                                    <flux:select.option value="{{ $cat->id }}">{{ $cat->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button icon="plus" variant="subtle" wire:click="$dispatch('open-category-modal')" title="Buka Kelola Kategori" />
                    </div>
                </div>
                
                <!-- Detail Nominal & Deskripsi -->
                <div class="space-y-6">
                    <flux:input type="date" wire:model="transactionDate" label="Tanggal Transaksi" required />
                    
                    <div x-data="{ 
                        val: @entangle('amount'),
                        format(v) { 
                            if (!v) return ''; 
                            return v.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); 
                        } 
                    }">
                        <flux:input type="text" 
                                    name="amount"
                                    label="Nominal (Rp)" 
                                    placeholder="Contoh: 1.500.000"
                                    required 
                                    x-bind:value="format(val)"
                                    x-on:input="val = $event.target.value.replace(/\D/g, '')" />
                    </div>
                    
                    <flux:textarea wire:model="description" label="Deskripsi Transaksi" placeholder="Misal: Pembayaran listrik bulan ini" required />
                </div>
            </div>
            
        </div>
        
        <div class="flex justify-end mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button type="submit" variant="primary" icon="check-circle">Simpan Transaksi</flux:button>
        </div>
    </form>
</div>
