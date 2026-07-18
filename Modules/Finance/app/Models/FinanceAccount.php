<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\FinanceAccountFactory;

class FinanceAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    // protected static function newFactory(): FinanceAccountFactory
    // {
    //     // return FinanceAccountFactory::new();
    // }
}
