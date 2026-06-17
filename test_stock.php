<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = DB::table('item_warehouse')->where('item_id', 51)->get();
foreach($rows as $row) {
    echo "ID: {$row->id}, Item: {$row->item_id}, WH: {$row->warehouse_id}, Stock: {$row->stock}\n";
}

$labels = \Modules\Inventory\Models\ItemLabel::where('item_id', 51)->get();
foreach($labels as $l) {
    echo "Label: {$l->label_code}, WH: {$l->warehouse_id}, Status: {$l->status}\n";
}
