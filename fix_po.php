<?php
$dest = __DIR__ . '/Modules/Sales/resources/views/livewire/sales-order/create.blade.php';

$content = file_get_contents($dest);

$content = str_replace('PurchaseOrder::', 'SalesOrder::', $content);
$content = str_replace('PurchaseOrderItem::', 'SalesOrderItem::', $content);
$content = preg_replace('/PurchaseQueue::.+?;/s', '', $content); // remove PurchaseQueue logic
$content = preg_replace('/PurchaseQueueFulfillment::.+?;/s', '', $content); // remove PurchaseQueueFulfillment logic
$content = str_replace('\Modules\Purchase\Models\PurchaseQueue::', '', $content);

// also let's make the SO number generator proper
$content = preg_replace('/ODM-/', 'SO-' . date('Ymd') . '-', $content);
$content = str_replace("F l u x : : t o a s t ( ' P u r c h a s e   O r d e r", "Flux::toast('Sales Order", $content);
$content = str_replace("Purchase Order berhasil disimpan!", "Sales Order berhasil disimpan!", $content);
$content = str_replace("Purchase Order", "Sales Order", $content);

file_put_contents($dest, $content);
echo "Done";
