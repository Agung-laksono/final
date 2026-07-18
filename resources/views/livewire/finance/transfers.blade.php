<?php

use function Livewire\Volt\{state, layout, computed, title, rules};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransfer;

layout('layouts.app');
title('Mutasi & Transfer Internal');

state([
    'showTransferModal' => false,
    'showPreviewModal' => false,
    'previewImage' => '',
    
    // Form fields
    'from_account_id' => '',
    'to_account_id' => '',
    'amount' => '',
    'transfer_date' => date('Y-m-d'),
    'notes' => '',
    'proof' => null,
]);

rules([
    'from_account_id' => 'required|exists:finance_accounts,id',
    'to_account_id' => 'required|exists:finance_accounts,id|different:from_account_id',
    'amount' => 'required|numeric|min:1',
    'transfer_date' => 'required|date',
    'notes' => 'nullable|string|max:255',
]);

$accounts = computed(function () {
    return FinanceAccount::where('is_active', true)->get();
});

// Transfer yang menunggu konfirmasi pengguna saat ini (sebagai penerima)
$incomingTransfers = computed(function () {
    return FinanceTransfer::with(['fromAccount', 'toAccount', 'creator'])
        ->where('status', 'pending')
        ->whereHas('toAccount', function($q) {
            if (!auth()->user()->hasRole('Super Admin')) {
                $q->where('user_id', auth()->id());
            }
        })
        ->orderBy('created_at', 'desc')
        ->get();
});

// Transfer yang diajukan oleh pengguna saat ini atau dari akun miliknya (sebagai pengirim)
$outgoingTransfers = computed(function () {
    return FinanceTransfer::with(['fromAccount', 'toAccount', 'confirmator'])
        ->where('status', 'pending')
        ->whereHas('fromAccount', function($q) {
            if (!auth()->user()->hasRole('Super Admin')) {
                $q->where('user_id', auth()->id());
            }
        })
        ->orderBy('created_at', 'desc')
        ->get();
});

$recentTransfers = computed(function () {
    return FinanceTransfer::with(['fromAccount', 'toAccount', 'creator', 'confirmator'])
        ->where('status', '!=', 'pending')
        ->where(function($q) {
            if (!auth()->user()->hasRole('Super Admin')) {
                $q->whereHas('fromAccount', fn($sq) => $sq->where('user_id', auth()->id()))
                  ->orWhereHas('toAccount', fn($sq) => $sq->where('user_id', auth()->id()));
            }
        })
        ->orderBy('updated_at', 'desc')
        ->limit(10)
        ->get();
});

$submitTransfer = function () {
    $this->validate();
    
    $fromAccount = FinanceAccount::findOrFail($this->from_account_id);
    if (!auth()->user()->hasRole('Super Admin') && $fromAccount->user_id !== auth()->id()) {
        \Flux::toast('Anda tidak berhak menggunakan akun sumber ini.', variant: 'danger');
        return;
    }
    
    try {
        $proofPath = null;
        if ($this->proof) {
            $proofPath = $this->proof->store('finance/transfers', 'public');
        }

        app(\Modules\Finance\Services\FinanceService::class)->createInternalTransfer(
            fromAccountId: $this->from_account_id,
            toAccountId: $this->to_account_id,
            amount: $this->amount,
            date: $this->transfer_date,
            notes: $this->notes,
            proofPath: $proofPath,
            createdBy: auth()->id()
        );

        \Flux::toast('Instruksi transfer berhasil dibuat dan menunggu konfirmasi penerima.', variant: 'success');
        $this->reset(['showTransferModal', 'from_account_id', 'to_account_id', 'amount', 'notes', 'proof']);
    } catch (\Exception $e) {
        \Flux::toast('Gagal: ' . $e->getMessage(), variant: 'danger');
    }
};

