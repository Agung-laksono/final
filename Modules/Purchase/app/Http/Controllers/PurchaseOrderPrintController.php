<?php

namespace Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Purchase\Models\PurchaseOrder;

class PurchaseOrderPrintController extends Controller
{
    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::with([
            'vendor', 
            'items.item', 
            'creator.brand'
        ])->findOrFail($id);
        
        $isOwn = $purchaseOrder->created_by === auth()->id();
        $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Pembelian', 'Manager', 'Gudang', 'Shipping', 'Finance']);
        if (!$isOwn && !$isManagerial) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen pesanan pembelian ini.');
        }

        return view('purchase::purchase-order-print', compact('purchaseOrder'));
    }
}
