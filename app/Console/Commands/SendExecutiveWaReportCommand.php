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

        if (class_exists(\Modules\Finance\Models\FinanceTransaction::class)) {
            try {
                $cashInToday = \Modules\Finance\Models\FinanceTransaction::whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['in', 'income', 'masuk'])
                    ->sum('amount');
                $cashOutToday = \Modules\Finance\Models\FinanceTransaction::whereBetween('created_at', [$todayStart, $todayEnd])
                    ->whereIn('type', ['out', 'expense', 'keluar'])
                    ->sum('amount');
            } catch (\Exception $e) {}
        }

        if (class_exists(\Modules\Finance\Models\FinanceAccount::class)) {
            try {
                $totalCashBankBalance = \Modules\Finance\Models\FinanceAccount::where('status', 'active')->sum('current_balance');
            } catch (\Exception $e) {}
        }

        // B. Sales Data
        $salesCountToday = 0;
        $salesOmsetToday = 0;
        if (class_exists(\Modules\Sales\Models\SalesOrder::class)) {
            try {
                $salesQuery = \Modules\Sales\Models\SalesOrder::whereBetween('created_at', [$todayStart, $todayEnd]);
                $salesCountToday = $salesQuery->count();
                $salesOmsetToday = $salesQuery->sum('total_amount');

                $totalArUnpaid = \Modules\Sales\Models\SalesOrder::whereNotIn('status', ['cancelled', 'completed', 'paid'])->sum('total_amount');
            } catch (\Exception $e) {}
        }

        // C. Inventory Data
        $lowStockCount = 0;
        if (class_exists(\Modules\Inventory\Models\Item::class)) {
            try {
                $lowStockCount = \Modules\Inventory\Models\Item::whereColumn('stock', '<=', 'min_stock')->count();
            } catch (\Exception $e) {}
        }

        // D. Purchase Data
        if (class_exists(\Modules\Purchase\Models\PurchaseOrder::class)) {
            try {
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

        $msg .= "💳 *1. RINGKASAN KEUANGAN*\n";
        $msg .= "• Kas Masuk Hari Ini: Rp " . number_format($cashInToday, 0, ',', '.') . "\n";
        $msg .= "• Kas Keluar Hari Ini: Rp " . number_format($cashOutToday, 0, ',', '.') . "\n";
        $msg .= "• Total Saldo Kas & Bank: Rp " . number_format($totalCashBankBalance, 0, ',', '.') . "\n";
        $msg .= "• Estimasi Sisa Piutang (AR): Rp " . number_format($totalArUnpaid, 0, ',', '.') . "\n";
        $msg .= "• Estimasi Sisa Hutang (AP): Rp " . number_format($totalApUnpaid, 0, ',', '.') . "\n\n";

        $msg .= "🛒 *2. RINGKASAN PENJUALAN (SALES)*\n";
        $msg .= "• Total Order Hari Ini: {$salesCountToday} Transaksi\n";
        $msg .= "• Total Omset Penjualan: Rp " . number_format($salesOmsetToday, 0, ',', '.') . "\n\n";

        $msg .= "📦 *3. GUDANG & INVENTORY*\n";
        $msg .= "• Peringatan Stok Menipis: {$lowStockCount} Items\n\n";

        $msg .= "🏭 *4. PRODUKSI*\n";
        $msg .= "• Batch Selesai Hari Ini: {$prodCompletedToday} Order\n";
        $msg .= "• Batch Dalam Proses: {$prodInProgress} Order\n";
        $msg .= "==============================\n\n";
        $msg .= "💡 *Pesan otomatis dikirim oleh ROMLAH ERP Assistant via Fonnte WA.*";

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
