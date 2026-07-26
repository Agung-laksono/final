<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Quotation;

class QuotationPrintController extends Controller
{
    public function show($id)
    {
        $quotation = Quotation::with([
            'customer', 
            'items.item', 
            'creator.brand'
        ])->findOrFail($id);
        
        $isOwn = $quotation->created_by === auth()->id();
        $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Sales', 'Manager', 'Gudang', 'Shipping', 'Finance']);
        if (!$isOwn && !$isManagerial) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen penawaran ini.');
        }

        return view('sales::quotation-print', compact('quotation'));
    }
}
