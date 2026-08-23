<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory, \App\Traits\SearchableAiKnowledge;

    protected $fillable = [
        'name',
        'tagline',
        'address',
        'logo',
        'phone',
        'email',
        'website',
        'npwp',
        'director_name',
        'signature_image',
        'stamp_image',
    ];

    public function financeAccounts()
    {
        return $this->belongsToMany(
            \Modules\Finance\Models\FinanceAccount::class,
            'brand_finance_account'
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
