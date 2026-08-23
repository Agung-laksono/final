<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Setting;
use Modules\Communication\Services\FonnteService;

class SendExecutiveWaReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-executive-report {--target= : Optional specific phone number target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim Laporan Operasional Eksekutif Otomatis via WhatsApp (Fonnte API)';

    /**
     * Execute the console command.
     */
    public function handle(FonnteService $fonnte)
    {
        $this->info('Menyiapkan Laporan Eksekutif ERP...');

        // 1. Determine Recipients
        $targetInput = $this->option('target');
        if (empty($targetInput)) {
            $targetInput = Setting::where('key', 'wa_report_recipients')->value('value');
        }

        $recipients = trim($targetInput ?? '');

        if (empty($recipients)) {
            // Fallback: fetch phone numbers of Super Admin / Manager users if column exists
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                    $adminPhones = \App\Models\User::whereNotNull('phone')
                        ->where('phone', '!=', '')
                        ->pluck('phone')
                        ->toArray();
                    $recipients = implode(',', $adminPhones);
                }
            } catch (\Exception $e) {
                // Ignore schema check errors
            }
        }

        if (empty(trim($recipients))) {
            $this->error('Tidak ada nomor WhatsApp penerima yang terdaftar.');
            return 1;
        }

        // 2. Gather Data Across Divisions
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $dateFormatted = now()->timezone('Asia/Jakarta')->format('d M Y');
        $timeFormatted = now()->timezone('Asia/Jakarta')->format('H:i');

        // A. Finance Data
        $cashInToday = 0;
        $cashOutToday = 0;
        $totalCashBankBalance = 0;
        $totalArUnpaid = 0;
        $totalApUnpaid = 0;
        $accountBalances = [];
        $cashInBreakdown = [];
        $cashOutBreakdown = [];

        if (class_exists(\Modules\Finance\Models\FinanceTransaction::class)) {
            try {
                $cashInToday = \Modules\Finance\Models\FinanceTransaction::whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['in', 'income', 'masuk'])
                    ->sum('amount');
                $cashOutToday = \Modules\Finance\Models\FinanceTransaction::whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['out', 'expense', 'keluar'])
                    ->sum('amount');

                // Breakdown Kas Masuk
                $todayInTxs = \Modules\Finance\Models\FinanceTransaction::with('category')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['in', 'income', 'masuk'])
                    ->get();
                $groupedIn = [];
                foreach ($todayInTxs as $t) {
                    $cName = $t->category->name ?? ($t->description ?: 'Penerimaan Penjualan');
                    $groupedIn[$cName] = ($groupedIn[$cName] ?? 0) + ($t->amount ?? 0);
                }
                arsort($groupedIn);
                foreach (array_slice($groupedIn, 0, 3) as $cName => $amt) {
                    $cashInBreakdown[] = "  └ *Pemasukan ({$cName})*: Rp " . number_format($amt, 0, ',', '.');
                }

                // Breakdown Kas Keluar
                $todayOutTxs = \Modules\Finance\Models\FinanceTransaction::with('category')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['out', 'expense', 'keluar'])
                    ->get();
                $groupedOut = [];
                foreach ($todayOutTxs as $t) {
                    $cName = $t->category->name ?? ($t->description ?: 'Pengeluaran Operasional');
                    $groupedOut[$cName] = ($groupedOut[$cName] ?? 0) + ($t->amount ?? 0);
                }
                arsort($groupedOut);
                foreach (array_slice($groupedOut, 0, 3) as $cName => $amt) {
                    $cashOutBreakdown[] = "  └ *Pengeluaran ({$cName})*: Rp " . number_format($amt, 0, ',', '.');
                }
            } catch (\Exception $e) {}
        }

        if (class_exists(\Modules\Finance\Models\FinanceAccount::class)) {
            try {
                $accounts = \Modules\Finance\Models\FinanceAccount::where('status', 'active')->get();
                $totalCashBankBalance = $accounts->sum('current_balance');
                foreach ($accounts as $acc) {
                    $accountBalances[] = "  └ *{$acc->name}*: Rp " . number_format($acc->current_balance ?? 0, 0, ',', '.');
                }
            } catch (\Exception $e) {}
        }

        // B. Sales Data & Salesperson Breakdown
        $salesCountToday = 0;
        $salesOmsetToday = 0;
        $salesOmsetMonth = 0;
        $soDraftCount = 0;
        $salespersonBreakdown = [];

        if (class_exists(\Modules\Sales\Models\SalesOrder::class)) {
            try {
                $salesQuery = \Modules\Sales\Models\SalesOrder::whereBetween('created_at', [$todayStart, $todayEnd]);
                $salesCountToday = $salesQuery->count();
                $salesOmsetToday = $salesQuery->sum('total_amount');

                $monthStart = now()->startOfMonth();
                $salesOmsetMonth = \Modules\Sales\Models\SalesOrder::whereBetween('created_at', [$monthStart, $todayEnd])
                    ->sum('total_amount');

                $soDraftCount = \Modules\Sales\Models\SalesOrder::where('status', 'draft')->count();
                $totalArUnpaid = \Modules\Sales\Models\SalesOrder::whereNotIn('status', ['cancelled', 'completed', 'paid'])->sum('total_amount');

                // Breakdown per Salesperson
                $todaySales = \Modules\Sales\Models\SalesOrder::with('creator')
                    ->whereBetween('created_at', [$todayStart, $todayEnd])
                    ->get();
                
                $groupedSales = [];
                foreach ($todaySales as $so) {
                    $sName = $so->creator->name ?? 'Sales Tim / Admin';
                    if (!isset($groupedSales[$sName])) {
                        $groupedSales[$sName] = ['count' => 0, 'amount' => 0];
                    }
                    $groupedSales[$sName]['count']++;
                    $groupedSales[$sName]['amount'] += ($so->total_amount ?? 0);
                }

                foreach ($groupedSales as $sName => $sData) {
                    $salespersonBreakdown[] = "  └ *{$sName}*: {$sData['count']} Order (Rp " . number_format($sData['amount'], 0, ',', '.') . ")";
                }
            } catch (\Exception $e) {}
        }

        // C. Inventory Data
        $lowStockCount = 0;
        $lowStockItems = [];
        $totalInventoryAssetValue = 0;

        if (class_exists(\Modules\Inventory\Models\Item::class)) {
            try {
                $items = \Modules\Inventory\Models\Item::all();
                foreach ($items as $it) {
                    $stock = $it->stock ?? 0;
                    $cost = $it->cost_price ?? ($it->unit_price ?? 0);
                    $totalInventoryAssetValue += ($stock * $cost);
                    
                    if ($it->min_stock && $stock <= $it->min_stock) {
                        $lowStockCount++;
                        if (count($lowStockItems) < 3) {
                            $lowStockItems[] = "  └ *{$it->name}*: {$stock} {$it->unit} (Min: {$it->min_stock})";
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        // D. Purchase Data
        $poCountToday = 0;
        $poAmountToday = 0;
        if (class_exists(\Modules\Purchase\Models\PurchaseOrder::class)) {
            try {
                $poToday = \Modules\Purchase\Models\PurchaseOrder::whereBetween('created_at', [$todayStart, $todayEnd]);
                $poCountToday = $poToday->count();
                $poAmountToday = $poToday->sum('total_amount');
                $totalApUnpaid = \Modules\Purchase\Models\PurchaseOrder::whereNotIn('status', ['cancelled', 'completed', 'paid'])->sum('total_amount');
            } catch (\Exception $e) {}
        }

        // E. Production Data
        $prodCompletedToday = 0;
        $prodInProgress = 0;
        if (class_exists(\Modules\Production\Models\ProductionOrder::class)) {
            try {
                $prodCompletedToday = \Modules\Production\Models\ProductionOrder::whereBetween('updated_at', [$todayStart, $todayEnd])
                    ->where('status', 'completed')
                    ->count();
                $prodInProgress = \Modules\Production\Models\ProductionOrder::where('status', 'in_progress')->count();
            } catch (\Exception $e) {}
        }

        // 3. Format WhatsApp Message
        $companyName = Setting::where('key', 'company_name')->value('value') ?? 'PT. Romlah Jaya';
        
        $msg = "📊 *LAPORAN EKSEKUTIF ERP METRICS*\n";
        $msg .= "🏢 *{$companyName}*\n";
        $msg .= "🗓️ Tanggal: {$dateFormatted} | Jam: {$timeFormatted} WIB\n";
        $msg .= "==============================\n\n";

        $msg .= "💳 *1. FINANSIAL & SALDO KAS/BANK*\n";
        $msg .= "• Kas Masuk Hari Ini: Rp " . number_format($cashInToday, 0, ',', '.') . "\n";
        if (count($cashInBreakdown) > 0) {
            $msg .= implode("\n", $cashInBreakdown) . "\n";
        }
        $msg .= "• Kas Keluar Hari Ini: Rp " . number_format($cashOutToday, 0, ',', '.') . "\n";
        if (count($cashOutBreakdown) > 0) {
            $msg .= implode("\n", $cashOutBreakdown) . "\n";
        }
        $msg .= "• *Total Saldo Kas & Bank*: Rp " . number_format($totalCashBankBalance, 0, ',', '.') . "\n";
        if (count($accountBalances) > 0) {
            $msg .= implode("\n", $accountBalances) . "\n";
        }
        $msg .= "• Estimasi Piutang Pelanggan (AR): Rp " . number_format($totalArUnpaid, 0, ',', '.') . "\n";
        $msg .= "• Estimasi Hutang Supplier (AP): Rp " . number_format($totalApUnpaid, 0, ',', '.') . "\n\n";

        $msg .= "🛒 *2. PENJUALAN & PERFORMA SALES*\n";
        $msg .= "• Order Masuk Hari Ini: {$salesCountToday} Transaksi\n";
        $msg .= "• *Omset Penjualan Hari Ini*: Rp " . number_format($salesOmsetToday, 0, ',', '.') . "\n";
        if (count($salespersonBreakdown) > 0) {
            $msg .= "  *Rincian Performa Tim Sales:*\n" . implode("\n", $salespersonBreakdown) . "\n";
        }
        $msg .= "• Total Omset Bulan Ini (MTD): Rp " . number_format($salesOmsetMonth, 0, ',', '.') . "\n";
        $msg .= "• Draft SO Menunggu Approve: {$soDraftCount} Order\n\n";

        $msg .= "📦 *3. GUDANG & INVENTARIS*\n";
        $msg .= "• Total Nilai Aset Stok: Rp " . number_format($totalInventoryAssetValue, 0, ',', '.') . "\n";
        $msg .= "• Peringatan Stok Menipis: {$lowStockCount} Item\n";
        if (count($lowStockItems) > 0) {
            $msg .= implode("\n", $lowStockItems) . "\n";
        }
        $msg .= "\n";

        $msg .= "🛒 *4. PEMBELIAN (PURCHASING)*\n";
        $msg .= "• PO Belanja Hari Ini: {$poCountToday} Order (Rp " . number_format($poAmountToday, 0, ',', '.') . ")\n\n";

        $msg .= "🏭 *5. MANUFAKTUR & PRODUKSI*\n";
        $msg .= "• Batch SPK Selesai Hari Ini: {$prodCompletedToday} Batch\n";
        $msg .= "• Batch SPK Sedang Berjalan: {$prodInProgress} Batch\n";
        $msg .= "==============================\n\n";
        $msg .= "💡 *Laporan ini dikirim secara otomatis oleh ROMLAH ERP System via Fonnte WA.*";

        // 4. Send via Fonnte
        $response = $fonnte->sendMessage($recipients, $msg);

        if ($response && ($response['status'] ?? false)) {
            $this->info("Laporan Eksekutif WhatsApp berhasil dikirim ke: {$recipients}");
            return 0;
        } else {
            $this->error("Gagal mengirim Laporan WA. Cek token Fonnte / nomor HP.");
            return 1;
        }
    }
}
