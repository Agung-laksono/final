<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Bersihkan data lama (Karena kita transisi ke sistem BREAD)
        \DB::table('role_has_permissions')->delete();
        Permission::query()->delete();
        Role::query()->delete();

        // Buat Permissions Dasar (Hierarki 3-Bagian untuk Inventory)
        $permissions = [
            // Kunci Utama Modul
            'inventory.view',
            'inventory.create', // Master data dasar (opsional)
            'inventory.update',
            'inventory.delete',

            // Sub-Menu: Barang
            'inventory.item.view',
            'inventory.item.create',
            'inventory.item.update',
            'inventory.item.delete',

            // Sub-Menu: Gudang
            'inventory.warehouse.view',
            'inventory.warehouse.create',
            'inventory.warehouse.update',
            'inventory.warehouse.delete',

            // Sub-Menu: Transfer Barang
            'inventory.transfer.view',
            'inventory.transfer.create',
            'inventory.transfer.update',
            'inventory.transfer.delete',

            // Sub-Menu: Riwayat Mutasi
            'inventory.movement.view',

            // Sub-Menu: Opname
            'inventory.opname.view',
            'inventory.opname.create',
            'inventory.opname.update',
            'inventory.opname.delete',
            
            // Sub-Menu: Notifikasi
            'inventory.notifikasi.view',
            'sales.notifikasi.view',
            'users.notifikasi.view',
            'production.notifikasi.view',
            'finance.notifikasi.view',
            'purchase.notifikasi.view',
            'hr.notifikasi.view',
            'marketing.notifikasi.view',
            'admin.notifikasi.view',            
            
            // Modul Pembelian (Purchase)
            'purchase.dashboard.view',
            'purchase.queue.view',
            'purchase.queue.create',
            'purchase.queue.update',
            'purchase.queue.delete',
            'purchase.approve.view',
            'purchase.approve.update',
            'purchase.approve.delete',
            'purchase.order.view',
            'purchase.order.create',
            'purchase.order.update',
            'purchase.order.delete',
            'purchase.vendor.view',
            'purchase.vendor.create',
            'purchase.vendor.update',
            'purchase.vendor.delete',

            // Modul Sales
            'sales.dashboard.view',
            'sales.customer.view',
            'sales.customer.create',
            'sales.customer.update',
            'sales.customer.delete',
            'sales.order.view',
            'sales.order.create',
            'sales.order.update',
            'sales.order.delete',
            'sales.approve.update',
            'sales.payment.create', // Sales bisa input pembayaran
            'sales.payment.validate', // Finance bisa validasi pembayaran

            // Modul Pegawai / Settings
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Profil Pengguna
            'profile.view',
            'profile.update',
            'profile.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Buat Roles dan berikan permissions
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        // Super Admin mendapatkan semua permission via Gate::before di AppServiceProvider

        $roleManager = Role::firstOrCreate(['name' => 'Manager']);
        $roleManager->givePermissionTo(Permission::all()); // Manager dapat semua hak (tapi bisa disesuaikan nanti)

        $roleGudang = Role::firstOrCreate(['name' => 'Gudang']);
        $roleGudang->givePermissionTo([
            'inventory.view', // Kunci masuk
            'inventory.item.view', 'inventory.item.create', 'inventory.item.update',
            'inventory.warehouse.view',
            'inventory.transfer.view', 'inventory.transfer.create', 'inventory.transfer.update',
            'inventory.movement.view',
            'inventory.opname.view', 'inventory.opname.create', 'inventory.opname.update',
            'purchase.queue.view', // Gudang bisa melihat antrean
            'sales.order.view', 'sales.order.update', // Gudang butuh ini untuk memproses Fulfillment
            // Gudang tidak punya hak delete apapun
        ]);

        $rolePurchasing = Role::firstOrCreate(['name' => 'Purchasing']);
        $rolePurchasing->givePermissionTo([
            'purchase.dashboard.view',
            'purchase.queue.view', 'purchase.queue.create', 'purchase.queue.update', 'purchase.queue.delete',
            'purchase.order.view', 'purchase.order.create', 'purchase.order.update', 'purchase.order.delete',
            'purchase.vendor.view', 'purchase.vendor.create', 'purchase.vendor.update', 'purchase.vendor.delete',
            'purchase.notifikasi.view',
            'inventory.view',
            'inventory.item.view',
            'inventory.warehouse.view'
        ]);

        $roleSales = Role::firstOrCreate(['name' => 'Sales']);
        $roleSales->givePermissionTo([
            'sales.dashboard.view',
            'sales.customer.view', 'sales.customer.create', 'sales.customer.update', 'sales.customer.delete',
            'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.delete',
            'sales.payment.create', // Sales bisa input bukti bayar
            'inventory.view',
            'inventory.item.view',
        ]);

        $roleKepalaSales = Role::firstOrCreate(['name' => 'Kepala Sales']);
        $roleKepalaSales->givePermissionTo([
            'sales.dashboard.view',
            'sales.customer.view', 'sales.customer.create', 'sales.customer.update', 'sales.customer.delete',
            'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.delete',
            'sales.approve.update', // Kepala Sales bisa approve (ACC) pesanan
            'sales.payment.create', // Bisa input bukti bayar juga
            'sales.notifikasi.view',
            'inventory.view',
            'inventory.item.view',
        ]);

        $roleFinance = Role::firstOrCreate(['name' => 'Finance']);
        $roleFinance->givePermissionTo([
            'sales.dashboard.view',
            'sales.order.view',
            'sales.payment.validate', // Finance yang berhak memvalidasi pembayaran
            'finance.notifikasi.view',
            'inventory.view',
            'inventory.item.view',
        ]);

        $roleMarketing = Role::firstOrCreate(['name' => 'Marketing']);
        $roleMarketing->givePermissionTo([
            'inventory.view', 
            'sales.dashboard.view'
        ]);

        // Jadikan user pertama di sistem sebagai Super Admin
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('Super Admin');
        }
    }
}
