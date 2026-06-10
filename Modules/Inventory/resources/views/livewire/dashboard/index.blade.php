<?php

use function Livewire\Volt\{state, mount, with};
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

with(function () {
    // 1. KPI Metrics
    $totalItems = Item::where('is_active', true)->count();
    
    $totalAssetValue = DB::table('items')
        ->join('item_warehouse', 'items.id', '=', 'item_warehouse.item_id')
        ->where('items.is_active', true)
        ->sum(DB::raw('items.purchase_price * item_warehouse.stock'));

    // Asumsi tabel stock_transfers punya kolom status. Jika tidak, gunakan count() biasa.
    // Mengecek apakah kolom status ada di schema
    $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn('stock_transfers', 'status');
    $pendingTransfers = $hasStatus 
        ? StockTransfer::where('status', 'pending')->count() 
        : StockTransfer::count(); // Fallback jika tidak ada kolom status

    $todaysMovements = StockMovement::whereDate('created_at', today())
        ->select(DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_in'),
                 DB::raw('SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_out'))
        ->first();

    // 2. Low Stock Alerts
    $lowStockItems = DB::table('items')
        ->join('item_warehouse', 'items.id', '=', 'item_warehouse.item_id')
        ->where('items.is_active', true)
        ->where('items.min_stock', '>', 0)
        ->select('items.id', 'items.name', 'items.code', 'items.min_stock', DB::raw('SUM(item_warehouse.stock) as total_stock'))
        ->groupBy('items.id', 'items.name', 'items.code', 'items.min_stock')
        ->havingRaw('SUM(item_warehouse.stock) <= items.min_stock') // Fix having raw
        ->take(5)
        ->get();

    // 3. Recent Activities
    $recentActivities = StockMovement::with(['item', 'warehouse', 'user'])
        ->latest()
        ->take(8)
        ->get();

    // 4. Chart Data (Last 7 Days)
    $categories = [];
    $dataIn = [];
    $dataOut = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i)->format('Y-m-d');
        $label = now()->subDays($i)->format('d M');
        
        $in = StockMovement::whereDate('created_at', $date)->where('quantity', '>', 0)->sum('quantity');
        $out = StockMovement::whereDate('created_at', $date)->where('quantity', '<', 0)->sum('quantity');
        
        $categories[] = $label;
        $dataIn[] = (int) $in;
        $dataOut[] = (int) abs($out);
    }

    return compact('totalItems', 'totalAssetValue', 'pendingTransfers', 'todaysMovements', 'lowStockItems', 'recentActivities', 'categories', 'dataIn', 'dataOut');
});

// Aksi dummy untuk memaksa Livewire me-refresh komponen
$refreshDashboard = function () {
    // Tidak perlu melakukan apa-apa, sekadar memicu re-render
};
?>

