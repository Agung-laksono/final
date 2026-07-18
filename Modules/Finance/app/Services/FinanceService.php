<?php

namespace Modules\Finance\Services;

use Modules\Finance\Models\FinanceAccount;
use Modules\Finance\Models\FinanceTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class FinanceService
{
    /**
     * Setujui pembayaran penjualan (SalesPayment).
     */
    public function approveSalesPayment($payment, $financeAccountId, $approvedBy)
    {
        $result = DB::transaction(function () use ($payment, $financeAccountId, $approvedBy) {
            $payment->update([
                'status' => 'verified',
                'verified_by' => $approvedBy,
                'verified_at' => now(),
            ]);

            $transaction = $this->recordTransaction(
                accountId: $financeAccountId,
                type: 'income',
                amount: $payment->amount,
                date: $payment->payment_date,
                description: 'Penerimaan Penjualan SO: ' . $payment->salesOrder->so_number,
                reference: $payment,
                createdBy: $approvedBy
            );

            // Perbarui payment_status pada SalesOrder
            $order = $payment->salesOrder;
            $totalVerified = $order->payments()->where('status', 'verified')->sum('amount');
            if ($totalVerified >= $order->total_amount) {
                $order->update(['payment_status' => 'paid']);
            } elseif ($totalVerified > 0) {
                $order->update(['payment_status' => 'partial']);
            }

            return $transaction;
        });

        // Event driven: Update Kanban board secara realtime di sisi Sales
        \App\Events\KanbanUpdated::safeDispatch('sales_order');

        if ($payment->creator) {
            $validator = \App\Models\User::find($approvedBy);
            $payment->creator->notify(new \App\Notifications\PaymentValidatedNotification($payment->salesOrder->so_number, $payment->amount, $validator, 'sales'));
        }

        return $result;
    }

    /**
     * Setujui pembayaran pembelian (PurchasePayment).
     */
    public function approvePurchasePayment($payment, $financeAccountId, $approvedBy)
    {
        $result = DB::transaction(function () use ($payment, $financeAccountId, $approvedBy) {
            $payment->update([
                'status' => 'verified',
                'verified_by' => $approvedBy,
                'verified_at' => now(),
            ]);

            $transaction = $this->recordTransaction(
                accountId: $financeAccountId,
                type: 'expense',
                amount: $payment->amount,
                date: $payment->payment_date,
                description: 'Pembayaran Pembelian/SPK: ' . $payment->purchaseOrder->po_number,
                reference: $payment,
                createdBy: $approvedBy
            );
            
            // Perbarui payment_status pada PurchaseOrder (jika ada field-nya)
            $order = $payment->purchaseOrder;
            if ($order) {
                $totalVerified = $order->payments()->where('status', 'verified')->sum('amount');
                if ($totalVerified >= $order->total_amount) {
                    $order->update(['payment_status' => 'paid']);
                } elseif ($totalVerified > 0) {
                    $order->update(['payment_status' => 'partial']);
                }
            }
            
            return $transaction;
        });

        // Event driven: Update Kanban board secara realtime
        \App\Events\KanbanUpdated::safeDispatch('purchase_order');

        if ($payment->creator) {
            $validator = \App\Models\User::find($approvedBy);
            $payment->creator->notify(new \App\Notifications\PaymentValidatedNotification($payment->purchaseOrder->po_number, $payment->amount, $validator, 'purchase'));
        }

        return $result;
    }
    
    /**
     * Tolak pembayaran pembelian atau penjualan.
     */
    public function rejectPayment($payment, $reason, $rejectedBy)
    {
        $payment->update([
            'status' => 'rejected',
            'verified_by' => $rejectedBy,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
        
        // Event driven fallback update
        if ($payment instanceof \Modules\Sales\Models\SalesPayment) {
            \App\Events\KanbanUpdated::safeDispatch('sales_order');
            if ($payment->creator) {
                $rejector = \App\Models\User::find($rejectedBy);
                $payment->creator->notify(new \App\Notifications\PaymentRejectedNotification($payment->salesOrder->so_number, $payment->amount, $rejector, $reason, 'sales'));
            }
        } elseif ($payment instanceof \Modules\Purchase\Models\PurchasePayment) {
            \App\Events\KanbanUpdated::safeDispatch('purchase_order');
            if ($payment->creator) {
                $rejector = \App\Models\User::find($rejectedBy);
                $payment->creator->notify(new \App\Notifications\PaymentRejectedNotification($payment->purchaseOrder->po_number, $payment->amount, $rejector, $reason, 'purchase'));
            }
        }
        
        return $payment;
    }
    
    /**
     * Catat transaksi mutasi kas/bank yang sah.
     */
    public function recordTransaction($accountId, $type, $amount, $date, $description, $reference = null, $categoryId = null, $createdBy = null)
    {
        $account = FinanceAccount::findOrFail($accountId);
        
        if ($amount <= 0) {
            throw new Exception("Nominal transaksi harus lebih besar dari nol.");
        }

        $transaction = FinanceTransaction::create([
            'finance_account_id' => $accountId,
            'finance_category_id' => $categoryId,
            'type' => $type,
            'amount' => $amount,
            'transaction_date' => $date,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        if ($type === 'income') {
            $account->increment('current_balance', $amount);
        } elseif ($type === 'expense') {
            $account->decrement('current_balance', $amount);
        }

        return $transaction;
    }

    /**
     * Mengajukan transfer internal antar akun (Mutasi) dengan Handshake.
     */
    public function createInternalTransfer($fromAccountId, $toAccountId, $amount, $date, $notes = null, $proofPath = null, $createdBy = null)
    {
        if ($amount <= 0) {
            throw new Exception("Nominal transfer harus lebih besar dari nol.");
        }

        if ($fromAccountId == $toAccountId) {
            throw new Exception("Akun asal dan tujuan tidak boleh sama.");
        }

        $transfer = \Modules\Finance\Models\FinanceTransfer::create([
            'transfer_number' => \Modules\Finance\Models\FinanceTransfer::generateTransferNumber(),
            'from_account_id' => $fromAccountId,
            'to_account_id' => $toAccountId,
            'amount' => $amount,
            'transfer_date' => $date,
            'notes' => $notes,
            'proof_path' => $proofPath,
            'status' => 'pending',
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        return $transfer;
    }

    /**
     * Konfirmasi penerimaan transfer internal.
     */
    public function confirmInternalTransfer($transfer, $confirmedBy)
    {
        if ($transfer->status !== 'pending') {
            throw new Exception("Transfer ini sudah diproses atau dibatalkan.");
        }

        return DB::transaction(function () use ($transfer, $confirmedBy) {
            $transfer->update([
                'status' => 'completed',
                'confirmed_by' => $confirmedBy,
                'confirmed_at' => now(),
            ]);

            // Catat pengeluaran dari akun asal
            $this->recordTransaction(
                accountId: $transfer->from_account_id,
                type: 'expense',
                amount: $transfer->amount,
                date: $transfer->transfer_date,
                description: 'Mutasi Keluar ke ' . ($transfer->toAccount->name ?? 'Akun Tujuan') . ' (' . $transfer->transfer_number . ')',
                reference: $transfer,
                createdBy: $confirmedBy
            );

            // Catat pemasukan ke akun tujuan
            $this->recordTransaction(
                accountId: $transfer->to_account_id,
                type: 'income',
                amount: $transfer->amount,
                date: $transfer->transfer_date,
                description: 'Mutasi Masuk dari ' . ($transfer->fromAccount->name ?? 'Akun Asal') . ' (' . $transfer->transfer_number . ')',
                reference: $transfer,
                createdBy: $confirmedBy
            );

            return $transfer;
        });
    }

    /**
     * Tolak penerimaan transfer internal.
     */
    public function rejectInternalTransfer($transfer, $reason, $rejectedBy)
    {
        if ($transfer->status !== 'pending') {
            throw new Exception("Transfer ini sudah diproses atau dibatalkan.");
        }

        $transfer->update([
            'status' => 'rejected',
            'confirmed_by' => $rejectedBy,
            'confirmed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $transfer;
    }
}
