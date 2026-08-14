<?php

use function Livewire\Volt\{state, layout, title, computed, rules};
layout('layouts.app');
title('Kas & Bank');
use Modules\Finance\Models\FinanceAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

state([
    'showModal' => false,
    'modalMode' => 'create', // 'create', 'edit'
    
    // Form fields
    'accountId' => null,
    'name' => '',
    'type' => 'bank',
    'account_number' => '',
    'account_holder_name' => '',
    'initial_balance' => 0,
    'user_id' => null, // PIC
    'is_active' => true,
]);

rules([
    'name' => 'required|string|max:255',
    'type' => 'required|in:cash,bank,ewallet',
    'account_number' => 'nullable|string|max:50',
    'account_holder_name' => 'nullable|string|max:255',
    'initial_balance' => 'required|numeric|min:0',
    'user_id' => 'nullable|exists:users,id',
    'is_active' => 'boolean',
]);

$accounts = computed(function () {
    return FinanceAccount::with('user')
        ->when(!auth()->user()->hasRole('Super Admin'), function($q) {
            $q->where('user_id', auth()->id());
        })
        ->orderBy('type')
        ->orderBy('name')
        ->get();
});

$users = computed(function () {
    return User::orderBy('name')->get();
});

$openCreateModal = function () {
    $this->reset(['accountId', 'name', 'type', 'account_number', 'account_holder_name', 'initial_balance', 'user_id']);
    $this->is_active = true;
    $this->modalMode = 'create';
    $this->showModal = true;
};

$openEditModal = function ($id) {
    $account = FinanceAccount::findOrFail($id);
    
    // Authorization check
    if (!auth()->user()->can('finance.accounts.update')) {
        \Flux::toast('Anda tidak memiliki akses.', variant: 'danger');
        return;
    }

    $this->accountId = $account->id;
    $this->name = $account->name;
    $this->type = $account->type;
    $this->account_number = $account->account_number;
    $this->account_holder_name = $account->account_holder_name;
    $this->initial_balance = $account->current_balance; // Not actually editable, just for display in edit mode
    $this->user_id = $account->user_id;
    $this->is_active = $account->is_active;
    
    $this->modalMode = 'edit';
    $this->showModal = true;
};

$saveAccount = function () {
    $this->validate();

    if ($this->modalMode === 'create' && !auth()->user()->can('finance.accounts.create')) {
        \Flux::toast('Anda tidak memiliki izin.', variant: 'danger');
        return;
    }
    if ($this->modalMode === 'edit' && !auth()->user()->can('finance.accounts.update')) {
        \Flux::toast('Anda tidak memiliki izin.', variant: 'danger');
        return;
    }

    try {
        DB::transaction(function () {
            if ($this->modalMode === 'create') {
                $account = FinanceAccount::create([
                    'name' => $this->name,
                    'type' => $this->type,
                    'account_number' => $this->account_number,
                    'account_holder_name' => $this->account_holder_name,
                    'current_balance' => $this->initial_balance, // Set initial balance
                    'user_id' => $this->user_id,
                    'is_active' => $this->is_active,
                ]);

                // Create initial balance transaction if > 0
                if ($this->initial_balance > 0) {
                    \Modules\Finance\Models\FinanceTransaction::create([
                        'finance_account_id' => $account->id,
                        'type' => 'income',
                        'amount' => $this->initial_balance,
                        'transaction_date' => now(),
                        'description' => 'Saldo Awal',
                        'created_by' => auth()->id(),
                    ]);
                }
                \Flux::toast('Akun berhasil dibuat.', variant: 'success');
            } else {
                $account = FinanceAccount::findOrFail($this->accountId);
                
                // Allow changing balance directly only if Super Admin (for correction purposes)
                $data = [
                    'name' => $this->name,
                    'type' => $this->type,
                    'account_number' => $this->account_number,
                    'account_holder_name' => $this->account_holder_name,
                    'user_id' => $this->user_id,
                    'is_active' => $this->is_active,
                ];

                if (auth()->user()->hasRole('Super Admin')) {
                    $difference = $this->initial_balance - $account->current_balance;
                    if ($difference != 0) {
                        // Create adjustment transaction
                        \Modules\Finance\Models\FinanceTransaction::create([
                            'finance_account_id' => $account->id,
                            'type' => $difference > 0 ? 'income' : 'expense',
                            'amount' => abs($difference),
                            'transaction_date' => now(),
                            'description' => 'Penyesuaian Saldo Manual oleh Super Admin',
                            'created_by' => auth()->id(),
                        ]);
                        $data['current_balance'] = $this->initial_balance;
                    }
                }

                $account->update($data);
                \Flux::toast('Akun berhasil diperbarui.', variant: 'success');
            }
        });
        
        $this->showModal = false;
    } catch (\Exception $e) {
        \Flux::toast('Gagal: ' . $e->getMessage(), variant: 'danger');
    }
};

