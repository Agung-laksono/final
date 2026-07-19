<?php

use function Livewire\Volt\{state, mount, with, layout};
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

layout('layouts.app');

with(function () {
    // 1. KPI Metrics
    $totalItems = Item::where('is_active', true)->count();
    
    $assetValues = DB::table('items')
        ->join('item_warehouse', 'items.id', '=', 'item_warehouse.item_id')
        ->where('items.is_active', true)
        ->select('items.type_id', DB::raw('SUM(items.purchase_price * item_warehouse.stock) as total_value'))
        ->groupBy('items.type_id')
        ->get()
        ->keyBy('type_id');

    $valuationFinished = $assetValues[1]->total_value ?? 0;
    $valuationRaw = ($assetValues[2]->total_value ?? 0) + ($assetValues[3]->total_value ?? 0);
    $valuationWIP = $assetValues[7]->total_value ?? 0;
    $totalAssetValue = $assetValues->sum('total_value');

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

    // 5. Active Users
    $allUsers = User::all();
    $activeUsers = [];
    foreach ($allUsers as $user) {
        $key = 'user-is-online-' . $user->id;
        if (Cache::has($key)) {
            $activeUsers[] = Cache::get($key);
        }
    }
    usort($activeUsers, function ($a, $b) {
        return $b['last_seen'] <=> $a['last_seen'];
    });

    return compact('totalItems', 'totalAssetValue', 'valuationRaw', 'valuationWIP', 'valuationFinished', 'pendingTransfers', 'todaysMovements', 'lowStockItems', 'recentActivities', 'categories', 'dataIn', 'dataOut', 'activeUsers');
});

// Aksi dummy untuk memaksa Livewire me-refresh komponen
$refreshDashboard = function () {
    // Tidak perlu melakukan apa-apa, sekadar memicu re-render
};
?>

