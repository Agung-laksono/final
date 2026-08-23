<?php

use function Livewire\Volt\{state, layout, title, computed, rules, mount, on};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;
use Modules\Finance\Models\FinanceCategory;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesPayment;
use Modules\Purchase\Models\PurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

layout('layouts.app');
title('Dashboard Keuangan');

state([
    'filterYear' => date('Y'),
    'selectedAccountId' => null,
    'filterMonth' => date('m'),
    'searchQuery' => '',
    'filterType' => 'all', // all, income, expense
    'showTransactionModal' => false,
    'showCategoryModal' => false,
    'showAccountModal' => false,
]);

on(['transaction-saved' => function () {
    $this->showTransactionModal = false;
}]);

on(['open-category-modal' => function () {
    $this->showCategoryModal = true;
}]);

// Summary metrics
$totalBalance = computed(function () {
    return FinanceAccount::when(!auth()->user()->hasRole('Super Admin'), function($q) {
        $q->where('user_id', auth()->id());
    })->sum('current_balance');
});

$activeAccountsCount = computed(function () {
    return FinanceAccount::when(!auth()->user()->hasRole('Super Admin'), function($q) {
        $q->where('user_id', auth()->id());
    })->where('is_active', true)->count();
});

// Total Piutang Penjualan (Accounts Receivable - AR)
$totalAR = computed(function () {
    return SalesOrder::whereNotIn('status', ['draft', 'cancelled', 'rejected'])
        ->get()
        ->sum(fn($so) => max(0, floatval($so->grand_total ?? 0) - floatval($so->paid_amount ?? 0)));
});

// Total Hutang Pembelian (Accounts Payable - AP)
$totalAP = computed(function () {
    return PurchaseOrder::whereNotIn('status', ['draft', 'cancelled', 'rejected'])
        ->get()
        ->sum(fn($po) => max(0, floatval($po->grand_total ?? 0) - floatval($po->paid_amount ?? 0)));
});

// Inbox Verifikasi Pembayaran Pending
$pendingPaymentsCount = computed(function () {
    return SalesPayment::where('status', 'pending')->count();
});

$thisMonthIncome = computed(function () {
    return FinanceTransaction::where('type', 'income')
        ->whereMonth('transaction_date', $this->filterMonth)
        ->whereYear('transaction_date', $this->filterYear)
        ->sum('amount');
});

$thisMonthExpense = computed(function () {
    return FinanceTransaction::where('type', 'expense')
        ->whereMonth('transaction_date', $this->filterMonth)
        ->whereYear('transaction_date', $this->filterYear)
        ->sum('amount');
});

// Chart data: Monthly Cash Flow
$monthlyCashFlow = computed(function () {
    $transactions = FinanceTransaction::whereYear('transaction_date', $this->filterYear)
        ->select('transaction_date', 'type', 'amount')
        ->get();
        
    $labels = [];
    $incomeData = [];
    $expenseData = [];
    
    for ($i = 1; $i <= 12; $i++) {
        $labels[] = date('M', mktime(0, 0, 0, $i, 1));
        
        $incomeData[] = $transactions->filter(function($t) use ($i) {
            return $t->type === 'income' && (int)date('m', strtotime($t->transaction_date)) === $i;
        })->sum('amount');
        
        $expenseData[] = $transactions->filter(function($t) use ($i) {
            return $t->type === 'expense' && (int)date('m', strtotime($t->transaction_date)) === $i;
        })->sum('amount');
    }
    
    return [
        'labels' => $labels,
        'income' => $incomeData,
        'expense' => $expenseData
    ];
});

// Chart data: Top Expenses by Category (This Year)
$topExpenses = computed(function () {
    return FinanceTransaction::with('category')
        ->whereYear('transaction_date', $this->filterYear)
        ->where('type', 'expense')
        ->selectRaw('finance_category_id, SUM(amount) as total')
        ->groupBy('finance_category_id')
        ->orderByDesc('total')
        ->limit(5)
        ->get();
});

