<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Relasi Many-to-Many ke Barang (Item)
     * Sebuah Gudang menyimpan banyak Barang
     * Kita menyertakan kolom 'stock' dari tabel pivot 'item_warehouse'
     */
    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_warehouse')
                    ->withPivot('stock')
                    ->withTimestamps();
    }

    /**
     * Relasi ke Mutasi Stok (Stock Movement)
     * Gudang ini memiliki riwayat pergerakan stok apa saja?
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Relasi ke Label Fisik Barang (Item Labels)
     * Ada fisik barang bernomor seri apa saja di gudang ini?
     */
    public function itemLabels()
    {
        return $this->hasMany(ItemLabel::class);
    }

    /**
     * Transfer barang DARI gudang ini
     */
    public function stockTransfersFrom()
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    /**
     * Transfer barang MENUJU gudang ini
     */
    public function stockTransfersTo()
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    /**
     * Riwayat Stok Opname (Penyesuaian) di gudang ini
     */
    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }
}
