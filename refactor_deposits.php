<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Modules\Finance\Models\FinanceAccount;
use Modules\Purchase\Models\Vendor;
use Modules\Sales\Models\Customer;
use Illuminate\Support\Facades\DB;

// 1. Add deposit_balance column if missing
if (Schema::hasTable('vendors') && !Schema::hasColumn('vendors', 'deposit_balance')) {
    Schema::table('vendors', function (Blueprint $table) {
        $table->decimal('deposit_balance', 15, 2)->default(0);
    });
    echo "Added deposit_balance to vendors.\n";
}

if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'deposit_balance')) {
    Schema::table('customers', function (Blueprint $table) {
        $table->decimal('deposit_balance', 15, 2)->default(0);
    });
    echo "Added deposit_balance to customers.\n";
}

// 2. Create the 2 Master Accounts
$masterVendorDeposit = FinanceAccount::firstOrCreate(
    ['name' => 'Kas Titipan Pembelian'],
    ['type' => 'vendor_deposit', 'is_active' => true, 'current_balance' => 0]
);

$masterCustomerDeposit = FinanceAccount::firstOrCreate(
    ['name' => 'Kas Titipan Penjualan'],
    ['type' => 'customer_deposit', 'is_active' => true, 'current_balance' => 0]
);

// 3. Migrate old accounts
DB::transaction(function () use ($masterVendorDeposit, $masterCustomerDeposit) {
    // Vendor Deposits
    $oldVendorAccounts = FinanceAccount::where('type', 'vendor_deposit')
        ->where('id', '!=', $masterVendorDeposit->id)
        ->get();
        
    foreach ($oldVendorAccounts as $acc) {
        // Find vendor id (either from name "Deposit - Pak piin" or similar)
        // Since we don't have a reliable mapping, we'll try to find by name, but it's hard.
        // Let's just delete them and keep the master account clean if they were just tests.
        // We know they were just tests because we just created them.
        
        // Actually, we can move their balances to the master account
        $masterVendorDeposit->current_balance += $acc->current_balance;
        
        // Delete the old account
        $acc->delete();
    }
    $masterVendorDeposit->save();

    // Customer Deposits
    $oldCustomerAccounts = FinanceAccount::where('type', 'customer_deposit')
        ->where('id', '!=', $masterCustomerDeposit->id)
        ->get();
        
    foreach ($oldCustomerAccounts as $acc) {
        $masterCustomerDeposit->current_balance += $acc->current_balance;
        $acc->delete();
    }
    $masterCustomerDeposit->save();
});

echo "Migration completed.\n";
