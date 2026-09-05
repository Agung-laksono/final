<?php

namespace App\Services;

use Modules\Sales\Models\SalesOrder;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;
use Modules\Production\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoidService
{
    /**
     * Membatalkan Sales Order dan mengelola pengembalian dana
     */
    public function voidSalesOrder(SalesOrder $order, $refundType = 'store_credit', $spkAction = 'continue')
    {
        Log::info("Memulai Void SO: " . $order->so_number);
        DB::transaction(function () use ($order, $refundType, $spkAction) {
            // 1. Ubah status SO menjadi void
            $order->status = 'void';
            $order->save();

            // 2. Tangani Uang yang sudah masuk (jika ada)
            $totalPaid = $order->payments()->where('status', 'verified')->sum('amount');
            
            if ($totalPaid > 0) {
                if ($refundType === 'store_credit') {
                    // Masukkan ke Deposit Pelanggan
                    $account = FinanceAccount::firstOrCreate(
                        ['name' => 'Kas Titipan Penjualan'],
                        ['type' => 'customer_deposit', 'is_active' => true]
                    );
                    
                    $account->current_balance += $totalPaid;
                    $account->save();
                    
                    // Update Customer deposit_balance
                    if ($order->customer) {
                        $order->customer->deposit_balance += $totalPaid;
                        $order->customer->save();
                    }
                    
                    FinanceTransaction::create([
                        'finance_account_id' => $account->id,
                        'type' => 'income',
                        'amount' => $totalPaid,
                        'transaction_date' => now(),
                        'description' => "Titipan dari SO Void: {$order->so_number} (" . ($order->customer ? $order->customer->name : "Customer #{$order->customer_id}") . ")",
                        'reference_type' => SalesOrder::class,
                        'reference_id' => $order->id,
                    ]);
                } else if ($refundType === 'refund_cash') {
                    // Buat pengeluaran kas (asumsikan dari akun kas utama)
                    $mainAccount = FinanceAccount::where('type', 'cash')->first();
                    if ($mainAccount) {
                        $mainAccount->current_balance -= $totalPaid;
                        $mainAccount->save();
                        
                        FinanceTransaction::create([
                            'finance_account_id' => $mainAccount->id,
                            'type' => 'expense',
                            'amount' => $totalPaid,
                            'transaction_date' => now(),
                            'description' => "Refund Tunai pembatalan SO: {$order->so_number}",
                            'reference_type' => SalesOrder::class,
                            'reference_id' => $order->id,
                        ]);
                    }
                }
            }

            // 3. Tangani SPK Produksi (Hybrid)
            if (class_exists(ProductionOrder::class)) {
                $spks = ProductionOrder::where('notes', 'like', "%{$order->so_number}%")
                                       ->orWhere('id', $order->production_order_id ?? 0)
                                       ->get();
                
                foreach ($spks as $spk) {
                    if ($spkAction === 'void') {
                        $spk->status = 'void';
                        $spk->notes .= "\n(Dibatalkan otomatis dari Void SO)";
                        $spk->save();
                    } else if ($spkAction === 'continue') {
                        // Ubah jadi stok internal
                        $spk->notes .= "\n(Dialihkan menjadi Stok Internal karena SO Dibatalkan)";
                        $spk->save();
                    }
                }
            }
        });
        Log::info("Selesai Void SO: " . $order->so_number);
    }

    /**
     * Membatalkan Purchase Order dan mengelola pengembalian dana
     */
    public function voidPurchaseOrder(PurchaseOrder $po, $refundType = 'vendor_credit')
    {
        Log::info("Memulai Void PO: " . $po->po_number);
        DB::transaction(function () use ($po, $refundType) {
            // 1. Ubah status PO menjadi void
            $po->status = 'void';
            $po->save();

            // 2. Tangani Uang yang sudah keluar (jika ada)
            $totalPaid = $po->payments()->where('status', 'verified')->sum('amount');
            
            if ($totalPaid > 0) {
                if ($refundType === 'vendor_credit') {
                    // Catat sebagai Deposit di Vendor
                    $account = FinanceAccount::firstOrCreate(
                        ['name' => 'Kas Titipan Pembelian'],
                        ['type' => 'vendor_deposit', 'is_active' => true]
                    );
                    
                    $account->current_balance += $totalPaid;
                    $account->save();
                    
                    // Update Vendor deposit_balance
                    if ($po->vendor) {
                        $po->vendor->deposit_balance += $totalPaid;
                        $po->vendor->save();
                    }
                    
                    FinanceTransaction::create([
                        'finance_account_id' => $account->id,
                        'type' => 'income',
                        'amount' => $totalPaid,
                        'transaction_date' => now(),
                        'description' => "Titipan dari PO Void: {$po->po_number} (" . ($po->vendor ? $po->vendor->name : "Vendor #{$po->vendor_id}") . ")",
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => $po->id,
                    ]);
                } else if ($refundType === 'refund_cash') {
                    // Vendor transfer balik uangnya (Pemasukan Kas)
                    $mainAccount = FinanceAccount::where('type', 'cash')->first();
                    if ($mainAccount) {
                        $mainAccount->current_balance += $totalPaid;
                        $mainAccount->save();
                        
                        FinanceTransaction::create([
                            'finance_account_id' => $mainAccount->id,
                            'type' => 'income',
                            'amount' => $totalPaid,
                            'transaction_date' => now(),
                            'description' => "Refund Tunai dari Vendor pembatalan PO: {$po->po_number}",
                            'reference_type' => PurchaseOrder::class,
                            'reference_id' => $po->id,
                        ]);
                    }
                }
            }
        });
        Log::info("Selesai Void PO: " . $po->po_number);
    }
}
