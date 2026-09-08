<?php

use function Livewire\Volt\{state, layout, title, computed, rules, updated};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceCategory;
use Modules\Finance\Models\FinanceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

state([
    'accountId' => '',
    'transactionType' => 'expense',
    'categoryId' => '',
    'isAddingCategory' => false,
    'newCategoryName' => '',
    'amount' => '',
    'transactionDate' => date('Y-m-d'),
    'description' => '',
    // Enterprise fields
    'contact_name' => '',
    'transaction_number' => '',
    'proof_image' => null, // base64 dari x-image-cropper
]);

rules([
    'accountId' => 'required|exists:finance_accounts,id',
    'transactionType' => 'required|in:income,expense',
    'amount' => 'required|numeric|min:1',
    'transactionDate' => 'required|date',
    'description' => 'required|string|max:255',
    'contact_name' => 'nullable|string|max:255',
    'transaction_number' => 'nullable|string|max:255',
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
        $transaction = $financeService->recordTransaction(
            accountId: $this->accountId,
            type: $this->transactionType,
            amount: $amount,
            date: $this->transactionDate,
            description: $this->description,
            reference: null,
            categoryId: $this->categoryId,
            createdBy: auth()->id()
        );

        // Simpan bukti dari base64 (x-image-cropper)
        $proofPath = null;
        if ($this->proof_image && str_starts_with($this->proof_image, 'data:image')) {
            $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $this->proof_image);
            $decoded = base64_decode($base64);
            $filename = 'finance_proofs/' . uniqid('proof_') . '.webp';
            Storage::disk('public')->put($filename, $decoded);
            $proofPath = $filename;
        }

        // Generate auto number if empty
        $txNumber = $this->transaction_number;
        if (empty($txNumber)) {
            $prefix = $this->transactionType === 'income' ? 'BKM-' : 'BKK-';
            $txNumber = $prefix . date('Ym', strtotime($this->transactionDate)) . '-' . str_pad($transaction->id, 4, '0', STR_PAD_LEFT);
        }

        $transaction->update([
            'transaction_number' => $txNumber,
            'contact_name' => $this->contact_name,
            'proof_path' => $proofPath
        ]);

        \Flux::toast('Transaksi berhasil dicatat.', variant: 'success');
        $this->reset(['amount', 'description', 'categoryId', 'contact_name', 'transaction_number', 'proof_image']);
        $this->dispatch('reset-cropper'); // Reset komponen image-cropper
        $this->transactionDate = date('Y-m-d');
        $this->dispatch('transaction-saved');
    });
};

?>

<div class="space-y-6">
    <form wire:submit="saveTransaction" class="overflow-hidden">
        <div class="space-y-6 pb-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Kolom Kiri: Info Finansial -->
                <div class="space-y-6 bg-zinc-50 dark:bg-zinc-800/50 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider mb-2">Info Finansial</h3>
                    
                    <flux:radio.group wire:model.live="transactionType" label="Jenis Transaksi" class="flex gap-4">
                        <flux:radio value="expense" label="Uang Keluar" />
                        <flux:radio value="income" label="Uang Masuk" />
                    </flux:radio.group>

                    <flux:select wire:model="accountId" label="Asal / Tujuan Dana (Akun Kas)" required>
                        <flux:select.option value="">Pilih Akun...</flux:select.option>
                        @foreach($this->accounts as $acc)
                            <flux:select.option value="{{ $acc->id }}">{{ $acc->name }} (Rp {{ number_format($acc->current_balance, 0, ',', '.') }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    
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
                </div>
                
                <!-- Kolom Kanan: Detail Enterprise -->
                <div class="space-y-6">
                    <h3 class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider mb-2">Detail Bukti & Referensi</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="date" wire:model="transactionDate" label="Tanggal Transaksi" required />
                        <flux:input wire:model="transaction_number" label="Nomor Bukti" placeholder="Kosongi untuk Auto" />
                    </div>

                    <flux:input wire:model="contact_name" label="Kontak / Pihak Terkait" placeholder="Nama Vendor / Karyawan / Pelanggan" icon="user" />
                    
                    <flux:textarea wire:model="description" label="Deskripsi Transaksi" placeholder="Misal: Pembayaran listrik bulan ini" required rows="3" />

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">Lampiran Bukti (Struk/Transfer)</label>
                        <x-image-cropper
                            id="finance-proof-cropper"
                            wire:model="proof_image"
                            label="Upload Bukti Transaksi"
                            accept="image/*"
                            mode="box"
                        />
                    </div>
                </div>
            </div>
            
        </div>
        
        <div class="flex justify-end mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-800">
            <flux:button type="submit" variant="primary" icon="check-circle" wire:loading.attr="disabled" wire:target="saveTransaction, proof_file">
                <span wire:loading.remove wire:target="saveTransaction">Simpan Transaksi Enterprise</span>
                <span wire:loading wire:target="saveTransaction">Menyimpan...</span>
            </flux:button>
        </div>
    </form>
</div>