$confirmTransfer = function ($transferId) {
    try {
        $transfer = FinanceTransfer::findOrFail($transferId);
        
        // Verifikasi kepemilikan akun penerima
        if (!auth()->user()->hasRole('Super Admin') && $transfer->toAccount->user_id !== auth()->id()) {
            \Flux::toast("Anda tidak memiliki hak untuk mengonfirmasi transfer ke akun ini.", variant: 'danger');
            return;
        }

        app(\Modules\Finance\Services\FinanceService::class)->confirmInternalTransfer($transfer, auth()->id());
        \Flux::toast('Transfer berhasil dikonfirmasi. Saldo telah diperbarui.', variant: 'success');
    } catch (\Exception $e) {
        \Flux::toast('Gagal: ' . $e->getMessage(), variant: 'danger');
    }
};

$rejectTransfer = function ($transferId) {
    try {
        $transfer = FinanceTransfer::findOrFail($transferId);
        
        if (!auth()->user()->hasRole('Super Admin') && $transfer->toAccount->user_id !== auth()->id()) {
            \Flux::toast("Anda tidak memiliki hak untuk menolak transfer ke akun ini.", variant: 'danger');
            return;
        }

        app(\Modules\Finance\Services\FinanceService::class)->rejectInternalTransfer($transfer, "Ditolak oleh penerima", auth()->id());
        \Flux::toast('Transfer ditolak.', variant: 'warning');
    } catch (\Exception $e) {
        \Flux::toast('Gagal: ' . $e->getMessage(), variant: 'danger');
    }
};

?>