<div x-data="{ compactMode: false }" 
     class="flex flex-col transition-all duration-200 h-full w-full flex-1 rounded-xl" 
     :class="compactMode ? 'gap-2' : 'gap-4'">
    
    {{-- Top Header / Toolbar --}}
    <div class="flex items-center justify-between shrink-0">
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
        
        <div class="flex items-center gap-2">
            {{-- Tombol Mode Ringkas --}}
            <button @click="compactMode = !compactMode" 
                    class="flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-md transition-colors"
                    :class="compactMode ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800'">
                <flux:icon.bars-arrow-down x-show="!compactMode" class="w-3.5 h-3.5" />
                <flux:icon.bars-arrow-up x-show="compactMode" x-cloak class="w-3.5 h-3.5" />
                <span class="hidden sm:inline" x-text="compactMode ? 'Mode Ringkas Aktif' : 'Aktifkan Mode Ringkas'"></span>
            </button>
        </div>
    </div>

    {{-- 1. TOP CARDS (Categorized Information) --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        
        {{-- Card 1: Kategori Valuasi --}}
        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-900 p-5 group shadow-sm flex flex-col justify-between">
            <div class="absolute -right-6 -top-6 text-emerald-500/10 dark:text-emerald-500/5 transition-transform group-hover:scale-110">
                <flux:icon.banknotes class="w-32 h-32" />
            </div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <flux:icon.currency-dollar class="w-4 h-4 text-emerald-500" />
                        <h3 class="text-xs font-bold uppercase tracking-wider">Valuasi Keseluruhan</h3>
                    </div>
                </div>
                <div class="text-3xl font-black text-zinc-900 dark:text-white mt-1 mb-4 truncate" title="Rp {{ number_format($totalAssetValue, 0, ',', '.') }}">
                    <span class="text-lg text-zinc-400 font-bold mr-1">Rp</span>{{ number_format($totalAssetValue, 0, ',', '.') }}
                </div>
                
                {{-- Detail Pemecahan Valuasi --}}
                <div class="grid grid-cols-3 gap-2 border-t border-zinc-100 dark:border-zinc-800 pt-3 mt-auto">
                    <div>
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5 truncate">Mentah</p>
                        <p class="text-xs font-bold text-zinc-700 dark:text-zinc-300 truncate" title="Rp {{ number_format($valuationRaw, 0, ',', '.') }}">{{ number_format($valuationRaw, 0, ',', '.') }}</p>
                    </div>
                    <div class="border-l border-zinc-100 dark:border-zinc-800 pl-2">
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5 truncate">WIP</p>
                        <p class="text-xs font-bold text-zinc-700 dark:text-zinc-300 truncate" title="Rp {{ number_format($valuationWIP, 0, ',', '.') }}">{{ number_format($valuationWIP, 0, ',', '.') }}</p>
                    </div>
                    <div class="border-l border-zinc-100 dark:border-zinc-800 pl-2">
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5 truncate">Jadi</p>
                        <p class="text-xs font-bold text-zinc-700 dark:text-zinc-300 truncate" title="Rp {{ number_format($valuationFinished, 0, ',', '.') }}">{{ number_format($valuationFinished, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Kategori Peringatan & Status Stok --}}
        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-900 p-5 group shadow-sm flex flex-col justify-between">
            <div class="absolute -right-6 -top-6 text-rose-500/10 dark:text-rose-500/5 transition-transform group-hover:scale-110">
                <flux:icon.exclamation-triangle class="w-32 h-32" />
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 mb-2">
                    <flux:icon.shield-exclamation class="w-4 h-4 text-rose-500" />
                    <h3 class="text-xs font-bold uppercase tracking-wider">Peringatan Menipis</h3>
                </div>
                <div class="flex items-end gap-2 mt-1 mb-4">
                    <div class="text-3xl font-black text-rose-600 dark:text-rose-400">
                        {{ count($lowStockItems) }}
                    </div>
                    <div class="text-sm font-semibold text-zinc-500 dark:text-zinc-400 mb-1">Item Butuh Restock</div>
                </div>
                
                {{-- Status Tambahan --}}
                <div class="grid grid-cols-2 gap-2 border-t border-zinc-100 dark:border-zinc-800 pt-3 mt-auto">
                    <div class="cursor-pointer group/item hover:bg-zinc-50 dark:hover:bg-zinc-800/50 p-1 -m-1 rounded transition-colors" wire:click="redirect('{{ route('inventory.items') }}', true)">
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5">Total Barang Aktif</p>
                        <p class="text-sm font-bold text-zinc-700 dark:text-zinc-300 group-hover/item:text-blue-500 flex items-center justify-between">
                            {{ number_format($totalItems) }} <flux:icon.arrow-right class="w-3 h-3 opacity-0 group-hover/item:opacity-100 transition-opacity" />
                        </p>
                    </div>
                    <div class="border-l border-zinc-100 dark:border-zinc-800 pl-2 cursor-pointer group/item hover:bg-zinc-50 dark:hover:bg-zinc-800/50 p-1 -m-1 rounded transition-colors" wire:click="redirect('{{ route('inventory.transfers') }}', true)">
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5">Transfer Pending</p>
                        <p class="text-sm font-bold text-zinc-700 dark:text-zinc-300 group-hover/item:text-orange-500 flex items-center justify-between">
                            {{ number_format($pendingTransfers) }} <flux:icon.arrow-right class="w-3 h-3 opacity-0 group-hover/item:opacity-100 transition-opacity" />
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Kategori Mutasi & Pengguna --}}
        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-zinc-900 p-5 group shadow-sm flex flex-col justify-between">
            <div class="absolute -right-6 -top-6 text-indigo-500/10 dark:text-indigo-500/5 transition-transform group-hover:scale-110">
                <flux:icon.arrows-right-left class="w-32 h-32" />
            </div>
            <div class="relative z-10">
                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400 mb-2">
                    <flux:icon.bolt class="w-4 h-4 text-indigo-500" />
                    <h3 class="text-xs font-bold uppercase tracking-wider">Aktivitas Hari Ini</h3>
                </div>
                
                <div class="flex items-center justify-between mt-1 mb-4">
                    <div>
                        <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Masuk</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <flux:icon.arrow-down-right class="w-4 h-4" /> {{ number_format($todaysMovements->total_in ?? 0) }}
                        </p>
                    </div>
                    <div class="w-px h-10 bg-zinc-200 dark:bg-zinc-700"></div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-zinc-400 uppercase mb-1">Keluar</p>
                        <p class="text-2xl font-black text-rose-600 dark:text-rose-400 flex items-center gap-1 justify-end">
                            {{ number_format($todaysMovements->total_out ?? 0) }} <flux:icon.arrow-up-right class="w-4 h-4" />
                        </p>
                    </div>
                </div>
                
                {{-- Pengguna Aktif --}}
                <div class="border-t border-zinc-100 dark:border-zinc-800 pt-3 mt-auto flex items-center justify-between">
                    <div>
                        <p class="text-[9px] uppercase font-bold text-zinc-400 mb-0.5">Pengguna Aktif di Sistem</p>
                        <div class="flex -space-x-2 overflow-hidden mt-1">
                            @foreach(array_slice($activeUsers, 0, 5) as $u)
                                <div class="inline-block h-6 w-6 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-[9px] font-bold text-indigo-700 dark:text-indigo-300" title="{{ $u['name'] }}">
                                    {{ substr($u['name'], 0, 2) }}
                                </div>
                            @endforeach
                            @if(count($activeUsers) > 5)
                                <div class="inline-block h-6 w-6 rounded-full ring-2 ring-white dark:ring-zinc-900 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-[9px] font-bold text-zinc-600 dark:text-zinc-400">
                                    +{{ count($activeUsers) - 5 }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded-md">{{ count($activeUsers) }} Online</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. BOTTOM LARGE CARD (Tabbed Interface) --}}
    <div x-data="{ activeTab: 'chart', cardExpanded: false }" 
         class="transition-all duration-300 bg-white dark:bg-zinc-900 shadow-sm flex flex-col overflow-hidden z-40"
         :class="cardExpanded ? 'fixed inset-0 sm:inset-4 z-[100] rounded-none sm:rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700' : 'relative h-full flex-1 rounded-xl border border-neutral-200 dark:border-neutral-700'">
        
        {{-- Custom Tab Navigation --}}
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/20 px-2 pt-2">
            <div class="flex items-center overflow-x-auto custom-scrollbar">
                <button @click="activeTab = 'chart'" 
                        class="px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
                        :class="activeTab === 'chart' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'">
                    <flux:icon.chart-bar class="w-4 h-4" /> Grafik Tren Stok
                </button>
                <button @click="activeTab = 'alerts'" 
                        class="px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
                        :class="activeTab === 'alerts' ? 'border-rose-500 text-rose-600 dark:text-rose-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'">
                    <flux:icon.exclamation-triangle class="w-4 h-4" /> Peringatan Stok 
                    @if(count($lowStockItems) > 0)
                        <span class="bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400 text-[10px] px-1.5 py-0.5 rounded-full ml-1">{{ count($lowStockItems) }}</span>
                    @endif
                </button>
                <button @click="activeTab = 'activities'" 
                        class="px-5 py-3 text-sm font-bold border-b-2 transition-colors flex items-center gap-2 whitespace-nowrap"
                        :class="activeTab === 'activities' ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'">
                    <flux:icon.clock class="w-4 h-4" /> Riwayat Transaksi Terbaru
                </button>
            </div>
            
            <div class="px-2 pb-1 shrink-0 flex items-center">
                <button @click="cardExpanded = !cardExpanded" 
                        class="p-2 text-zinc-500 hover:text-indigo-600 dark:text-zinc-400 dark:hover:text-indigo-400 hover:bg-white dark:hover:bg-zinc-700 rounded-md transition-colors border border-transparent hover:border-zinc-200 dark:hover:border-zinc-600 shadow-sm hover:shadow"
                        :title="cardExpanded ? 'Perkecil Tampilan' : 'Perbesar Tampilan'">
                    <flux:icon.arrows-pointing-out x-show="!cardExpanded" class="w-4 h-4" />
                    <flux:icon.arrows-pointing-in x-show="cardExpanded" x-cloak class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- Tab Content Area --}}
        <div class="p-6 flex-1 relative">
            
            {{-- TAB 1: Chart --}}
            <div x-show="activeTab === 'chart'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="h-full w-full flex flex-col">
                <div class="mb-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">Menampilkan pergerakan barang masuk dan keluar selama 7 hari terakhir.</div>
                <div class="flex-1 w-full min-h-[300px]">
                    <div id="stockChart" class="w-full h-full"></div>
                </div>
            </div>

            {{-- TAB 2: Alerts --}}
            <div x-show="activeTab === 'alerts'" style="display: none;" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="h-full">
                <div class="mb-4 text-xs font-medium text-zinc-500 dark:text-zinc-400 flex items-center justify-between">
                    <span>Daftar barang yang jumlah stoknya berada di bawah batas minimum (Reorder Point).</span>
                    <a href="{{ route('inventory.items') }}" wire:navigate class="text-indigo-600 hover:underline">Kelola Data Barang &rarr;</a>
                </div>
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr class="text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider">
                                <th class="px-4 py-3 font-semibold">Kode Barang</th>
                                <th class="px-4 py-3 font-semibold">Nama Barang</th>
                                <th class="px-4 py-3 font-semibold text-right">Batas Minimum</th>
                                <th class="px-4 py-3 font-semibold text-right">Sisa Stok Fisik</th>
                                <th class="px-4 py-3 font-semibold text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($lowStockItems as $lowItem)
                                <tr class="hover:bg-rose-50/50 dark:hover:bg-rose-900/10 transition-colors group">
                                    <td class="px-4 py-3 text-xs font-mono text-zinc-500">{{ $lowItem->code }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-zinc-900 dark:text-white">{{ $lowItem->name }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-zinc-500">{{ $lowItem->min_stock }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-black text-rose-600 dark:text-rose-400">{{ $lowItem->total_stock }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('inventory.items') }}?show_item={{ $lowItem->id }}" wire:navigate class="inline-flex items-center gap-1 text-[10px] font-bold text-white bg-rose-600 hover:bg-rose-700 px-3 py-1.5 rounded-md transition-colors shadow-sm">
                                            Restock Sekarang
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center">
                                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400 mb-3">
                                            <flux:icon.check-circle class="w-6 h-6" />
                                        </div>
                                        <p class="text-sm font-bold text-zinc-700 dark:text-zinc-300">Semua stok aman!</p>
                                        <p class="text-xs text-zinc-500 mt-1">Tidak ada barang yang perlu segera di-restock.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TAB 3: Recent Activities --}}
            <div x-show="activeTab === 'activities'" style="display: none;" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="h-full">
                <div class="mb-4 text-xs font-medium text-zinc-500 dark:text-zinc-400 flex items-center justify-between">
                    <span>8 pergerakan inventaris (masuk/keluar/transfer) terakhir yang dicatat oleh sistem.</span>
                    <a href="{{ route('inventory.movements') }}" wire:navigate class="text-indigo-600 hover:underline">Lihat Semua Mutasi &rarr;</a>
                </div>
                <div class="overflow-x-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr class="text-zinc-500 dark:text-zinc-400 text-[10px] uppercase tracking-wider">
                                <th class="px-4 py-3 font-semibold">Waktu</th>
                                <th class="px-4 py-3 font-semibold">Pengguna</th>
                                <th class="px-4 py-3 font-semibold">Barang</th>
                                <th class="px-4 py-3 font-semibold">Gudang</th>
                                <th class="px-4 py-3 font-semibold text-right">Jumlah Mutasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($recentActivities as $activity)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-4 py-3 text-xs text-zinc-500">{{ $activity->created_at->diffForHumans(null, true, true) }}</td>
                                    <td class="px-4 py-3 text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $activity->user?->name ?? 'Sistem' }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $activity->item?->name ?? 'Unknown Item' }}</td>
                                    <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">{{ $activity->warehouse?->name ?? 'Utama' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($activity->quantity > 0)
                                            <span class="inline-flex items-center gap-1 text-xs font-black text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-1 rounded">
                                                <flux:icon.arrow-down-right class="w-3 h-3" /> +{{ number_format($activity->quantity) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-xs font-black text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 px-2 py-1 rounded">
                                                <flux:icon.arrow-up-right class="w-3 h-3" /> {{ number_format($activity->quantity) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center text-zinc-500 text-sm">Belum ada riwayat transaksi sama sekali.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
