<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function newFactory()
    {
        return \Modules\Inventory\Database\Factories\ItemFactory::new();
    }

    // --- RELASI KE MASTER DATA ---

    /** Barang ini menggunakan Satuan (Unit) apa? */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /** Barang ini masuk ke Tipe apa? */
    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    /** Barang ini ada di Kategori apa? */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /** Barang ini ada di Sub Kategori apa? */
    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    // --- RELASI KE TRANSAKSI & GUDANG ---

    /** 
     * Relasi Many-to-Many ke Gudang (Warehouse)
     * Barang ini sedang ada di gudang mana saja? (beserta jumlah stoknya di gudang tersebut)
     */
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'item_warehouse')
                    ->withPivot('stock')
                    ->withTimestamps();
    }

    /**
     * Calculate Available to Promise (ATP)
     * ATP = Physical Stock + Incoming (WIP + PO) - Committed (Booking)
     */
    public function getATP()
    {
        // 1. Physical Stock
        $totalStock = \Illuminate\Support\Facades\DB::table('item_warehouse')
            ->where('item_id', $this->id)
            ->sum('stock');

        // 2. Incoming (WIP)
        $wipQty = \Modules\Production\Models\ProductionOrder::where('item_id', $this->id)
            ->whereNotIn('status', ['completed', 'archived', 'rejected'])
            ->selectRaw('SUM(requested_qty - fulfilled_qty) as remaining')
            ->value('remaining') ?? 0;

        // 3. Incoming (Purchase Queue/Order)
        $poQty = \Modules\Purchase\Models\PurchaseQueue::where('item_id', $this->id)
            ->whereNotIn('status', ['completed', 'rejected'])
            ->sum('requested_qty') ?? 0;

        // 4. Committed (Booking from Sales Order)
        $bookingQty = \Modules\Sales\Models\SalesOrderItem::where('item_id', $this->id)
            ->whereHas('salesOrder', function($q) {
                $q->whereIn('status', ['approved', 'processing']);
            })
            ->sum('qty') ?? 0;

        $atp = $totalStock + $wipQty + $poQty - $bookingQty;
        return max(0, $atp);
    }

    /** 
     * Riwayat Pergerakan Stok (Keluar/Masuk/Transfer)
     * untuk barang ini
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Riwayat Perubahan Harga (History Naik/Turun)
     */
    public function priceHistories()
    {
        return $this->hasMany(ItemPriceHistory::class);
    }

    /**
     * Daftar Label/Serial Number Fisik untuk Barang ini
     */
    public function labels()
    {
        return $this->hasMany(ItemLabel::class);
    }

    /**
     * Riwayat transfer antar gudang untuk barang ini
     */
    public function stockTransfers()
    {
        return $this->hasMany(StockTransfer::class);
    }

    /**
     * Riwayat Stok Opname (Penyesuaian) untuk barang ini
     */
    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }
}
