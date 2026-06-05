<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi ke Sub Kategori
     * Satu Kategori Utama memiliki banyak Sub Kategori
     */
    public function subCategories()
    {
        return $this->hasMany(SubCategory::class);
    }

    /**
     * Relasi ke Barang (Item)
     * Satu Kategori bisa memiliki banyak Barang
     */
    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
