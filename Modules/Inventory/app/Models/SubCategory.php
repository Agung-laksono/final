<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCategory extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi Induk ke Kategori Utama
     * Sub Kategori ini milik Kategori yang mana?
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi ke Barang (Item)
     * Satu Sub Kategori bisa memiliki banyak Barang
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
