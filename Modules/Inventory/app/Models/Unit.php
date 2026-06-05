<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    // Membuka semua kolom agar bisa diisi (Mass Assignment), kecuali kolom ID
    protected $guarded = ['id'];

    /**
     * Relasi ke Barang (Item)
     * Satu Satuan (Unit) bisa dipakai oleh banyak Barang (Item)
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
