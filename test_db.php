<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$po = \Modules\Production\Models\ProductionOrder::whereHas('item', function($q) {
    $q->where('name', 'like', '%kursi santai%');
})->first();

if (!$po) {
    echo "No PO found for kursi santai\n";
    exit;
}

echo "PO ID: {$po->id}, Target Qty: {$po->target_quantity}\n";

$recipe = \DB::table('production_recipes')->where('item_id', $po->item_id)->where('is_active', true)->first();

if (!$recipe) {
    echo "No recipe found for item_id: {$po->item_id}\n";
    exit;
}

$recipeItems = \DB::table('production_recipe_items')
    ->join('items', 'production_recipe_items.item_id', '=', 'items.id')
    ->where('production_recipe_id', $recipe->id)
    ->select('production_recipe_items.*', 'items.name')
    ->get();

foreach ($recipeItems as $ri) {
    $needed = $ri->qty * $po->target_quantity;
    $stock = \DB::table('item_warehouse')
        ->where('item_id', $ri->item_id)
        ->sum('stock') ?? 0;
    
    echo "Material: {$ri->name} (Needed: {$needed}, Stock: {$stock})\n";
}

