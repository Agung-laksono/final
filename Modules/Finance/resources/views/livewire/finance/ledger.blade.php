<?php

use function Livewire\Volt\{state, layout, title, computed, rules, mount};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;
use Modules\Finance\Models\FinanceCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

layout('layouts.app');
title('Buku Besar / Mutasi Rekening');

state([
    'selectedAccountId' => null,
    'filterMonth' => date('m'),
    'filterYear' => date('Y'),
]);

rules([
    'selectedAccountId' => 'required|exists:finance_accounts,id',
]);

$accounts = computed(function () {
    return FinanceAccount::when(!auth()->user()->hasRole('Super Admin'), function($q) {
        $q->where('user_id', auth()->id());
    })->orderBy('name')->get();
});



$transactions = computed(function () {
    if (!$this->selectedAccountId) return collect();
    
    return FinanceTransaction::with(['category', 'creator'])
        ->where('finance_account_id', $this->selectedAccountId)
        ->whereMonth('transaction_date', $this->filterMonth)
        ->whereYear('transaction_date', $this->filterYear)
        ->orderBy('transaction_date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();
});

// Calculate running balance for the view
$accountSummary = computed(function () {
    if (!$this->selectedAccountId) return null;
    
    $account = FinanceAccount::find($this->selectedAccountId);
    
    // Calculate balance before the selected month
    $startingBalance = FinanceTransaction::where('finance_account_id', $this->selectedAccountId)
        ->where(function($q) {
            $q->whereYear('transaction_date', '<', $this->filterYear)
              ->orWhere(function($sq) {
                  $sq->whereYear('transaction_date', $this->filterYear)
                     ->whereMonth('transaction_date', '<', $this->filterMonth);
              });
        })
        ->get()
        ->reduce(function ($carry, $item) {
            return $item->type === 'income' ? $carry + $item->amount : $carry - $item->amount;
        }, 0); // Assuming initial account creation creates an income transaction
        
    $periodIncome = $this->transactions->where('type', 'income')->sum('amount');
    $periodExpense = $this->transactions->where('type', 'expense')->sum('amount');
    
    return [
        'account' => $account,
        'starting_balance' => $startingBalance,
        'period_income' => $periodIncome,
        'period_expense' => $periodExpense,
        'ending_balance' => $startingBalance + $periodIncome - $periodExpense,
    ];
});



// Auto-select first account if not selected
mount(function () {
    if ($this->accounts->isNotEmpty() && !$this->selectedAccountId) {
        $this->selectedAccountId = $this->accounts->first()->id;
    }
});

?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <flux:heading size="xl">Buku Besar / Riwayat Mutasi</flux:heading>
            <flux:subheading>Pantau arus kas masuk dan keluar secara mendetail.</flux:subheading>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm flex flex-col md:flex-row gap-4 items-end">
        <div class="w-full md:w-1/3">
            <flux:select wire:model.live="selectedAccountId" label="Pilih Akun Kas/Bank">
                @foreach($this->accounts as $acc)
                    <flux:select.option value="{{ $acc->id }}">{{ $acc->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        
        <div class="w-full md:w-1/4">
            <flux:select wire:model.live="filterMonth" label="Bulan">
                @foreach(range(1, 12) as $m)
                    <flux:select.option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        
        <div class="w-full md:w-1/4">
            <flux:select wire:model.live="filterYear" label="Tahun">
                @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                    <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if($this->accountSummary)
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm">
            <div class="text-sm text-zinc-500 font-medium mb-1">Saldo Awal Periode</div>
            <div class="text-xl font-bold text-zinc-800 dark:text-zinc-100">Rp {{ number_format($this->accountSummary['starting_balance'], 0, ',', '.') }}</div>
        </div>
        <div class="bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-900 rounded-xl p-4 shadow-sm">
            <div class="text-sm text-emerald-600 dark:text-emerald-400 font-medium mb-1">Total Pemasukan</div>
            <div class="text-xl font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($this->accountSummary['period_income'], 0, ',', '.') }}</div>
        </div>
        <div class="bg-rose-50 dark:bg-rose-900/10 border border-rose-200 dark:border-rose-900 rounded-xl p-4 shadow-sm">
            <div class="text-sm text-rose-600 dark:text-rose-400 font-medium mb-1">Total Pengeluaran</div>
            <div class="text-xl font-bold text-rose-700 dark:text-rose-300">Rp {{ number_format($this->accountSummary['period_expense'], 0, ',', '.') }}</div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-900 rounded-xl p-4 shadow-sm">
            <div class="text-sm text-blue-600 dark:text-blue-400 font-medium mb-1">Saldo Akhir Periode</div>
            <div class="text-xl font-bold text-blue-700 dark:text-blue-300">Rp {{ number_format($this->accountSummary['ending_balance'], 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Tabel Buku Besar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-6 py-3 w-32">Tanggal</th>
                        <th class="px-6 py-3">Deskripsi</th>
                        <th class="px-6 py-3 w-48">Kategori</th>
                        <th class="px-6 py-3 text-right w-40">Uang Masuk</th>
                        <th class="px-6 py-3 text-right w-40">Uang Keluar</th>
                        <th class="px-6 py-3 text-right w-48 bg-zinc-100 dark:bg-zinc-700">Saldo Berjalan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <!-- Baris Saldo Awal -->
                    <tr class="bg-zinc-50/50 dark:bg-zinc-800/30 font-medium">
                        <td class="px-6 py-4" colspan="5">Saldo Awal Bulan {{ date('M Y', mktime(0, 0, 0, $this->filterMonth, 1, $this->filterYear)) }}</td>
                        <td class="px-6 py-4 text-right bg-zinc-50 dark:bg-zinc-800/50">Rp {{ number_format($this->accountSummary['starting_balance'], 0, ',', '.') }}</td>
                    </tr>
                    
                    @php $runningBalance = $this->accountSummary['starting_balance']; @endphp
                    
                    @forelse($this->transactions as $tx)
                        @php 
                            if($tx->type === 'income') {
                                $runningBalance += $tx->amount;
                            } else {
                                $runningBalance -= $tx->amount;
                            }
                        @endphp
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <td class="px-6 py-3">{{ \Carbon\Carbon::parse($tx->transaction_date)->format('d M Y') }}</td>
                            <td class="px-6 py-3">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $tx->description }}</div>
                                @if($tx->reference_type)
                                    <div class="text-xs text-zinc-400 mt-0.5"><flux:icon.link class="w-3 h-3 inline mr-1"/>{{ class_basename($tx->reference_type) }} #{{ $tx->reference_id }}</div>
                                @endif
                                <div class="text-xs text-zinc-400 mt-0.5">Oleh: {{ $tx->creator->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="px-6 py-3 text-zinc-500">
                                @if($tx->category)
                                    <flux:badge size="sm" color="zinc">{{ $tx->category->name }}</flux:badge>
                                @else
                                    <span class="text-zinc-400 italic">Tanpa Kategori</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                {{ $tx->type === 'income' ? 'Rp ' . number_format($tx->amount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-6 py-3 text-right font-medium text-rose-600 dark:text-rose-400">
                                {{ $tx->type === 'expense' ? 'Rp ' . number_format($tx->amount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-6 py-3 text-right font-bold text-zinc-900 dark:text-zinc-100 bg-zinc-50 dark:bg-zinc-800/50">
                                Rp {{ number_format($runningBalance, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-zinc-500">
                                Tidak ada transaksi pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="text-center py-12 text-zinc-500">
        Pilih akun kas/bank untuk melihat riwayat transaksi.
    </div>
    @endif

</div>
