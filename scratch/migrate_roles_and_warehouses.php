<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;

echo "1. Menjalankan ulang RolePermissionSeeder...\n";
Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
echo Artisan::output();

echo "2. Menyesuaikan ulang jabatan (Role) ke User berdasarkan email...\n";

$roleMap = [
    'manager@a.com' => 'Manager',
    'gudang@a.com' => 'Kepala Gudang',
    'purchasing@a.com' => 'Kepala Purchasing',
    'sales@a.com' => 'Staf Sales',
    'kepalasales@a.com' => 'Kepala Sales',
    'finance@a.com' => 'Kepala Finance',
    'marketing@a.com' => 'Staf Marketing',
    'stafgudang@a.com' => 'Staf Gudang',
    'admin@a.com' => 'Super Admin',
    // Existing users based on previous dump
    'amel@a.com' => 'Kepala Gudang',
    'fitri@a.com' => 'Kepala Finance',
    'yasa@a.com' => 'Staf Finance',
    'staf.finance@a.com' => 'Staf Finance',
];

$allWarehouses = Warehouse::pluck('id')->toArray();

foreach (User::all() as $user) {
    if (isset($roleMap[$user->email])) {
        $user->syncRoles([$roleMap[$user->email]]);
        echo " - " . $user->name . " -> Diberi role: " . $roleMap[$user->email] . "\n";
    }

    // Default: beri akses ke semua gudang agar tidak error saat mereka login pertama kali
    $user->warehouses()->sync($allWarehouses);
}

echo "\nSelesai! Migrasi berhasil.\n";
