<?php
$dest = __DIR__ . '/resources/views/livewire/global/customer-gallery-modal.blade.php';

$content = file_get_contents($dest);

$content = str_replace('Modules\Purchase\Models\Vendor', 'Modules\Sales\Models\Customer', $content);
$content = str_replace('vendor', 'customer', $content);
$content = str_replace('Vendor', 'Customer', $content);
$content = str_replace('VENDOR', 'CUSTOMER', $content);
$content = str_replace('Pemasok', 'Pelanggan', $content);
$content = str_replace('pemasok', 'pelanggan', $content);
$content = str_replace('Data Vendor', 'Data Customer', $content);

file_put_contents($dest, $content);
echo "Done";
