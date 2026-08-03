<?php
use function Livewire\Volt\{state, mount, on};

state(['type' => '', 'count' => 0, 'colorClass' => 'bg-rose-500', 'positionClass' => '-left-2 -top-1.5']);

$fetchCount = function () {
    switch ($this->type) {
        case 'purchase_queue':
            $this->count = \Modules\Purchase\Models\PurchaseQueue::where('status', 'approved')->count();
            $this->colorClass = 'bg-sky-500';
            break;
        case 'purchase_order':
            $this->count = \Modules\Purchase\Models\PurchaseOrder::whereIn('status', ['draft', 'on_delivery'])->count();
            $this->colorClass = 'bg-amber-500';
            break;
        case 'sales_order':
            if (auth()->user()->can('sales.dashboard.view')) {
                $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['pending_approval'])->count();
                $this->colorClass = 'bg-rose-500';
            } elseif (auth()->user()->can('sales.order.update')) {
                $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['processing', 'packing'])->count();
                $this->colorClass = 'bg-blue-500';
            }
            break;
        case 'production_order':
            $this->count = \Modules\Production\Models\ProductionOrder::whereIn('status', ['pending_approval', 'waiting_material', 'material_fulfillment'])->count();
            $this->colorClass = 'bg-rose-500';
            break;
        case 'inventory_transfer':
            $this->count = \Modules\Inventory\Models\StockTransfer::where('status', 'in_transit')->count();
            $this->colorClass = 'bg-blue-500';
            break;
        case 'inventory_request':
            $this->count = \Modules\Inventory\Models\InventoryRequest::where('status', 'draft')->count(); 
            $this->colorClass = 'bg-amber-500';
            break;
        case 'inventory_item':
            $this->count = \Modules\Inventory\Models\Item::where('is_approved', false)->count(); 
            $this->colorClass = 'bg-amber-500';
            break;
        case 'inventory_inbound':
            $this->count = \Modules\Production\Models\ProductionOrder::where('status', 'receiving')->whereNull('target_warehouse_id')->count();
            $this->colorClass = 'bg-amber-500';
            break;
        case 'inventory_physical_receipt':
            $this->count = \Modules\Production\Models\ProductionOrder::where('status', 'receiving')->whereNotNull('target_warehouse_id')->count();
            $this->colorClass = 'bg-amber-500';
            break;
        case 'inventory_production_fulfillment':
            $this->count = \Modules\Production\Models\ProductionOrder::whereIn('status', ['waiting_material', 'material_fulfillment'])->count();
            $this->colorClass = 'bg-amber-500';
            break;
        case 'inventory_sales_delivery':
            $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['processing', 'packing'])->count();
            $this->colorClass = 'bg-amber-500';
            break;
            
        case 'finance_payables':
            $this->count = \Modules\Purchase\Models\PurchaseOrder::where('status', 'pending_approval')->count();
            $this->colorClass = 'bg-rose-500';
            break;
            
        case 'finance_inbox':
            $this->count = \Modules\Sales\Models\SalesPayment::where('status', 'pending')->count() +
                           \Modules\Purchase\Models\PurchasePayment::where('status', 'pending')->count();
            $this->colorClass = 'bg-rose-500';
            break;
            
        case 'finance_transfer':
            $this->count = \Modules\Finance\Models\FinanceTransfer::where('status', 'pending')
                ->whereHas('toAccount', function($q) {
                    if (!auth()->user()->hasRole('Super Admin')) {
                        $q->where('user_id', auth()->id());
                    }
                })->count();
            $this->colorClass = 'bg-rose-500';
            break;

        case 'module_inventory':
            $unapprovedItems = auth()->user()->can('inventory.item.update') ? \Modules\Inventory\Models\Item::where('is_approved', false)->count() : 0;
            $inboundReceipts = auth()->user()->can('inventory.receipt.view') ? \Modules\Production\Models\ProductionOrder::where('status', 'receiving')->count() : 0;
            $outboundDeliveries = auth()->user()->can('inventory.sales.delivery') ? \Modules\Sales\Models\SalesOrder::whereIn('status', ['processing', 'packing'])->count() : 0;
            $productionNeeds = auth()->user()->can('inventory.production.fulfillment') ? \Modules\Production\Models\ProductionOrder::whereIn('status', ['waiting_material', 'material_fulfillment'])->count() : 0;
            $inTransit = auth()->user()->can('inventory.transfer.view') ? \Modules\Inventory\Models\StockTransfer::where('status', 'in_transit')->count() : 0;
            $draftRequests = auth()->user()->can('inventory.request.view') ? \Modules\Inventory\Models\InventoryRequest::where('status', 'draft')->count() : 0;

            $this->count = $unapprovedItems + $inboundReceipts + $outboundDeliveries + $productionNeeds + $inTransit + $draftRequests;
            $this->colorClass = 'bg-amber-500';
            break;
            
        case 'module_purchase':
            $pendingQueue = \Modules\Purchase\Models\PurchaseQueue::where('status', 'approved')->count();
            $draftOrders = \Modules\Purchase\Models\PurchaseOrder::where('status', 'draft')->count();
            
            $this->count = $pendingQueue + $draftOrders;
            $this->colorClass = 'bg-sky-500';
            break;
            
        case 'module_sales':
            if (auth()->user()->can('sales.dashboard.view')) {
                $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['pending_approval'])->count();
                $this->colorClass = 'bg-emerald-500';
            } elseif (auth()->user()->can('sales.order.update')) {
                $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['processing', 'packing'])->count();
                $this->colorClass = 'bg-emerald-500';
            }
            break;
        case 'module_production':
            $this->count = \Modules\Production\Models\ProductionOrder::whereIn('status', ['pending_approval', 'waiting_material', 'material_fulfillment'])->count();
            $this->colorClass = 'bg-purple-500';
            break;
        case 'module_finance':
            $pendingTransfers = \Modules\Finance\Models\FinanceTransfer::where('status', 'pending')
                ->whereHas('toAccount', function($q) {
                    if (!auth()->user()->hasRole('Super Admin')) {
                        $q->where('user_id', auth()->id());
                    }
                })->count();
                
            $pendingApprovalPOs = \Modules\Purchase\Models\PurchaseOrder::where('status', 'pending_approval')->count();
                
            $this->count = \Modules\Sales\Models\SalesPayment::where('status', 'pending')->count() +
                           \Modules\Purchase\Models\PurchasePayment::where('status', 'pending')->count() +
                           $pendingTransfers + $pendingApprovalPOs;
            $this->colorClass = 'bg-blue-500';
            break;
            
        case 'total_all':
            // Inventory
            $inventoryCount = (auth()->user()->can('inventory.item.update') ? \Modules\Inventory\Models\Item::where('is_approved', false)->count() : 0) +
                              (auth()->user()->can('inventory.receipt.view') ? \Modules\Production\Models\ProductionOrder::where('status', 'receiving')->count() : 0) +
                              (auth()->user()->can('inventory.sales.delivery') ? \Modules\Sales\Models\SalesOrder::whereIn('status', ['processing', 'packing'])->count() : 0) +
                              (auth()->user()->can('inventory.production.fulfillment') ? \Modules\Production\Models\ProductionOrder::whereIn('status', ['waiting_material', 'material_fulfillment'])->count() : 0) +
                              (auth()->user()->can('inventory.transfer.view') ? \Modules\Inventory\Models\StockTransfer::where('status', 'in_transit')->count() : 0) +
                              (auth()->user()->can('inventory.request.view') ? \Modules\Inventory\Models\InventoryRequest::where('status', 'draft')->count() : 0);

            // Purchase
            $purchaseCount = 0;
            if (auth()->user()->can('purchase.queue.view')) {
                $purchaseCount += \Modules\Purchase\Models\PurchaseQueue::where('status', 'approved')->count();
            }
            if (auth()->user()->can('purchase.order.view')) {
                $purchaseCount += \Modules\Purchase\Models\PurchaseOrder::where('status', 'draft')->count();
            }

            // Sales
            $salesCount = 0;
            if (auth()->user()->can('sales.dashboard.view')) {
                $salesCount = \Modules\Sales\Models\SalesOrder::whereIn('status', ['pending_approval'])->count();
            }

            // Production
            $productionCount = 0;
            if (auth()->user()->can('production.order.view')) {
                $productionCount = \Modules\Production\Models\ProductionOrder::whereIn('status', ['pending_approval', 'waiting_material', 'material_fulfillment'])->count();
            }

            // Finance
            $financeCount = 0;
            if (auth()->user()->can('finance.inbox.view')) {
                $financeCount += \Modules\Sales\Models\SalesPayment::where('status', 'pending')->count() +
                                 \Modules\Purchase\Models\PurchasePayment::where('status', 'pending')->count();
            }
            if (auth()->user()->can('purchase.order.update')) { // permission to approve PO in finance
                $financeCount += \Modules\Purchase\Models\PurchaseOrder::where('status', 'pending_approval')->count();
            }
            if (auth()->user()->can('finance.transfers.view')) {
                $financeCount += \Modules\Finance\Models\FinanceTransfer::where('status', 'pending')
                    ->whereHas('toAccount', function($q) {
                        if (!auth()->user()->hasRole('Super Admin')) {
                            $q->where('user_id', auth()->id());
                        }
                    })->count();
            }

            $this->count = $inventoryCount + $purchaseCount + $salesCount + $productionCount + $financeCount;
            $this->colorClass = 'bg-red-500';
            break;
    }
};

mount(function ($type, $positionClass = '-left-2 -top-1.5') {
    $this->type = $type;
    $this->positionClass = $positionClass;
    $this->fetchCount();
});

on([
    'status-updated' => function() {
        $this->fetchCount();
    },
    'echo:kanban,KanbanUpdated' => function($event) {
        $this->fetchCount();
    },
    // Listen for global badge updates from the UpdatesMenuBadges trait
    'echo:global-updates,.MenuBadgesUpdated' => function() {
        $this->fetchCount();
        // Request a refresh since fetchCount only updates internal state
        $this->dispatch('$refresh');
    }
]);
?>
<div style="display: contents;">
    @if($count > 0)
        <span class="absolute {{ $positionClass }} flex h-[18px] min-w-[18px] items-center justify-center rounded-full {{ $colorClass ?? 'bg-rose-500' }} border-[2px] border-zinc-50 dark:border-zinc-900 text-[9px] font-extrabold text-white shadow-sm pointer-events-none z-10 leading-none px-1">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</div>
