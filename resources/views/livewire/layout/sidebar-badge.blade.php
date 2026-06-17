<?php
use function Livewire\Volt\{state, mount, on};

state(['type' => '', 'count' => 0, 'colorClass' => 'bg-rose-500']);

$fetchCount = function () {
    switch ($this->type) {
        case 'purchase_queue':
            $this->count = \Modules\Purchase\Models\PurchaseQueue::where('status', 'pending_approval')->count();
            $this->colorClass = 'bg-rose-500';
            break;
        case 'purchase_order':
            $this->count = \Modules\Purchase\Models\PurchaseOrder::whereIn('status', ['draft', 'on_delivery'])->count();
            $this->colorClass = 'bg-amber-500';
            break;
        case 'sales_order':
            $this->count = \Modules\Sales\Models\SalesOrder::whereIn('status', ['pending_approval', 'waiting_payment'])->count();
            $this->colorClass = 'bg-rose-500';
            break;
        case 'production_order':
            $this->count = \Modules\Production\Models\ProductionOrder::whereIn('status', ['pending_approval', 'waiting_material'])->count();
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
    }
};

mount(function ($type) {
    $this->type = $type;
    $this->fetchCount();
});

on([
    'status-updated' => function() {
        $this->fetchCount();
    },
    'echo:kanban,KanbanUpdated' => function($event) {
        if (!isset($event['type']) || $event['type'] === $this->type) {
            $this->fetchCount();
        }
    }
]);
?>
<div style="display: contents;">
    @if($count > 0)
        <span class="absolute -left-2 -top-1.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full {{ $colorClass ?? 'bg-rose-500' }} border-[2px] border-zinc-50 dark:border-zinc-900 text-[9px] font-extrabold text-white shadow-sm pointer-events-none z-10 leading-none px-1">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</div>
