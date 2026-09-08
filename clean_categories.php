<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Finance\Models\FinanceCategory;
use Modules\Finance\Models\FinanceTransaction;

// Get all categories to see what we have
$categories = FinanceCategory::all();

echo "Current Categories:\n";
foreach ($categories as $cat) {
    echo "ID: {$cat->id} | Name: {$cat->name} | Type: {$cat->type} | Active: {$cat->is_active}\n";
}

// Find the new standard categories
$newAtk = FinanceCategory::where('name', 'Alat Tulis Kantor (ATK)')->first();
$newExpense = FinanceCategory::where('name', 'Biaya Lain-lain (Expense)')->first();

// Old categories to map and delete
$mappings = [
    'ATK' => $newAtk,
    'Lain-lain' => $newExpense
];

echo "\nCleaning up...\n";

foreach ($mappings as $oldName => $newCat) {
    if (!$newCat) continue;
    
    $oldCats = FinanceCategory::where('name', $oldName)->get();
    foreach ($oldCats as $oldCat) {
        // Move transactions
        $updated = FinanceTransaction::where('finance_category_id', $oldCat->id)
            ->update(['finance_category_id' => $newCat->id]);
            
        echo "Moved {$updated} transactions from '{$oldName}' to '{$newCat->name}'.\n";
        
        // Delete old category
        $oldCat->delete();
        echo "Deleted old category '{$oldName}'.\n";
    }
}
echo "Done.\n";