<div wire:poll.5s="refreshDashboard" x-data="{ compactMode: localStorage.getItem('dashboardCompact') === 'true' }" 
     x-init="$watch('compactMode', val => localStorage.setItem('dashboardCompact', val))"
     :class="compactMode ? 'space-y-3' : 'space-y-6'">
    
    <div class="flex items-center justify-between" :class="compactMode ? 'mb-1' : ''">
        <div class="flex items-center gap-4">
            <flux:heading size="xl">Dashboard Inventory</flux:heading>
            
            {{-- Indikator Live Update --}}
            <div class="hidden sm:flex items-center gap-1.5 px-2 py-1 rounded-md border transition-colors"
                 :class="compactMode ? 'bg-zinc-50/50 dark:bg-zinc-800/20 border-zinc-100 dark:border-zinc-800' : 'bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700'">
                <div class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </div>
                <span class="font-mono text-zinc-500 dark:text-zinc-400" :class="compactMode ? 'text-[10px]' : 'text-xs'">
                    Update: {{ now()->format('H:i:s') }}
                </span>
            </div>
        </div>
        
        <button @click="compactMode = !compactMode" 
                class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors"
                :class="compactMode ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
            <flux:icon.arrows-pointing-in x-show="!compactMode" class="w-3.5 h-3.5" />
            <flux:icon.arrows-pointing-out x-show="compactMode" x-cloak class="w-3.5 h-3.5" />
            <span x-text="compactMode ? 'Mode Ringkas Aktif' : 'Aktifkan Mode Ringkas'"></span>
        </button>
    </div>

    {{-- 1. KPI CARDS (High Density) --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm overflow-hidden">
        <div class="grid grid-cols-2 tab-y:grid-cols-4  xl:grid-cols-8 divide-y tab-y:divide-y-0 tab-x:divide-x divide-zinc-200 dark:divide-zinc-800 gap-1">
            {{-- Total Items --}}
            <div class="p-3 flex items-center gap-3">
                <div class="rounded-md bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400 w-8 h-8 shrink-0">
                    <flux:icon.cube class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider truncate">Total Barang</p>
                    <p class="font-bold text-zinc-900 dark:text-white text-base leading-none mt-0.5">{{ number_format($totalItems) }}</p>
                </div>
            </div>

            {{-- Asset Value --}}
            <div class="p-3 flex items-center gap-3">
                <div class="rounded-md bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400 w-8 h-8 shrink-0">
                    <flux:icon.banknotes class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider truncate">Nilai Aset</p>
                    <p class="font-bold text-zinc-900 dark:text-white text-sm tab-x:text-base leading-none mt-0.5 truncate">Rp {{ number_format($totalAssetValue, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Pending Transfers --}}
            <div class="p-3 flex items-center gap-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" wire:click="redirect('{{ route('inventory.transfers') }}', true)">
                <div class="rounded-md bg-orange-50 dark:bg-orange-500/10 flex items-center justify-center text-orange-600 dark:text-orange-400 w-8 h-8 shrink-0">
                    <flux:icon.truck class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider truncate">Transfer Pending</p>
                    <p class="font-bold text-zinc-900 dark:text-white text-base leading-none mt-0.5">{{ number_format($pendingTransfers) }}</p>
                </div>
            </div>

            {{-- Today's Movement --}}
            <div class="p-3 flex items-center gap-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors" wire:click="redirect('{{ route('inventory.movements') }}', true)">
                <div class="rounded-md bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 w-8 h-8 shrink-0">
                    <flux:icon.arrows-right-left class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="font-medium text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider truncate">Mutasi Hari Ini</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="flex items-center font-bold text-emerald-600 dark:text-emerald-400 text-xs leading-none">
                            <flux:icon.arrow-down-right class="w-3 h-3 mr-0.5" /> {{ number_format($todaysMovements->total_in ?? 0) }}
                        </span>
                        <span class="flex items-center font-bold text-rose-600 dark:text-rose-400 text-xs leading-none">
                            <flux:icon.arrow-up-right class="w-3 h-3 mr-0.5" /> {{ number_format($todaysMovements->total_out ?? 0) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="grid grid-cols-1 tab-x:grid-cols-3" :class="compactMode ? 'gap-3' : 'gap-6'">
        
        {{-- Chart Section (Spans 2 columns on large screens) --}}
        <div class="tab-x:col-span-2" :class="compactMode ? 'space-y-2' : 'space-y-4'">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm overflow-hidden flex flex-col">
                <div class="px-4 py-2 border-b border-zinc-100 dark:border-zinc-800/50 bg-zinc-50/50 dark:bg-zinc-800/20">
                    <h3 class="text-[11px] font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Tren Pergerakan Stok (7 Hari)</h3>
                </div>
                
                <div class="p-2">
                    <div id="stockChart" class="w-full" :class="compactMode ? 'h-36' : 'h-48'"></div>
                </div>
            </div>

            {{-- Recent Activities --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-2 border-b border-zinc-100 dark:border-zinc-800/50 flex items-center justify-between bg-zinc-50/50 dark:bg-zinc-800/20">
                    <h3 class="text-[11px] font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Riwayat Transaksi Terbaru</h3>
                    <a href="{{ route('inventory.movements') }}" wire:navigate class="text-[10px] text-indigo-600 hover:text-indigo-700 font-medium">Lihat Semua</a>
                </div>
                
                <div class="overflow-x-auto custom-scrollbar" :class="compactMode ? 'max-h-[14rem] overflow-y-auto' : ''">
                    <table class="w-full text-left border-collapse">
                        <thead class="sticky top-0 bg-white dark:bg-zinc-900 z-10 shadow-sm">
                            <tr class="text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider">
                                <th class="px-4 py-1.5 font-semibold">Waktu</th>
                                <th class="px-4 py-1.5 font-semibold">Barang</th>
                                <th class="px-4 py-1.5 font-semibold">Gudang</th>
                                <th class="px-4 py-1.5 font-semibold text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                            @forelse($recentActivities as $activity)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-4 py-1.5 text-zinc-500 dark:text-zinc-400 text-[10px] whitespace-nowrap">{{ $activity->created_at->diffForHumans(null, true, true) }}</td>
                                    <td class="px-4 py-1.5 min-w-[150px]">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-200 text-xs truncate">{{ $activity->item?->name ?? 'Unknown' }}</div>
                                    </td>
                                    <td class="px-4 py-1.5 text-zinc-600 dark:text-zinc-300 text-[10px] whitespace-nowrap">{{ $activity->warehouse?->name ?? 'Utama' }}</td>
                                    <td class="px-4 py-1.5 text-right font-bold text-xs whitespace-nowrap" :class="['{{ $activity->quantity > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}']">
                                        {{ $activity->quantity > 0 ? '+' : '' }}{{ number_format($activity->quantity) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-zinc-500 text-xs">Belum ada riwayat transaksi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Sidebar Section (Spans 1 column) --}}
        <div :class="compactMode ? 'space-y-3' : 'space-y-6'">
            {{-- Low Stock Alerts --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-sm overflow-hidden flex flex-col">
                <div class="px-4 py-2 border-b border-rose-100 dark:border-rose-900/30 flex items-center gap-2 bg-rose-50/50 dark:bg-rose-900/10">
                    <flux:icon.exclamation-triangle class="w-4 h-4 text-rose-500" />
                    <h3 class="text-[11px] font-bold text-rose-700 dark:text-rose-400 uppercase tracking-wider">Peringatan Stok Menipis</h3>
                </div>

                <div class="overflow-x-auto custom-scrollbar" :class="compactMode ? 'max-h-[22rem] overflow-y-auto' : ''">
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800/50">
                        @forelse($lowStockItems as $lowItem)
                            <li class="px-4 py-2 flex items-center justify-between hover:bg-rose-50/30 dark:hover:bg-rose-900/10 transition-colors group">
                                <div class="min-w-0 pr-3">
                                    <h4 class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate">{{ $lowItem->name }}</h4>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[9px] font-mono text-zinc-500">{{ $lowItem->code }}</span>
                                        <span class="text-[9px] text-zinc-400">&bull;</span>
                                        <span class="text-[9px] text-zinc-500">Min: {{ $lowItem->min_stock }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end shrink-0 gap-1">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-500/20 text-rose-700 dark:text-rose-300">
                                        Sisa: {{ $lowItem->total_stock }}
                                    </span>
                                    <a href="{{ route('inventory.items') }}?show_item={{ $lowItem->id }}" wire:navigate class="text-[9px] font-bold text-rose-500 hover:text-rose-600 dark:hover:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                        Tindak Lanjut &rarr;
                                    </a>
                                </div>
                            </li>
                        @empty
                            <li class="px-4 py-6 text-center flex flex-col items-center justify-center">
                                <div class="rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center w-8 h-8 mb-2">
                                    <flux:icon.check-circle class="h-4 w-4 text-emerald-500" />
                                </div>
                                <p class="font-medium text-zinc-600 dark:text-zinc-400 text-[10px]">Semua stok barang terpantau aman.</p>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

    </div>

    {{-- ApexCharts Injection via Script --}}
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            if (document.getElementById('stockChart') && typeof ApexCharts !== 'undefined') {
                var options = {
                    series: [{
                        name: 'Barang Masuk',
                        data: @json($dataIn)
                    }, {
                        name: 'Barang Keluar',
                        data: @json($dataOut)
                    }],
                    chart: {
                        type: 'area',
                        height: 300,
                        toolbar: { show: false },
                        fontFamily: 'inherit',
                        background: 'transparent'
                    },
                    colors: ['#10b981', '#f43f5e'], // Emerald for In, Rose for Out
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.4,
                            opacityTo: 0.05,
                            stops: [0, 90, 100]
                        }
                    },
                    xaxis: {
                        categories: @json($categories),
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: {
                            style: { colors: '#a1a1aa' } // zinc-400
                        }
                    },
                    yaxis: {
                        labels: {
                            style: { colors: '#a1a1aa' }
                        }
                    },
                    grid: {
                        borderColor: 'rgba(161, 161, 170, 0.1)', // zinc-400/10
                        strokeDashArray: 4,
                    },
                    theme: {
                        mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                    },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right'
                    }
                };

                var chart = new ApexCharts(document.querySelector("#stockChart"), options);
                chart.render();

                // Listen to Flux dark mode toggle if necessary
                window.addEventListener('theme-changed', (e) => {
                    chart.updateOptions({
                        theme: { mode: e.detail.dark ? 'dark' : 'light' }
                    });
                });
            }
        });
    </script>
    @endpush
</div>
