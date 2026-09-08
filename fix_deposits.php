<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$accounts = \Modules\Finance\Models\FinanceAccount::where('name', 'like', 'Deposit Vendor %')->get();
foreach ($accounts as $a) {
    $vendorId = str_replace('Deposit Vendor #', '', $a->name);
    $vendor = \Modules\Purchase\Models\Vendor::find($vendorId);
    if ($vendor) {
        $a->name = 'Deposit - ' . $vendor->name;
        $a->save();
        echo "Updated to: " . $a->name . "\n";
    }
}
