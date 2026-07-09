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
            'inventory.dashboard.view',
            'inventory.view',
            'inventory.create', // Master data dasar (opsional)
            'inventory.update',
            'inventory.delete',
            
            // Sub-Menu: Stok (Penerimaan / Alokasi)
            'inventory.dispatch.view',
            'inventory.receipt.view',

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
            
            // Modul Finance
            'finance.dashboard.view',
            'finance.inbox.view',
            'finance.inbox.create',
            'finance.inbox.update',
            'finance.inbox.delete',

            'finance.accounts.view',
            'finance.accounts.create',
            'finance.accounts.update',
            'finance.accounts.delete',

            'finance.categories.view',
            'finance.categories.create',
            'finance.categories.update',
            'finance.categories.delete',

            'finance.ledger.view',
            'finance.ledger.create',
            'finance.ledger.update',
            'finance.ledger.delete',

            'finance.transfers.view',
            'finance.transfers.create',
            'finance.transfers.update',
            'finance.transfers.delete',
            
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
            'settings.view', // Akses menu pengaturan aplikasi
            'dashboard.main.view', // Akses halaman home/dashboard utama
            
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Profil Pengguna
            'profile.view',
            'profile.update',
            'profile.delete',

            // Modul Produksi
            'production.dashboard.view',
            'production.order.view',
            'production.order.create',
            'production.order.update',
            'production.order.delete',
            'production.recipe.view',
            'production.recipe.create',
            'production.recipe.update',
            'production.recipe.delete',

            // Permintaan Barang (Inventory Pivot)
            'inventory.request.view',
            'inventory.request.create',
            'inventory.request.update',
            'inventory.request.delete',

            // Fulfillment Gudang (Permission KHUSUS, terpisah dari Sales & Produksi)
            'inventory.sales.delivery',        // Akses menu Pengiriman Penjualan (Gudang saja)
            'inventory.production.fulfillment', // Akses menu Pemenuhan Produksi (Gudang saja)
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Buat Roles dan berikan permissions
        $roleSuperAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        // Super Admin mendapatkan semua permission via Gate::before di AppServiceProvider

        $roleManager = Role::firstOrCreate(['name' => 'Manager']);
        $roleManager->givePermissionTo(Permission::all());

        // --- DIVISI GUDANG (Termasuk Shipping) ---
        $roleKepalaGudang = Role::firstOrCreate(['name' => 'Kepala Gudang']);
        $roleKepalaGudang->givePermissionTo([
            'dashboard.main.view',
            'inventory.dashboard.view', 'inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete',
            'inventory.dispatch.view', 'inventory.receipt.view',
            'inventory.item.view', 'inventory.item.create', 'inventory.item.update', 'inventory.item.delete',
            'inventory.warehouse.view', 'inventory.warehouse.create', 'inventory.warehouse.update', 'inventory.warehouse.delete',
            'inventory.transfer.view', 'inventory.transfer.create', 'inventory.transfer.update', 'inventory.transfer.delete',
            'inventory.movement.view',
            'inventory.opname.view', 'inventory.opname.create', 'inventory.opname.update', 'inventory.opname.delete',
            'inventory.request.view', 'inventory.request.create', 'inventory.request.update', 'inventory.request.delete',
            'inventory.sales.delivery', 'inventory.production.fulfillment',
            'inventory.notifikasi.view',
            'purchase.queue.view',
            'sales.order.view',
        ]);

        $roleStafGudang = Role::firstOrCreate(['name' => 'Staf Gudang']);
        $roleStafGudang->givePermissionTo([
            'dashboard.main.view',
            'inventory.dashboard.view', 'inventory.view',
            'inventory.receipt.view',
            'inventory.item.view',
            'inventory.warehouse.view',
            'inventory.transfer.view', 'inventory.transfer.create', 'inventory.transfer.update',
            'inventory.movement.view',
            'inventory.opname.view', 'inventory.opname.create', 'inventory.opname.update',
            'inventory.request.view', 'inventory.request.create', 'inventory.request.update',
            'production.order.view',
            'inventory.production.fulfillment', // Pemenuhan bahan baku produksi
            'sales.order.view',
            'inventory.sales.delivery',         // Pengiriman & pengemasan penjualan
        ]);

        $roleStafGudangPPIC = Role::firstOrCreate(['name' => 'Staf Gudang PPIC']);
        $roleStafGudangPPIC->givePermissionTo([
            'dashboard.main.view',
            'inventory.view',
            'inventory.item.view',
            'inventory.warehouse.view',
            'inventory.request.view', 'inventory.request.create', 'inventory.request.update', 'inventory.request.delete',
            'inventory.transfer.view', 'inventory.transfer.create', 'inventory.transfer.update',
            'inventory.movement.view',
            'inventory.dispatch.view',
            'production.dashboard.view', 'production.order.view', 'production.order.create', 'production.order.update',
            'production.recipe.view',
        ]);

        // Alias: Staf Gudang Fulfillment = sama dengan Staf Gudang (untuk kompatibilitas)
        $roleStafGudangFulfillment = Role::firstOrCreate(['name' => 'Staf Gudang Fulfillment']);
        $roleStafGudangFulfillment->givePermissionTo([
            'dashboard.main.view',
            'inventory.view',
            'inventory.item.view',
            'inventory.warehouse.view',
            'inventory.receipt.view',
            'inventory.request.view', 'inventory.request.update',
            'inventory.movement.view',
            'production.order.view',
            'inventory.production.fulfillment', // Pemenuhan Produksi
            'sales.order.view',
            'inventory.sales.delivery',         // Pengiriman Penjualan
        ]);

        // --- DIVISI PURCHASING ---
        $roleKepalaPurchasing = Role::firstOrCreate(['name' => 'Kepala Purchasing']);
        $roleKepalaPurchasing->givePermissionTo([
            'dashboard.main.view', 'settings.view',
            'purchase.dashboard.view',
            'purchase.queue.view', 'purchase.queue.create', 'purchase.queue.update', 'purchase.queue.delete',
            'purchase.approve.view', 'purchase.approve.update', 'purchase.approve.delete', // Hak ACC
            'purchase.order.view', 'purchase.order.create', 'purchase.order.update', 'purchase.order.delete',
            'purchase.vendor.view', 'purchase.vendor.create', 'purchase.vendor.update', 'purchase.vendor.delete',
            'purchase.notifikasi.view',
            'inventory.view', 'inventory.item.view', 'inventory.warehouse.view'
        ]);

        $roleStafPurchasing = Role::firstOrCreate(['name' => 'Staf Purchasing']);
        $roleStafPurchasing->givePermissionTo([
            'dashboard.main.view',
            'purchase.dashboard.view',
            'purchase.queue.view', 'purchase.queue.create', 'purchase.queue.update',
            'purchase.order.view', 'purchase.order.create', 'purchase.order.update',
            'purchase.vendor.view', 'purchase.vendor.create', 'purchase.vendor.update',
            'purchase.notifikasi.view',
            'inventory.view', 'inventory.item.view', 'inventory.warehouse.view'
        ]);

        // --- DIVISI SALES ---
        $roleKepalaSales = Role::firstOrCreate(['name' => 'Kepala Sales']);
        $roleKepalaSales->givePermissionTo([
            'dashboard.main.view', 'settings.view',
            'sales.dashboard.view',
            'sales.customer.view', 'sales.customer.create', 'sales.customer.update', 'sales.customer.delete',
            'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.delete',
            'sales.approve.update', // Hak ACC Pesanan
            'sales.payment.create', // Upload bukti bayar
            'sales.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        $roleStafSales = Role::firstOrCreate(['name' => 'Staf Sales']);
        $roleStafSales->givePermissionTo([
            'dashboard.main.view',
            'sales.dashboard.view',
            'sales.customer.view', 'sales.customer.create', 'sales.customer.update',
            'sales.order.view', 'sales.order.create', 'sales.order.update',
            'sales.payment.create',
            'sales.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        // --- DIVISI FINANCE ---
        $roleKepalaFinance = Role::firstOrCreate(['name' => 'Kepala Finance']);
        $roleKepalaFinance->givePermissionTo([
            'dashboard.main.view', 'settings.view',
            'finance.dashboard.view',
            'finance.inbox.view', 'finance.inbox.create', 'finance.inbox.update', 'finance.inbox.delete',
            'finance.accounts.view', 'finance.accounts.create', 'finance.accounts.update', 'finance.accounts.delete',
            'finance.categories.view', 'finance.categories.create', 'finance.categories.update', 'finance.categories.delete',
            'finance.ledger.view', 'finance.ledger.create', 'finance.ledger.update', 'finance.ledger.delete',
            'finance.transfers.view', 'finance.transfers.create', 'finance.transfers.update', 'finance.transfers.delete',
            'sales.payment.validate', // Hak validasi pembayaran sales
            'sales.order.view',
            'finance.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        $roleStafFinance = Role::firstOrCreate(['name' => 'Staf Finance']);
        $roleStafFinance->givePermissionTo([
            'dashboard.main.view',
            'finance.dashboard.view',
            'finance.accounts.view',
            'finance.categories.view',
            'finance.ledger.view', 'finance.ledger.create',
            'finance.transfers.view', 'finance.transfers.create', 'finance.transfers.update',
            'finance.inbox.view', 'finance.inbox.update',
            'sales.order.view',
            'finance.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        // --- DIVISI PRODUKSI ---
        $roleKepalaProduksi = Role::firstOrCreate(['name' => 'Kepala Produksi']);
        $roleKepalaProduksi->givePermissionTo([
            'dashboard.main.view', 'settings.view',
            'production.dashboard.view',
            'production.order.view', 'production.order.create', 'production.order.update', 'production.order.delete',
            'production.recipe.view', 'production.recipe.create', 'production.recipe.update', 'production.recipe.delete',
            'production.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        $roleStafProduksi = Role::firstOrCreate(['name' => 'Staf Produksi']);
        $roleStafProduksi->givePermissionTo([
            'dashboard.main.view',
            'production.dashboard.view',
            'production.order.view', 'production.order.update',
            'production.recipe.view',
            'production.notifikasi.view',
            'inventory.view', 'inventory.item.view'
        ]);

        // --- DIVISI MARKETING ---
        $roleKepalaMarketing = Role::firstOrCreate(['name' => 'Kepala Marketing']);
        $roleKepalaMarketing->givePermissionTo([
            'dashboard.main.view', 'settings.view',
            'sales.dashboard.view',
            'inventory.view', 'inventory.item.view',
            'marketing.notifikasi.view',
        ]);

        $roleStafMarketing = Role::firstOrCreate(['name' => 'Staf Marketing']);
        $roleStafMarketing->givePermissionTo([
            'dashboard.main.view',
            'sales.dashboard.view',
            'inventory.view', 'inventory.item.view',
            'marketing.notifikasi.view',
        ]);

        // Jadikan user pertama di sistem sebagai Super Admin
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('Super Admin');
        }
    }
}
