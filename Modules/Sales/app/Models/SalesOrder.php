<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesOrder extends Model
{
    use HasFactory, \App\Traits\UpdatesMenuBadges, \App\Traits\SearchableAiKnowledge;

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function fulfillments()
    {
        return $this->hasMany(SalesOrderFulfillment::class);
    }

    public function payments()
    {
        return $this->hasMany(SalesPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function brand()
    {
        return $this->belongsTo(\App\Models\Brand::class);
    }

    public function courierVendor()
    {
        return $this->belongsTo(\Modules\Purchase\Models\Vendor::class, 'courier_vendor_id');
    }

    /**
     * Memeriksa ketersediaan stok (ATP) untuk semua item di SO ini.
     * Jika terjadi defisit, sistem akan otomatis membuat InventoryRequest.
     * Mengembalikan boolean true jika ada defisit yang memicu pembuatan request baru.
     */
    public function checkStockAndCreateInventoryRequests(): bool
    {
        $hasDeficit = false;
        
        // Pastikan relasi items sudah dimuat
        if (!$this->relationLoaded('items')) {
            $this->load('items');
        }
        
        foreach ($this->items as $item) {
            $itemModel = \Modules\Inventory\Models\Item::find($item->item_id);
            $atp = $itemModel ? $itemModel->getATP() : 0;
            
            if ($item->qty > $atp) {
                $deficit = $item->qty - $atp;
                
                // Pastikan belum ada antrean untuk item ini dari SO ini
                $existingRequest = \Modules\Inventory\Models\InventoryRequest::where('item_id', $item->item_id)
                    ->where('source_type', 'sales')
                    ->where('reference_number', $this->so_number)
                    ->exists();
                    
                if (!$existingRequest) {
                    \Modules\Inventory\Models\InventoryRequest::create([
                        'item_id' => $item->item_id,
                        'source_type' => 'sales',
                        'reference_number' => $this->so_number,
                        'requested_qty' => $deficit,
                        'notes' => 'Defisit stok untuk pesanan pelanggan (ATP: ' . $atp . ', Dipesan: ' . $item->qty . ')' . 
                                   (!empty($item->custom_attributes) || !empty($item->custom_attachments) ? ' [CUSTOM]' : ''),
                        'status' => 'draft',
                    ]);
                    $hasDeficit = true;
                }
            }
        }
        
        return $hasDeficit;
    }
}