<div class="space-y-6" wire:poll.3s>
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">Mutasi & Transfer Internal</flux:heading>
            <flux:subheading>Kelola perpindahan dana antar akun kas/bank secara aman.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="arrows-right-left" wire:click="$set('showTransferModal', true)"> 
            Buat Mutasi Baru
 </flux:button>
    </div>

    <!-- Menunggu Konfirmasi Saya (Incoming) -->
    @if($this->incomingTransfers->count() > 0)
    <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl border border-rose-200 dark:border-rose-800 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-rose-200 dark:bg-rose-800 flex items-center justify-center text-rose-700 dark:text-rose-300">
                <flux:icon.bell-alert class="w-5 h-5" />
            </div>
            <h3 class="text-lg font-bold text-rose-900 dark:text-rose-100">Menunggu Konfirmasi Anda</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($this->incomingTransfers as $transfer)
            <div class="bg-white dark:bg-zinc-800 border border-rose-100 dark:border-rose-900/50 rounded-lg p-4 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-rose-50 dark:bg-rose-900/30 -mr-8 -mt-8 rounded-full z-0"></div>
                <div class="relative z-10">
                    <div class="flex justify-between items-start mb-3">
                        <div class="text-sm font-medium text-zinc-500">Dari: {{ $transfer->fromAccount->name }}</div>
                        <div class="font-mono text-xs text-zinc-400">{{ $transfer->transfer_number }}</div>
                    </div>
                    <div class="text-2xl font-black text-zinc-800 dark:text-zinc-100 mb-3">
                        Rp {{ number_format($transfer->amount, 0, ',', '.') }}
                    </div>
                    @if($transfer->notes)
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 italic mb-4">"{{ $transfer->notes }}"</div>
                    @endif
                    
                    <div class="flex gap-2 mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-700">
                        <flux:button variant="danger" class="w-1/3" size="sm" wire:click="rejectTransfer({{ $transfer->id }})" wire:confirm="Tolak mutasi ini?">Tolak</flux:button>
                        <flux:button variant="primary" class="w-2/3" size="sm" wire:click="confirmTransfer({{ $transfer->id }})" icon="check">Terima Dana</flux:button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Sedang Diproses (Outgoing) -->
    @if($this->outgoingTransfers->count() > 0)
    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-6">
        <div class="flex items-center gap-3 mb-4">
            <flux:icon.clock class="w-5 h-5 text-amber-600" />
            <h3 class="text-lg font-bold text-amber-900 dark:text-amber-100">Mutasi Sedang Menunggu Konfirmasi Penerima</h3>
        </div>
        
        <div class="space-y-3">
            @foreach($this->outgoingTransfers as $transfer)
            <div class="flex items-center justify-between bg-white dark:bg-zinc-800 border border-amber-100 dark:border-amber-900/50 p-4 rounded-lg shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <flux:icon.arrow-right class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-sm font-bold">Ke: {{ $transfer->toAccount->name }}</div>
                        <div class="text-xs text-zinc-500">No: {{ $transfer->transfer_number }} &bull; Tgl: {{ $transfer->transfer_date->format('d M Y') }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-bold text-lg">Rp {{ number_format($transfer->amount, 0, ',', '.') }}</div>
                    <flux:badge color="amber" size="sm">Menunggu Penerima</flux:badge>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Riwayat Transaksi -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-800">
            <h3 class="text-lg font-bold">Riwayat Mutasi Terakhir</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-6 py-3">No. Mutasi</th>
                        <th class="px-6 py-3">Tanggal</th>
                        <th class="px-6 py-3">Dari Akun</th>
                        <th class="px-6 py-3">Ke Akun</th>
                        <th class="px-6 py-3 text-right">Nominal</th>
                        <th class="px-6 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->recentTransfers as $transfer)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-6 py-4 font-mono text-xs">{{ $transfer->transfer_number }}</td>
                        <td class="px-6 py-4">{{ $transfer->transfer_date->format('d M Y') }}</td>
                        <td class="px-6 py-4 font-medium">{{ $transfer->fromAccount->name ?? '-' }}</td>
                        <td class="px-6 py-4 font-medium">{{ $transfer->toAccount->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-right font-bold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($transfer->amount, 0, ',', '.') }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($transfer->status === 'completed')
                                <flux:badge color="emerald" size="sm">Selesai</flux:badge>
                            @else
                                <flux:badge color="rose" size="sm">Ditolak</flux:badge>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-zinc-500">Belum ada riwayat mutasi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Mutasi Baru -->
    <flux:modal wire:model="showTransferModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Mutasi Dana</flux:heading>
                <flux:subheading>Transfer saldo antar akun kas/bank. Dana tidak akan berpindah sampai penerima mengonfirmasi.</flux:subheading>
            </div>

            <form wire:submit="submitTransfer" class="space-y-4">
                <flux:select wire:model="from_account_id" label="Dari Akun (Sumber Dana)">
                    <flux:select.option value="">Pilih Akun Sumber...</flux:select.option>
                    @foreach($this->accounts as $account)
                        <!-- Hanya tampilkan akun milik user saat ini, kecuali Super Admin -->
                        @if(auth()->user()->hasRole('Super Admin') || $account->user_id === auth()->id())
                            <flux:select.option value="{{ $account->id }}">{{ $account->name }} (Saldo: Rp {{ number_format($account->current_balance, 0, ',', '.') }})</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>

                <flux:select wire:model="to_account_id" label="Ke Akun (Tujuan)">
                    <flux:select.option value="">Pilih Akun Tujuan...</flux:select.option>
                    @foreach($this->accounts as $account)
                        <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div x-data="{ 
                    val: @entangle('amount'),
                    format(v) { 
                        if (!v) return ''; 
                        return v.toString().replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); 
                    } 
                }">
                    <flux:input type="text" 
                                label="Nominal Transfer (Rp)" 
                                placeholder="Contoh: 1.500.000"
                                x-bind:value="format(val)"
                                x-on:input="val = $event.target.value.replace(/\D/g, '')" />
                </div>
                
                <flux:input type="date" wire:model="transfer_date" label="Tanggal Transfer" />
                
                <flux:textarea wire:model="notes" label="Catatan / Keterangan" placeholder="Contoh: Pengisian petty cash minggu ke-2" rows="2" />
                
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700 flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showTransferModal', false)"> Batal </flux:button>
                    <flux:button type="submit" variant="primary">Ajukan Mutasi</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