$topExpensesChart = computed(function () {
    $labels = [];
    $data = [];
    $colors = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#06b6d4'];
    
    foreach ($this->topExpenses as $expense) {
        $labels[] = $expense->category ? $expense->category->name : 'Tanpa Kategori';
        $data[] = $expense->total;
    }
    
    return [
        'labels' => $labels,
        'data' => $data,
        'colors' => array_slice($colors, 0, count($data))
    ];
});

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
        ->when($this->filterType !== 'all', fn($q) => $q->where('type', $this->filterType))
        ->when(!empty(trim($this->searchQuery)), function($q) {
            $term = '%' . trim($this->searchQuery) . '%';
            $q->where(function($sq) use ($term) {
                $sq->where('description', 'LIKE', $term)
                   ->orWhere('reference_id', 'LIKE', $term)
                   ->orWhere('reference_type', 'LIKE', $term);
            });
        })
        ->orderBy('transaction_date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();
});

$accountSummary = computed(function () {
    if (!$this->selectedAccountId) return null;
    
    $account = FinanceAccount::find($this->selectedAccountId);
    $initialBalance = $account ? floatval($account->initial_balance ?? 0) : 0;
    
    // Calculate balance of prior transactions before the selected month/year
    $priorBalance = FinanceTransaction::where('finance_account_id', $this->selectedAccountId)
        ->where(function($q) {
            $q->whereYear('transaction_date', '<', $this->filterYear)
              ->orWhere(function($sq) {
                  $sq->whereYear('transaction_date', $this->filterYear)
                     ->whereMonth('transaction_date', '<', $this->filterMonth);
              });
        })
        ->get()
        ->reduce(function ($carry, $item) {
            return $item->type === 'income' ? $carry + floatval($item->amount) : $carry - floatval($item->amount);
        }, 0);

    $startingBalance = $initialBalance + $priorBalance;
        
    $periodIncome = FinanceTransaction::where('finance_account_id', $this->selectedAccountId)
        ->whereYear('transaction_date', $this->filterYear)
        ->whereMonth('transaction_date', $this->filterMonth)
        ->where('type', 'income')
        ->sum('amount');

    $periodExpense = FinanceTransaction::where('finance_account_id', $this->selectedAccountId)
        ->whereYear('transaction_date', $this->filterYear)
        ->whereMonth('transaction_date', $this->filterMonth)
        ->where('type', 'expense')
        ->sum('amount');
    
    return [
        'account' => $account,
        'starting_balance' => $startingBalance,
        'period_income' => $periodIncome,
        'period_expense' => $periodExpense,
        'ending_balance' => $startingBalance + $periodIncome - $periodExpense,
    ];
});

