<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Type extends Model
{
    use HasFactory;

    // Membuka semua kolom agar bisa diisi (Mass Assignment), kecuali kolom ID
    protected $guarded = ['id'];

    /**
     * Relasi ke Barang (Item)
     * Satu Tipe bisa menaungi banyak Barang
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
