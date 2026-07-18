<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\SalesOrder;

class InvoiceController extends Controller
{
    public function show($id)
    {
        $order = SalesOrder::with([
            'customer', 
            'items.item', 
            'creator.brand.financeAccounts', 
            'payments', 
            'brand.financeAccounts'
        ])->findOrFail($id);
        
        if (!$order->brand && $order->creator && $order->creator->brand) {
            $order->setRelation('brand', $order->creator->brand);
        }
        $isOwn = $order->created_by === auth()->id();
        $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
        if (!$isOwn && !$isManagerial) {
            abort(403, 'Anda tidak memiliki akses untuk melihat invoice pesanan ini.');
        }

        return view('sales::invoice', compact('order'));
    }
}
