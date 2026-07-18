<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\PurchasePaymentFactory;

class PurchasePayment extends Model
{
    use HasFactory, \App\Traits\UpdatesMenuBadges;

    protected $fillable = [
        'purchase_order_id',
        'amount',
        'payment_date',
        'payment_method',
        'proof_path',
        'finance_account_id',
        'notes',
        'created_by',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason'
    ];

    protected $casts = [
        'payment_date' => 'date'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function financeTransaction()
    {
        return $this->morphOne(\Modules\Finance\Models\FinanceTransaction::class, 'reference');
    }

    public function financeAccount()
    {
        return $this->belongsTo(\Modules\Finance\Models\FinanceAccount::class);
    }

    // protected static function newFactory(): PurchasePaymentFactory
    // {
    //     // return PurchasePaymentFactory::new();
    // }
}
