<?php
$path = __DIR__ . '/Modules/Sales/resources/views/livewire/sales-order/create.blade.php';
$content = file_get_contents($path);

$content = str_replace("'pajak' => \$this->tax,", "'tax' => \$this->tax,", $content);
$content = str_replace("'total_amount' => \$grandTotal,", "'total_amount' => \$grandTotal,\n                'packing_fee' => 0,\n                'payment_status' => 'unpaid',", $content);
$content = str_replace("purchase_orders", "sales_orders", $content);
$content = str_replace("purchase_order_items", "sales_order_items", $content);
$content = str_replace("purchase_order_id", "sales_order_id", $content);

// Remove PurchaseQueue related code since Sales doesn't have a queue yet
// Actually, wait, SalesOrder doesn't use PurchaseQueue at all!
// I should remove lines 90 to 127 roughly, or just let them be dead code if `queues` is not in request.

file_put_contents($path, $content);
echo "Done";
