<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$models = [
    "App\Models\User",
    "App\Models\Setting",
    "Modules\CMS\Models\CmsCategory",
    "Modules\CMS\Models\CmsPost",
    "Modules\Finance\Models\FinanceAccount",
    "Modules\Finance\Models\FinanceCategory",
    "Modules\Finance\Models\FinanceTransaction",
    "Modules\Finance\Models\FinanceTransfer",
    "Modules\Production\Models\ProductionOrder",
    "Modules\Production\Models\ProductionOrderHistory",
    "Modules\Production\Models\ProductionRecipe",
    "Modules\Production\Models\ProductionRecipeItem",
    "Modules\Purchase\Models\PurchaseOrder",
    "Modules\Purchase\Models\PurchaseOrderItem",
    "Modules\Purchase\Models\PurchasePayment",
    "Modules\Purchase\Models\PurchaseQueue",
    "Modules\Purchase\Models\PurchaseQueueFulfillment",
    "Modules\Purchase\Models\PurchaseReceipt",
    "Modules\Purchase\Models\PurchaseReceiptItem",
    "Modules\Purchase\Models\Vendor",
    "Modules\Sales\Models\Customer",
    "Modules\Sales\Models\SalesOrder",
    "Modules\Sales\Models\SalesOrderFulfillment",
    "Modules\Sales\Models\SalesOrderItem",
    "Modules\Sales\Models\SalesPayment",
];

foreach ($models as $model) {
    echo "Mengeksekusi indexing untuk $model...\n";
    try {
        \Illuminate\Support\Facades\Artisan::call('ai:index', ['model_class' => $model]);
        echo \Illuminate\Support\Facades\Artisan::output();
    } catch (\Exception $e) {
        echo "Gagal: " . $e->getMessage() . "\n";
    }
}
echo "\nSemua tabel selesai di-index!\n";