$toggleStatus = function ($id) {
    if (!auth()->user()->can('finance.accounts.update')) return;
    
    $account = FinanceAccount::findOrFail($id);
    
    $account->update(['is_active' => !$account->is_active]);
    \Flux::toast('Status akun diubah.', variant: 'success');
};

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        @can('finance.accounts.create')
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Tambah Akun
        </flux:button>
        @endcan
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($this->accounts as $account)
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col group">
            <div class="p-5 flex-1 relative">
                <div class="absolute top-4 right-4">
                    @if($account->is_active)
                        <flux:badge color="emerald" size="sm">Aktif</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">Non-Aktif</flux:badge>
                    @endif
                </div>
                
                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4
                    {{ $account->type === 'bank' ? 'bg-blue-100 text-blue-600' : ($account->type === 'cash' ? 'bg-emerald-100 text-emerald-600' : 'bg-purple-100 text-purple-600') }}">
                    @if($account->type === 'bank')
                        <flux:icon.building-library class="w-6 h-6" />
                    @elseif($account->type === 'cash')
                        <flux:icon.banknotes class="w-6 h-6" />
                    @else
                        <flux:icon.device-phone-mobile class="w-6 h-6" />
                    @endif
                </div>
                
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white truncate" title="{{ $account->name }}">{{ $account->name }}</h3>
                <div class="text-sm text-zinc-500 font-mono mt-1 h-5">{{ $account->account_number ?? '-' }}</div>
                @if($account->account_holder_name)
                    <div class="text-xs text-zinc-500 mt-1 truncate">a/n: {{ $account->account_holder_name }}</div>
                @endif
                
                <div class="mt-6 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="text-xs text-zinc-500 mb-1 uppercase tracking-wider font-semibold">Saldo Saat Ini</div>
                    <div class="text-2xl font-black text-zinc-800 dark:text-zinc-100">
                        Rp {{ number_format($account->current_balance, 0, ',', '.') }}
                    </div>
                </div>
                
                @if($account->user)
                <div class="mt-4 flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <flux:icon.user class="w-4 h-4" />
                    <span>PIC: <strong>{{ $account->user->name }}</strong></span>
                </div>
                @endif
            </div>
            
            <!-- Actions -->
            <div class="px-5 py-3 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-100 dark:border-zinc-800 flex justify-end gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                @can('finance.accounts.update')
                <flux:button size="sm" variant="ghost" wire:click="toggleStatus({{ $account->id }})" title="{{ $account->is_active ? 'Non-aktifkan' : 'Aktifkan' }}">
                    <flux:icon.power class="w-4 h-4 {{ $account->is_active ? 'text-rose-500' : 'text-emerald-500' }}" />
                </flux:button>
                <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $account->id }})">
                    <flux:icon.pencil-square class="w-4 h-4" />
                </flux:button>
                @endcan
            </div>
        </div>
        @empty
        <div class="col-span-full py-12 text-center border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-2xl text-zinc-500">
            <flux:icon.wallet class="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>Belum ada akun kas atau bank yang terdaftar.</p>
        </div>
        @endforelse
    </div>

    <flux:modal wire:model="showModal" class="w-full sm:w-[90vw] max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $modalMode === 'create' ? 'Tambah Akun Baru' : 'Edit Akun' }}</flux:heading>
            </div>

            <form wire:submit="saveAccount" class="space-y-4">
                <flux:input wire:model="name" label="Nama Akun" placeholder="Misal: Bank BCA Utama, Petty Cash Gudang" required />
                
                <div class="grid grid-cols-1 gap-4">
                    <flux:select wire:model="type" label="Tipe Akun" required>
                        <flux:select.option value="bank">Bank</flux:select.option>
                        <flux:select.option value="cash">Uang Tunai (Cash)</flux:select.option>
                        <flux:select.option value="ewallet">e-Wallet / Digital</flux:select.option>
                    </flux:select>
                    
                    <flux:input wire:model="account_number" label="No. Rekening (opsional)" />
                    <flux:input wire:model="account_holder_name" label="Atas Nama (a/n) (opsional)" placeholder="Misal: JURAGAN GEBYOK / Sufiatin" />
                </div>
                
                <div class="space-y-2">
                    <flux:label>{{ $modalMode === 'create' ? 'Saldo Awal (Rp)' : 'Penyesuaian Saldo (Rp)' }}</flux:label>
                    <x-rupiah-input wire:model="initial_balance" align="left" :disabled="$modalMode === 'edit' && !auth()->user()->hasRole('Super Admin')" />
                </div>
                @if($modalMode === 'edit' && !auth()->user()->hasRole('Super Admin'))
                    <div class="text-xs text-rose-500 -mt-2">Hanya Super Admin yang dapat menyesuaikan saldo secara manual. Gunakan Mutasi untuk merubah saldo.</div>
                @endif
                
                <flux:select wire:model="user_id" label="PIC / Pemegang Akun (opsional)">
                    <flux:select.option value="">Semua Bisa Akses (Global)</flux:select.option>
                    @foreach($this->users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                
                <flux:switch wire:model="is_active" label="Akun Aktif" />
                
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)"> Batal </flux:button>
                    <flux:button icon="check" type="submit" variant="primary"> Simpan </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
