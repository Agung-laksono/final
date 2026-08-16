<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use Illuminate\Database\Seeder;

class NavigationItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ── INVENTORY ─────────────────────────────────────────
            // COLUMN 1
            ['route_name' => 'inventory.warehouses',         'label' => 'Gudang & Lokasi',    'icon' => 'building-storefront',        'section' => 'INVENTORY',   'sub_group' => 'Master Data',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => 'inventory.warehouse.view'],
            ['route_name' => 'inventory.items',              'label' => 'Data Barang',        'icon' => 'cube',                       'section' => 'INVENTORY',   'sub_group' => 'Master Data',             'badge_type' => 'inventory_item',                       'menu_column' => 1, 'sort_order' => 2,  'permission' => 'inventory.item.view'],
            ['route_name' => 'inventory',                    'label' => 'Dashboard Inventory', 'icon' => 'chart-pie',                  'section' => 'INVENTORY',   'sub_group' => 'Master Data',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 3,  'permission' => 'inventory.dashboard.view'],
            
            // COLUMN 2
            ['route_name' => 'inventory.dispatch',           'label' => 'Alokasi Kedatangan', 'icon' => 'arrow-right-end-on-rectangle','section' => 'INVENTORY',  'sub_group' => 'Inbound (Masuk)',         'badge_type' => 'inventory_inbound',                    'menu_column' => 2, 'sort_order' => 1,  'permission' => 'inventory.stock.create'],
            ['route_name' => 'inventory.production-receipts','label' => 'Penerimaan Fisik (QC)', 'icon' => 'arrow-down-tray',         'section' => 'INVENTORY',   'sub_group' => 'Inbound (Masuk)',         'badge_type' => 'inventory_physical_receipt',           'menu_column' => 2, 'sort_order' => 2,  'permission' => 'inventory.stock.create'],
            ['route_name' => 'inventory.requests',           'label' => 'Permintaan Barang',  'icon' => 'inbox-arrow-down',           'section' => 'INVENTORY',   'sub_group' => 'Transfer & Permintaan',   'badge_type' => 'inventory_request',                    'menu_column' => 2, 'sort_order' => 3,  'permission' => 'inventory.request.view'],
            ['route_name' => 'inventory.transfers',          'label' => 'Transfer Barang',    'icon' => 'arrows-right-left',          'section' => 'INVENTORY',   'sub_group' => 'Transfer & Permintaan',   'badge_type' => 'inventory_transfer',                   'menu_column' => 2, 'sort_order' => 4,  'permission' => 'inventory.transfer.view'],
            
            // COLUMN 3
            ['route_name' => 'inventory.fulfillments',       'label' => 'Pemenuhan Produksi', 'icon' => 'clipboard-document-check',   'section' => 'INVENTORY',   'sub_group' => 'Outbound (Keluar)',       'badge_type' => 'inventory_production_fulfillment',     'menu_column' => 3, 'sort_order' => 1,  'permission' => 'inventory.production.fulfillment'],
            ['route_name' => 'inventory.sales-deliveries',   'label' => 'Pengiriman Penjualan', 'icon' => 'truck',                    'section' => 'INVENTORY',   'sub_group' => 'Outbound (Keluar)',       'badge_type' => 'inventory_sales_delivery',             'menu_column' => 3, 'sort_order' => 2,  'permission' => 'inventory.sales.delivery'],
            ['route_name' => 'inventory.movements',          'label' => 'Riwayat Mutasi',     'icon' => 'clock',                      'section' => 'INVENTORY',   'sub_group' => 'Laporan & Stok',          'badge_type' => null,                                   'menu_column' => 3, 'sort_order' => 3,  'permission' => 'inventory.movement.view'],
            ['route_name' => 'inventory.stock-opname',       'label' => 'Opname',             'icon' => 'adjustments-horizontal',     'section' => 'INVENTORY',   'sub_group' => 'Laporan & Stok',          'badge_type' => null,                                   'menu_column' => 3, 'sort_order' => 4,  'permission' => 'inventory.opname.view'],

            // ── PEMBELIAN ──────────────────────────────────────────
            ['route_name' => 'purchase.vendors.index',       'label' => 'Data Vendor',        'icon' => 'building-office-2',          'section' => 'PEMBELIAN',   'sub_group' => 'Master Data',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => 'purchase.vendor.view'],
            ['route_name' => 'purchase.orders.create',       'label' => 'Buat PO Baru',       'icon' => 'plus',                       'section' => 'PEMBELIAN',   'sub_group' => 'Transaksi',               'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 2,  'permission' => 'purchase.order.create'],
            ['route_name' => 'purchase.orders.kanban',       'label' => 'Daftar Purchase Order', 'icon' => 'clipboard-document-list', 'section' => 'PEMBELIAN',   'sub_group' => 'Transaksi',               'badge_type' => 'purchase_order',                       'menu_column' => 1, 'sort_order' => 3,  'permission' => 'purchase.order.view'],
            ['route_name' => 'purchase.queues.kanban',       'label' => 'Daftar Permintaan',  'icon' => 'queue-list',                 'section' => 'PEMBELIAN',   'sub_group' => 'Transaksi',               'badge_type' => 'purchase_queue',                       'menu_column' => 1, 'sort_order' => 4,  'permission' => 'purchase.queue.view'],
            ['route_name' => 'purchase.index',               'label' => 'Dashboard Pembelian', 'icon' => 'shopping-cart',              'section' => 'PEMBELIAN',   'sub_group' => 'Dashboard',               'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 5,  'permission' => 'purchase.dashboard.view'],
            ['route_name' => 'purchase.returns.index',       'label' => 'Retur Pembelian',    'icon' => 'arrow-uturn-left',           'section' => 'PEMBELIAN',   'sub_group' => null,                      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 6,  'permission' => 'purchase.return.view'],

            // ── PRODUKSI ───────────────────────────────────────────
            ['route_name' => 'production.recipes',           'label' => 'Resep Produksi',     'icon' => 'document-text',              'section' => 'PRODUKSI',    'sub_group' => 'Master Data',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => 'production.order.view'],
            ['route_name' => 'production.orders',            'label' => 'Daftar Produksi',    'icon' => 'wrench-screwdriver',         'section' => 'PRODUKSI',    'sub_group' => 'Transaksi',               'badge_type' => 'production_order',                     'menu_column' => 1, 'sort_order' => 2,  'permission' => 'production.order.view'],

            // ── PENJUALAN ──────────────────────────────────────────
            ['route_name' => 'sales.customers.index',        'label' => 'Data Pelanggan',     'icon' => 'user-group',                 'section' => 'PENJUALAN',   'sub_group' => 'Master Data',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => 'sales.customer.view'],
            ['route_name' => 'sales.quotations.index',       'label' => 'Daftar Penawaran',   'icon' => 'document-text',              'section' => 'PENJUALAN',   'sub_group' => 'Transaksi',               'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 2,  'permission' => 'sales.quotation.view'],
            ['route_name' => 'sales.orders.create',          'label' => 'Buat SO Baru',       'icon' => 'plus',                       'section' => 'PENJUALAN',   'sub_group' => 'Transaksi',               'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 3,  'permission' => 'sales.order.create'],
            ['route_name' => 'sales.orders.index',           'label' => 'Daftar Sales Order', 'icon' => 'clipboard-document-list',    'section' => 'PENJUALAN',   'sub_group' => 'Transaksi',               'badge_type' => 'sales_order',                          'menu_column' => 1, 'sort_order' => 4,  'permission' => 'sales.order.view'],
            ['route_name' => 'sales.returns.index',          'label' => 'Retur Penjualan',    'icon' => 'arrow-uturn-left',           'section' => 'PENJUALAN',   'sub_group' => null,                      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 5,  'permission' => 'sales.return.view'],

            // ── KOMUNIKASI ─────────────────────────────────────────
            ['route_name' => 'chat.index',                   'label' => 'Chat WhatsApp',      'icon' => 'chat-bubble-left-right',     'section' => 'KOMUNIKASI',  'sub_group' => null,                      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => null],

            // ── KEUANGAN ───────────────────────────────────────────
            ['route_name' => 'finance.inbox',                'label' => 'Validasi Transaksi', 'icon' => 'inbox-arrow-down',           'section' => 'KEUANGAN',    'sub_group' => 'Operasional',             'badge_type' => 'finance_inbox',                        'menu_column' => 1, 'sort_order' => 1,  'permission' => 'finance.inbox.view'],
            ['route_name' => 'finance.payables',             'label' => 'Hutang Pembelian',   'icon' => 'credit-card',                'section' => 'KEUANGAN',    'sub_group' => 'Operasional',             'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 2,  'permission' => 'finance.payables.view'],
            ['route_name' => 'finance.transfers',            'label' => 'Mutasi Internal',    'icon' => 'arrows-right-left',          'section' => 'KEUANGAN',    'sub_group' => 'Operasional',             'badge_type' => 'finance_transfer',                     'menu_column' => 1, 'sort_order' => 3,  'permission' => 'finance.transfers.view'],
            ['route_name' => 'finance.dashboard',            'label' => 'Dashboard Keuangan', 'icon' => 'banknotes',                  'section' => 'KEUANGAN',    'sub_group' => 'Dashboard & Mutasi',      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 4,  'permission' => 'finance.dashboard.view'],

            // ── LAINNYA ────────────────────────────────────────────
            ['route_name' => 'dashboard',                    'label' => 'Dashboard Utama',    'icon' => 'home',                       'section' => 'LAINNYA',     'sub_group' => null,                      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 1,  'permission' => null],
            ['route_name' => 'cms.posts.index',              'label' => 'Artikel Dokumen',    'icon' => 'newspaper',                  'section' => 'LAINNYA',     'sub_group' => null,                      'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 2,  'permission' => 'cms.posts.view'],
            ['route_name' => 'settings.index',               'label' => 'Pengaturan Aplikasi','icon' => 'cog-6-tooth',                'section' => 'LAINNYA',     'sub_group' => 'Sistem',                  'badge_type' => null,                                   'menu_column' => 1, 'sort_order' => 3,  'permission' => null],
        ];

        foreach ($items as $item) {
            NavigationItem::updateOrCreate(
                ['route_name' => $item['route_name']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
