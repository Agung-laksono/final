<?php
$dest = __DIR__ . '/Modules/Sales/resources/views/livewire/sales-order/create.blade.php';

$content = file_get_contents($dest);

// Remove source_queues properties
$content = preg_replace("/'source_queues' => \[\]\,/", "", $content);

// Remove the validation and business logic block
$content = preg_replace("/\/\/ Validasi aturan bisnis: Qty tidak boleh kurang dari tiket antrean asal.*?if \(\!empty\(\\\$this->source_queues\)\) \{.*?\}/s", "", $content);

// Remove remaining source_queues usages inside saveCart
$content = preg_replace("/if \(\!empty\(\\\$this->source_queues\)\) \{.*?\} \}/s", "", $content);
$content = preg_replace("/\\\$this->source_queues = \[\]; \/\/ reset after success/", "", $content);

file_put_contents($dest, $content);
echo "Done";
