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

    public function getShippingRecipientNameAttribute()
    {
        return !empty($this->recipient_name) ? $this->recipient_name : ($this->customer?->name ?? 'Pelanggan');
    }

    public function getShippingRecipientPhoneAttribute()
    {
        return !empty($this->recipient_phone) ? $this->recipient_phone : ($this->customer?->phone ?? '-');
    }

    public function getFullShippingAddressAttribute()
    {
        if (!empty($this->shipping_address)) {
            return $this->shipping_address;
        }

        if (!$this->customer) return '-';

        $parts = array_filter([
            $this->customer->address,
            $this->customer->village,
            $this->customer->district,
            $this->customer->city,
            $this->customer->province
        ]);

        return !empty($parts) ? implode(', ', $parts) : '-';
    }

    /**
     * Memeriksa ketersediaan stok (ATP) untuk semua item di SO ini.
     * Jika terjadi defisit, sistem akan otomatis membuat InventoryRequest.
     * Mengembalikan boolean true jika ada defisit yang memicu pembuatan request baru.
     */
    public function checkStockAndCreateInventoryRequests(): bool
    {
        $hasDeficit = false;
        
        \Illuminate\Support\Facades\Log::info("checkStockAndCreateInventoryRequests dimulai untuk SO: {$this->so_number}");
        
        // Selalu paksa load dari database untuk memastikan kita mendapat item terbaru (baru saja diinsert)
        $this->load('items');
        
        foreach ($this->items as $item) {
            $itemModel = \Modules\Inventory\Models\Item::find($item->item_id);
            
            // Kita pass $this->id ke getATP agar SO yang saat ini sedang diproses 
            // TIDAK dihitung sebagai beban (karena kita sedang mengecek defisit untuk memenuhinya).
            $actualAtp = $itemModel ? $itemModel->getATP($this->id) : 0;
            
            \Illuminate\Support\Facades\Log::info("Item: {$item->item_id}, QTY Req: {$item->qty}, Actual ATP (tanpa SO ini): {$actualAtp}");
            
            if ($item->qty > $actualAtp) {
                $deficit = $item->qty - $actualAtp;
                
                // Pastikan belum ada antrean untuk item ini dari SO ini
                $existingRequest = \Modules\Inventory\Models\InventoryRequest::where('item_id', $item->item_id)
                    ->where('source_type', 'sales')
                    ->where('reference_number', $this->so_number)
                    ->exists();
                    
                \Illuminate\Support\Facades\Log::info("Deficit: {$deficit}, Existing: " . ($existingRequest ? 'true' : 'false'));
                    
                if (!$existingRequest) {
                    \Modules\Inventory\Models\InventoryRequest::create([
                        'item_id' => $item->item_id,
                        'source_type' => 'sales',
                        'reference_number' => $this->so_number,
                        'requested_qty' => $deficit,
                        'notes' => 'Defisit stok untuk pesanan pelanggan (ATP: ' . $actualAtp . ', Dipesan: ' . $item->qty . ')' . 
                                   (!empty($item->custom_attributes) || !empty($item->custom_attachments) ? ' [CUSTOM]' : ''),
                        'status' => 'draft',
                    ]);
                    $hasDeficit = true;
                    \Illuminate\Support\Facades\Log::info("Created InventoryRequest for {$deficit} qty.");
                }
            }
        }
        
        return $hasDeficit;
    }
}