mount(function () {
    if ($this->accounts->isNotEmpty() && !$this->selectedAccountId) {
        $this->selectedAccountId = $this->accounts->first()->id;
    }
});

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <flux:heading size="xl">Dashboard Keuangan & Buku Besar</flux:heading>
            <flux:subheading>Ringkasan arus kas, mutasi rekening, piutang, hutang, dan posisi keuangan perusahaan.</flux:subheading>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            @if($this->pendingPaymentsCount > 0)
                <a href="{{ route('finance.inbox') }}" wire:navigate class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-bold hover:bg-amber-500/20 transition-colors">
                    <flux:icon.inbox class="w-4 h-4" />
                    <span>Inbox Verifikasi</span>
                    <span class="bg-amber-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-black">{{ $this->pendingPaymentsCount }}</span>
                </a>
            @endif

            <flux:button wire:click="$set('showAccountModal', true)" icon="wallet" size="sm" class="hidden md:flex">Kas & Bank</flux:button>
            <flux:button wire:click="$set('showCategoryModal', true)" icon="tag" size="sm" class="hidden md:flex">Kategori</flux:button>
            <flux:button wire:click="$set('showTransactionModal', true)" variant="primary" icon="plus" size="sm">Catat Transaksi</flux:button>
        </div>
    </div>

    <!-- Financial Health Metrics (Stats Grid) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- 1. Total Saldo -->
        <div class="bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 rounded-2xl p-5 text-white shadow-lg relative overflow-hidden flex flex-col justify-between sm:col-span-2 lg:col-span-1">
            <div class="absolute -right-4 -bottom-4 opacity-15">
                <flux:icon.wallet class="w-28 h-28" />
            </div>
            <div class="relative z-10">
                <div class="text-indigo-100 font-medium text-xs mb-1 flex items-center gap-1.5">
                    <flux:icon.banknotes class="w-4 h-4" />
                    Total Saldo Kas & Bank
                </div>
                <div class="text-2xl font-black mb-1.5 tracking-tight">
                    Rp {{ number_format($this->totalBalance, 0, ',', '.') }}
                </div>
            </div>
            <div class="relative z-10 text-[11px] text-indigo-100 bg-white/10 backdrop-blur-sm inline-block px-2.5 py-1 rounded-lg w-max font-medium">
                {{ $this->activeAccountsCount }} Akun Aktif
            </div>
        </div>

        <!-- 2. Pemasukan Bulan Ini -->
        <div class="bg-white dark:bg-zinc-900 border border-emerald-200 dark:border-emerald-900/50 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 text-xs font-semibold mb-1 flex items-center gap-1.5">
                    <flux:icon.arrow-trending-up class="w-4 h-4 text-emerald-500" />
                    Pemasukan Bulan Ini
                </div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($this->thisMonthIncome, 0, ',', '.') }}
                </div>
            </div>
            <div class="text-[10px] text-zinc-400 mt-2">
                Periode {{ date('M Y', mktime(0, 0, 0, $this->filterMonth, 1, $this->filterYear)) }}
            </div>
        </div>

        <!-- 3. Pengeluaran Bulan Ini -->
        <div class="bg-white dark:bg-zinc-900 border border-rose-200 dark:border-rose-900/50 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 text-xs font-semibold mb-1 flex items-center gap-1.5">
                    <flux:icon.arrow-trending-down class="w-4 h-4 text-rose-500" />
                    Pengeluaran Bulan Ini
                </div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400">
                    Rp {{ number_format($this->thisMonthExpense, 0, ',', '.') }}
                </div>
            </div>
            <div class="text-[10px] text-zinc-400 mt-2">
                Periode {{ date('M Y', mktime(0, 0, 0, $this->filterMonth, 1, $this->filterYear)) }}
            </div>
        </div>
        
        <!-- 4. Piutang Penjualan (AR) -->
        <div class="bg-white dark:bg-zinc-900 border border-blue-200 dark:border-blue-900/50 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 text-xs font-semibold mb-1 flex items-center gap-1.5">
                    <flux:icon.arrow-down-left class="w-4 h-4 text-blue-500" />
                    Piutang Penjualan (AR)
                </div>
                <div class="text-xl font-extrabold text-blue-600 dark:text-blue-400">
                    Rp {{ number_format($this->totalAR, 0, ',', '.') }}
                </div>
            </div>
            <div class="text-[10px] text-zinc-400 mt-2">
                Tagihan SO Belum Lunas
            </div>
        </div>

        <!-- 5. Hutang Pembelian (AP) -->
        <div class="bg-white dark:bg-zinc-900 border border-amber-200 dark:border-amber-900/50 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <div>
                <div class="text-zinc-500 dark:text-zinc-400 text-xs font-semibold mb-1 flex items-center gap-1.5">
                    <flux:icon.arrow-up-right class="w-4 h-4 text-amber-500" />
                    Hutang Pembelian (AP)
                </div>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400">
                    Rp {{ number_format($this->totalAP, 0, ',', '.') }}
                </div>
            </div>
            <div class="text-[10px] text-zinc-400 mt-2">
                Kewajiban PO Belum Lunas
            </div>
        </div>
    </div>

    <!-- Visual Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Arus Kas Chart -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Arus Kas Bulanan</h3>
                    <p class="text-xs text-zinc-400">Perbandingan Pemasukan vs Pengeluaran Tahun {{ $this->filterYear }}</p>
                </div>
                <div class="w-32">
                    <flux:select wire:model.live="filterYear" size="sm">
                        @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                            <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="h-[280px] w-full" 
                 x-data="cashFlowChart(@js($this->monthlyCashFlow))" 
                 x-init="initChart()">
                <canvas id="cashFlowChartCanvas"></canvas>
            </div>
        </div>

        <!-- Top Expenses Doughnut -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-6 flex flex-col">
            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 mb-1">Pengeluaran Terbesar</h3>
            <p class="text-xs text-zinc-400 mb-4">5 Kategori Beban Tertinggi Tahun {{ $this->filterYear }}</p>

            @if(count($this->topExpensesChart['data']) > 0)
                <div class="flex-1 min-h-[180px]" 
                     x-data="expenseChart(@js($this->topExpensesChart))" 
                     x-init="initChart()">
                    <canvas id="expenseChartCanvas"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($this->topExpenses as $index => $expense)
                        <div class="flex justify-between items-center text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $this->topExpensesChart['colors'][$index] ?? '#ccc' }}"></div>
                                <span class="text-zinc-600 dark:text-zinc-400 truncate" title="{{ $expense->category ? $expense->category->name : 'Tanpa Kategori' }}">
                                    {{ $expense->category ? $expense->category->name : 'Tanpa Kategori' }}
                                </span>
                            </div>
                            <div class="font-bold text-zinc-900 dark:text-zinc-100 shrink-0">Rp {{ number_format($expense->total, 0, ',', '.') }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex-1 flex items-center justify-center text-zinc-400 text-xs italic">
                    Belum ada data pengeluaran.
                </div>
            @endif
        </div>
    </div>

    <!-- Buku Besar / Mutasi Rekening Section -->
    <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-800">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Buku Besar / Mutasi Rekening</h3>
                <p class="text-xs text-zinc-400">Riwayat rinci transaksi arus kas keluar & masuk per rekening.</p>
            </div>
            
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-zinc-200 dark:border-zinc-700 text-xs font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors print:hidden">
                <flux:icon.printer class="w-4 h-4 text-zinc-500" />
                <span>Cetak Mutasi</span>
            </button>
        </div>

        <!-- Filter Bar Buku Besar -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 items-end mb-6 print:hidden">
            <div>
                <flux:select wire:model.live="selectedAccountId" label="Pilih Akun Kas/Bank">
                    @foreach($this->accounts as $acc)
                        <flux:select.option value="{{ $acc->id }}">{{ $acc->name }} (Rp {{ number_format($acc->current_balance, 0, ',', '.') }})</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            
            <div>
                <flux:select wire:model.live="filterMonth" label="Bulan">
                    @foreach(range(1, 12) as $m)
                        <flux:select.option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            
            <div>
                <flux:select wire:model.live="filterYear" label="Tahun">
                    @foreach(range(date('Y') - 2, date('Y') + 1) as $y)
                        <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div>
                <flux:input wire:model.live.debounce.300ms="searchQuery" icon="magnifying-glass" placeholder="Cari deskripsi/ref..." label="Pencarian Mutasi" />
            </div>
        </div>

        @if($this->accountSummary)
        <!-- Summary Strip Buku Besar -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 shadow-sm">
                <div class="text-[11px] text-zinc-500 font-semibold mb-0.5">Saldo Awal Periode</div>
                <div class="text-base font-extrabold text-zinc-900 dark:text-zinc-100">Rp {{ number_format($this->accountSummary['starting_balance'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/50 rounded-xl p-3.5 shadow-sm">
                <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mb-0.5">Total Pemasukan</div>
                <div class="text-base font-extrabold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($this->accountSummary['period_income'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/50 rounded-xl p-3.5 shadow-sm">
                <div class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold mb-0.5">Total Pengeluaran</div>
                <div class="text-base font-extrabold text-rose-700 dark:text-rose-300">Rp {{ number_format($this->accountSummary['period_expense'], 0, ',', '.') }}</div>
            </div>
            <div class="bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/50 rounded-xl p-3.5 shadow-sm">
                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mb-0.5">Saldo Akhir Periode</div>
                <div class="text-base font-extrabold text-indigo-700 dark:text-indigo-300">Rp {{ number_format($this->accountSummary['ending_balance'], 0, ',', '.') }}</div>
            </div>
        </div>

        <!-- Tabel Buku Besar -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/80 text-zinc-500 uppercase text-[10px] font-bold tracking-wider">
                        <tr>
                            <th class="px-4 py-3 w-28">Tanggal</th>
                            <th class="px-4 py-3">Deskripsi Transaksi</th>
                            <th class="px-4 py-3 w-36">Kategori</th>
                            <th class="px-4 py-3 text-right w-36">Uang Masuk</th>
                            <th class="px-4 py-3 text-right w-36">Uang Keluar</th>
                            <th class="px-4 py-3 text-right w-40 bg-zinc-100/70 dark:bg-zinc-800">Saldo Berjalan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        <!-- Baris Saldo Awal -->
                        <tr class="bg-zinc-50/70 dark:bg-zinc-800/40 font-bold text-zinc-700 dark:text-zinc-300">
                            <td class="px-4 py-3" colspan="5">
                                Saldo Awal {{ date('M Y', mktime(0, 0, 0, $this->filterMonth, 1, $this->filterYear)) }} ({{ $this->accountSummary['account']->name ?? 'Kas/Bank' }})
                            </td>
                            <td class="px-4 py-3 text-right font-mono bg-zinc-100/50 dark:bg-zinc-800/50">
                                Rp {{ number_format($this->accountSummary['starting_balance'], 0, ',', '.') }}
                            </td>
                        </tr>
                        
                        @php $runningBalance = $this->accountSummary['starting_balance']; @endphp
                        
                        @forelse($this->transactions as $tx)
                            @php 
                                if($tx->type === 'income') {
                                    $runningBalance += floatval($tx->amount);
                                } else {
                                    $runningBalance -= floatval($tx->amount);
                                }
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap font-mono text-zinc-600 dark:text-zinc-400">
                                    {{ \Carbon\Carbon::parse($tx->transaction_date)->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $tx->description }}</div>
                                    @if($tx->reference_type)
                                        <div class="text-[10px] text-zinc-400 mt-0.5 flex items-center gap-1">
                                            <flux:icon.link class="w-3 h-3 inline"/>
                                            <span>{{ class_basename($tx->reference_type) }} #{{ $tx->reference_id }}</span>
                                        </div>
                                    @endif
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Oleh: {{ $tx->creator->name ?? 'Sistem' }}</div>
                                </td>
                                <td class="px-4 py-3 text-zinc-500">
                                    @if($tx->category)
                                        <flux:badge size="sm" color="zinc">{{ $tx->category->name }}</flux:badge>
                                    @else
                                        <span class="text-zinc-400 italic">Tanpa Kategori</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ $tx->type === 'income' ? 'Rp ' . number_format($tx->amount, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-rose-600 dark:text-rose-400">
                                    {{ $tx->type === 'expense' ? 'Rp ' . number_format($tx->amount, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-black text-zinc-900 dark:text-zinc-100 bg-zinc-50/50 dark:bg-zinc-800/40">
                                    Rp {{ number_format($runningBalance, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-zinc-400 text-xs italic">
                                    Tidak ada mutasi transaksi pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="text-center py-12 text-zinc-400 text-xs italic">
            Pilih akun kas/bank untuk melihat mutasi buku besar.
        </div>
        @endif
    </div>

    {{-- Modals --}}
    <flux:modal wire:model="showTransactionModal" class="w-full sm:w-[90vw] max-w-4xl">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold">Catat Transaksi</h2>
            </div>
            @if($showTransactionModal)
            <livewire:finance.transactions />
            @endif
        </div>
    </flux:modal>

    <flux:modal wire:model="showCategoryModal" class="w-full sm:w-[90vw] max-w-5xl">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold">Kelola Kategori</h2>
            </div>
            @if($showCategoryModal)
            <livewire:finance.categories />
            @endif
        </div>
    </flux:modal>

    <flux:modal wire:model="showAccountModal" class="w-full sm:w-[90vw] max-w-7xl">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold">Kelola Kas & Bank</h2>
            </div>
            @if($showAccountModal)
            <livewire:finance.accounts />
            @endif
        </div>
    </flux:modal>
</div>

@script
<script>
    Alpine.data('cashFlowChart', (chartData) => ({
        chart: null,
        initChart() {
            if (typeof Chart === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                script.onload = () => this.renderChart();
                document.head.appendChild(script);
            } else {
                this.renderChart();
            }
        },
        renderChart() {
            const ctx = document.getElementById('cashFlowChartCanvas');
            if (!ctx) return;
            
            if (this.chart) this.chart.destroy();
            
            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Pemasukan',
                            data: chartData.income,
                            backgroundColor: '#10b981',
                            borderRadius: 4,
                        },
                        {
                            label: 'Pengeluaran',
                            data: chartData.expense,
                            backgroundColor: '#f43f5e',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if(value >= 1000000) return 'Rp ' + (value / 1000000) + 'Jt';
                                    if(value >= 1000) return 'Rp ' + (value / 1000) + 'Rb';
                                    return 'Rp ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
    }));
    
    Alpine.data('expenseChart', (chartData) => ({
        chart: null,
        initChart() {
            if (typeof Chart === 'undefined') return;
            this.renderChart();
        },
        renderChart() {
            const ctx = document.getElementById('expenseChartCanvas');
            if (!ctx) return;
            
            if (this.chart) this.chart.destroy();
            
            this.chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.data,
                        backgroundColor: chartData.colors,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    }));
</script>
@endscript
