<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\FinanceCategoryFactory;

class FinanceCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    public function transactions()
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    // protected static function newFactory(): FinanceCategoryFactory
    // {
    //     // return FinanceCategoryFactory::new();
    // }
}
