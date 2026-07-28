<?php

namespace Modules\Production\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Purchase\Models\PurchaseOrder;

class WorkOrderPrintController extends Controller
{
    public function show($id)
    {
        $po = PurchaseOrder::with(['vendor', 'items.item', 'creator.brand'])->findOrFail($id);
        
        $isOwn = $po->created_by === auth()->id();
        $isManagerial = auth()->user()->hasAnyRole(['Super Admin', 'Kepala Produksi', 'Manager']);
        if (!$isOwn && !$isManagerial && !auth()->user()->can('production.order.view')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen SPK ini.');
        }

        $printMode = request('mode', 'compact');

        return view('production::print-spk', compact('po', 'printMode'));
    }
}
