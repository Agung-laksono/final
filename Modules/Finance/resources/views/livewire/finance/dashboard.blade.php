<?php

use function Livewire\Volt\{state, layout, title, computed, rules, mount, on};
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;
use Modules\Finance\Models\FinanceCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

layout('layouts.app');
title('Dashboard Keuangan');

state([
    'filterYear' => date('Y'),
    'selectedAccountId' => null,
    'filterMonth' => date('m'),
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
    return FinanceAccount::sum('current_balance');
});

$activeAccountsCount = computed(function () {
    return FinanceAccount::where('is_active', true)->count();
});

$thisMonthIncome = computed(function () {
    return FinanceTransaction::where('type', 'income')
        ->whereMonth('transaction_date', date('m'))
        ->whereYear('transaction_date', $this->filterYear)
        ->sum('amount');
});

$thisMonthExpense = computed(function () {
    return FinanceTransaction::where('type', 'expense')
        ->whereMonth('transaction_date', date('m'))
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
        ->orderBy('transaction_date', 'asc')
        ->orderBy('created_at', 'asc')
        ->get();
});

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

mount(function () {
    if ($this->accounts->isNotEmpty() && !$this->selectedAccountId) {
        $this->selectedAccountId = $this->accounts->first()->id;
    }
});

?>

<div class="space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <flux:heading size="xl">Dashboard Keuangan & Buku Besar</flux:heading>
            <flux:subheading>Ringkasan arus kas, mutasi rekening, dan posisi keuangan perusahaan.</flux:subheading>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button wire:click="$set('showAccountModal', true)" icon="wallet" size="sm" class="hidden md:flex">Kas & Bank</flux:button>
            <flux:button wire:click="$set('showCategoryModal', true)" icon="tag" size="sm" class="hidden md:flex">Kategori</flux:button>
            <flux:button wire:click="$set('showTransactionModal', true)" variant="primary" icon="plus" size="sm">Catat Transaksi</flux:button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Saldo -->
        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-md relative overflow-hidden">
            <div class="absolute -right-4 -bottom-4 opacity-20">
                <flux:icon.wallet class="w-32 h-32" />
            </div>
            <div class="relative z-10">
                <div class="text-blue-100 font-medium mb-1 flex items-center gap-2">
                    <flux:icon.banknotes class="w-5 h-5" />
                    Total Saldo (Semua Akun)
                </div>
                <div class="text-3xl font-black mb-2">
                    Rp {{ number_format($this->totalBalance, 0, ',', '.') }}
                </div>
                <div class="text-sm text-blue-100 bg-black/20 inline-block px-2 py-1 rounded-md">
                    Terdiri dari {{ $this->activeAccountsCount }} akun aktif
                </div>
            </div>
        </div>

        <!-- Pemasukan Bulan Ini -->
        <div class="bg-white dark:bg-zinc-900 border border-emerald-200 dark:border-emerald-900/50 rounded-2xl p-6 shadow-sm">
            <div class="text-zinc-500 dark:text-zinc-400 font-medium mb-1 flex items-center gap-2">
                <flux:icon.arrow-trending-up class="w-5 h-5 text-emerald-500" />
                Pemasukan (Bulan Ini)
            </div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                Rp {{ number_format($this->thisMonthIncome, 0, ',', '.') }}
            </div>
        </div>

        <!-- Pengeluaran Bulan Ini -->
        <div class="bg-white dark:bg-zinc-900 border border-rose-200 dark:border-rose-900/50 rounded-2xl p-6 shadow-sm">
            <div class="text-zinc-500 dark:text-zinc-400 font-medium mb-1 flex items-center gap-2">
                <flux:icon.arrow-trending-down class="w-5 h-5 text-rose-500" />
                Pengeluaran (Bulan Ini)
            </div>
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                Rp {{ number_format($this->thisMonthExpense, 0, ',', '.') }}
            </div>
        </div>
        
        <!-- Net Cash Flow -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
            <div class="text-zinc-500 dark:text-zinc-400 font-medium mb-1 flex items-center gap-2">
                <flux:icon.calculator class="w-5 h-5 text-zinc-500" />
                Net Cash Flow (Bulan Ini)
            </div>
            @php $net = $this->thisMonthIncome - $this->thisMonthExpense; @endphp
            <div class="text-2xl font-bold {{ $net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                Rp {{ number_format($net, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Arus Kas Chart -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-100 mb-6">Arus Kas Bulanan (Tahun {{ $this->filterYear }})</h3>
            <div class="h-[300px] w-full" 
                 x-data="cashFlowChart(@js($this->monthlyCashFlow))" 
                 x-init="initChart()">
                <canvas id="cashFlowChartCanvas"></canvas>
            </div>
        </div>

        <!-- Top Expenses -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm p-6 flex flex-col">
            <h3 class="text-lg font-bold text-zinc-800 dark:text-zinc-100 mb-6">Pengeluaran Terbesar</h3>
            @if(count($this->topExpensesChart['data']) > 0)
                <div class="flex-1 min-h-[200px]" 
                     x-data="expenseChart(@js($this->topExpensesChart))" 
                     x-init="initChart()">
                    <canvas id="expenseChartCanvas"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($this->topExpenses as $index => $expense)
                        <div class="flex justify-between items-center text-sm">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full" style="background-color: {{ $this->topExpensesChart['colors'][$index] ?? '#ccc' }}"></div>
                                <span class="text-zinc-600 dark:text-zinc-400 truncate w-32" title="{{ $expense->category ? $expense->category->name : 'Tanpa Kategori' }}">
                                    {{ $expense->category ? $expense->category->name : 'Tanpa Kategori' }}
                                </span>
                            </div>
                            <div class="font-bold">Rp {{ number_format($expense->total, 0, ',', '.') }}</div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex-1 flex items-center justify-center text-zinc-400 text-sm italic">
                    Belum ada data pengeluaran.
                </div>
            @endif
        </div>
    </div>
    <div class="mt-8 pt-8 border-t border-zinc-200 dark:border-zinc-800">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">Buku Besar / Mutasi Rekening</h3>
        </div>
        <!-- Filter Bar -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-sm flex flex-col md:flex-row gap-4 items-end mb-6">
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
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
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
                // In case Chart.js is not loaded, we can dynamically load it
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
            if (typeof Chart === 'undefined') return; // Handled by cashFlowChart loading script
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
