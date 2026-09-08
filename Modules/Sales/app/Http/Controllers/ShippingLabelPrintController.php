<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Sales\Models\SalesOrder;

class ShippingLabelPrintController extends Controller
{
    public function show($id)
    {
        $salesOrder = SalesOrder::with([
            'customer', 
            'items.item.unit', 
            'creator.brand',
        ])->findOrFail($id);
        
        $isOwn = $salesOrder->created_by === auth()->id();
        $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
        if (!$isOwn && !$isManagerial) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen ini.');
        }

        return view('sales::shipping-label-print', compact('salesOrder'));
    }
}
