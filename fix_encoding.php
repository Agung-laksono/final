<?php
$src = __DIR__ . '/Modules/Purchase/resources/views/livewire/purchase/purchase-form.blade.php';
$dest = __DIR__ . '/Modules/Sales/resources/views/livewire/sales-order/create.blade.php';

// Copy file
copy($src, $dest);

// Read
$content = file_get_contents($dest);

// Replace Models
$content = str_replace('Modules\Purchase\Models\PurchaseOrder', 'Modules\Sales\Models\SalesOrder', $content);
$content = str_replace('Modules\Purchase\Models\PurchaseOrderItem', 'Modules\Sales\Models\SalesOrderItem', $content);
$content = str_replace('Modules\Purchase\Models\Vendor', 'Modules\Sales\Models\Customer', $content);

// Replace Variables & Attributes
$content = str_replace('vendor_id', 'customer_id', $content);
$content = str_replace('selected_vendor', 'selected_customer', $content);
$content = str_replace('vendor_search_query', 'customer_search_query', $content);
$content = str_replace('show_vendor_suggestions', 'show_customer_suggestions', $content);
$content = str_replace('Vendor::', 'Customer::', $content);
$content = str_replace('vendors', 'customers', $content);
$content = str_replace('vendor', 'customer', $content);
$content = str_replace('Vendor', 'Customer', $content);
$content = str_replace('VENDOR', 'CUSTOMER', $content);
$content = str_replace('po_number', 'so_number', $content);
$content = str_replace('PO-', 'SO-', $content);
$content = str_replace('Purchase Order', 'Sales Order', $content);
$content = str_replace('purchase.order', 'sales.order', $content);
$content = str_replace('purchase.orders.kanban', 'sales.orders.kanban', $content);

// UI Text
$content = str_replace('Form Sales Order', 'Form Pembuatan Sales Order', $content);
$content = str_replace('Cari nama customer...', 'Cari nama pelanggan...', $content);
$content = str_replace('Pilih Customer', 'Pilih Pelanggan', $content);
$content = str_replace('Data Customer', 'Data Pelanggan', $content);
$content = str_replace('pemasok', 'pelanggan', $content);
$content = str_replace('purchase.customer', 'sales.customer', $content);

// DB Fields
$content = str_replace("'ongkir'", "'shipping_fee'", $content);
$content = str_replace("->ongkir", "->shipping_fee", $content);
$content = str_replace("diskon_global", "discount", $content);
$content = str_replace("pajak_nominal", "tax", $content);
$content = str_replace("pajak_persen", "tax_percent", $content);

$content = str_replace("'pajak' => \$this->tax,", "'tax' => \$this->tax,", $content);
$content = str_replace("'total_amount' => \$grandTotal,", "'total_amount' => \$grandTotal,\n                'packing_fee' => 0,\n                'payment_status' => 'unpaid',", $content);
$content = str_replace("purchase_orders", "sales_orders", $content);
$content = str_replace("purchase_order_items", "sales_order_items", $content);
$content = str_replace("purchase_order_id", "sales_order_id", $content);

file_put_contents($dest, $content);
echo "Done";
