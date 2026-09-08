<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Finance\Models\FinanceCategory;
use Modules\Finance\Models\FinanceTransaction;

$incomeCategory = FinanceCategory::where('name', 'Pendapatan Penjualan')->first();
$expenseCategory = FinanceCategory::where('name', 'Bahan Baku & Material')->first();

$updatedIncome = 0;
$updatedExpense = 0;

if ($incomeCategory) {
    $updatedIncome = FinanceTransaction::whereNull('finance_category_id')
        ->where('reference_type', 'Modules\Sales\Models\SalesPayment')
        ->update(['finance_category_id' => $incomeCategory->id]);
}

if ($expenseCategory) {
    $updatedExpense = FinanceTransaction::whereNull('finance_category_id')
        ->where('reference_type', 'Modules\Purchase\Models\PurchasePayment')
        ->update(['finance_category_id' => $expenseCategory->id]);
}

echo "Fixed historical transactions:\n";
echo "Assigned {$updatedIncome} sales payments to 'Pendapatan Penjualan'.\n";
echo "Assigned {$updatedExpense} purchase payments to 'Bahan Baku & Material'.\n";
