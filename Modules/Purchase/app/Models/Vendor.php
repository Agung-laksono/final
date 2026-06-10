<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Purchase\Database\Factories\VendorFactory;

class Vendor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = ['id'];

    // protected static function newFactory(): VendorFactory
    // {
    //     // return VendorFactory::new();
    // }
}
