<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesPayment extends Model
{
    use HasFactory, \App\Traits\UpdatesMenuBadges;

    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function financeAccount()
    {
        return $this->belongsTo(\Modules\Finance\Models\FinanceAccount::class);
    }
}
