<?php

// SO Print
$c = file_get_contents(__DIR__ . '/Modules/Sales/resources/views/quotation-print.blade.php');
$c = str_replace('$quotation', '$salesOrder', $c);
$c = str_replace('quotation_number', 'so_number', $c);
$c = str_replace('quotation_date', 'order_date', $c);
$c = str_replace('Penawaran', 'Sales Order', $c);
$c = str_replace('sales.quotations.index', 'sales.orders.index', $c);
$c = str_replace('Cetak Penawaran', 'Cetak Order', $c);
file_put_contents(__DIR__ . '/Modules/Sales/resources/views/sales-order-print.blade.php', $c);

// PO Print
$c = file_get_contents(__DIR__ . '/Modules/Sales/resources/views/quotation-print.blade.php');
$c = str_replace('$quotation', '$purchaseOrder', $c);
$c = str_replace('quotation_number', 'po_number', $c);
$c = str_replace('quotation_date', 'order_date', $c);
$c = str_replace('Penawaran', 'Purchase Order', $c);
$c = str_replace('sales.quotations.index', 'purchase.orders.kanban', $c); // wait, PO uses kanban
$c = str_replace('Cetak Penawaran', 'Cetak PO', $c);
$c = str_replace('customer', 'vendor', $c);
$c = str_replace('customer_name', 'vendor_name', $c);
$c = str_replace('customer_phone', 'vendor_phone', $c);
file_put_contents(__DIR__ . '/Modules/Purchase/resources/views/purchase-order-print.blade.php', $c);

echo "Done\n";
