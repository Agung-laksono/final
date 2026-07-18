<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\UpdatesMenuBadges;

class FinanceTransfer extends Model
{
    use HasFactory, UpdatesMenuBadges;

    protected $guarded = ['id'];

    protected $casts = [
        'transfer_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function fromAccount()
    {
        return $this->belongsTo(FinanceAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(FinanceAccount::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmator()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
    
    // Generator for Transfer Number
    public static function generateTransferNumber()
    {
        $prefix = 'TRF-';
        $date = now()->format('Ymd');
        $last = self::where('transfer_number', 'like', $prefix . $date . '%')
            ->orderBy('transfer_number', 'desc')
            ->first();

        if ($last) {
            $lastSequence = (int)substr($last->transfer_number, -4);
            $newSequence = str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newSequence = '0001';
        }

        return $prefix . $date . '-' . $newSequence;
    }
}
