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
            ['route_name' => 'inventory',                    'label' => 'Dashboard',          'icon' => 'chart-pie',                  'section' => 'INVENTORY',   'sort_order' => 1,  'permission' => 'inventory.view'],
            ['route_name' => 'inventory.items',              'label' => 'Barang',             'icon' => 'cube',                       'section' => 'INVENTORY',   'sort_order' => 2,  'permission' => 'inventory.item.view'],
            ['route_name' => 'inventory.warehouses',         'label' => 'Gudang',             'icon' => 'building-storefront',        'section' => 'INVENTORY',   'sort_order' => 3,  'permission' => 'inventory.warehouse.view'],
            ['route_name' => 'inventory.transfers',          'label' => 'Transfer',           'icon' => 'arrows-right-left',          'section' => 'INVENTORY',   'sort_order' => 4,  'permission' => 'inventory.transfer.view'],
            ['route_name' => 'inventory.requests',           'label' => 'Permintaan',         'icon' => 'inbox-arrow-down',           'section' => 'INVENTORY',   'sort_order' => 5,  'permission' => 'inventory.transfer.view'],
            ['route_name' => 'inventory.dispatch',           'label' => 'Kedatangan',         'icon' => 'arrow-right-end-on-rectangle','section' => 'INVENTORY',  'sort_order' => 6,  'permission' => 'inventory.stock.create'],
            ['route_name' => 'inventory.production-receipts','label' => 'Terima QC',          'icon' => 'arrow-down-tray',            'section' => 'INVENTORY',   'sort_order' => 7,  'permission' => 'inventory.stock.create'],
            ['route_name' => 'inventory.fulfillments',       'label' => 'Pemenuhan',          'icon' => 'clipboard-document-check',   'section' => 'INVENTORY',   'sort_order' => 8,  'permission' => 'production.order.update'],
            ['route_name' => 'inventory.sales-deliveries',   'label' => 'Pengiriman',         'icon' => 'truck',                      'section' => 'INVENTORY',   'sort_order' => 9,  'permission' => 'inventory.view'],
            ['route_name' => 'inventory.movements',          'label' => 'Riwayat',            'icon' => 'clock',                      'section' => 'INVENTORY',   'sort_order' => 10, 'permission' => 'inventory.movement.view'],
            ['route_name' => 'inventory.stock-opname',       'label' => 'Opname',             'icon' => 'adjustments-horizontal',     'section' => 'INVENTORY',   'sort_order' => 11, 'permission' => 'inventory.opname.view'],

            // ── PEMBELIAN ──────────────────────────────────────────
            ['route_name' => 'purchase.index',               'label' => 'Dashboard',          'icon' => 'shopping-cart',              'section' => 'PEMBELIAN',   'sort_order' => 1,  'permission' => 'purchase.dashboard.view'],
            ['route_name' => 'purchase.queues.kanban',       'label' => 'Permintaan',         'icon' => 'queue-list',                 'section' => 'PEMBELIAN',   'sort_order' => 2,  'permission' => 'purchase.queue.view'],
            ['route_name' => 'purchase.orders.kanban',       'label' => 'Pesanan',            'icon' => 'clipboard-document-list',    'section' => 'PEMBELIAN',   'sort_order' => 3,  'permission' => 'purchase.order.view'],
            ['route_name' => 'purchase.vendors.index',       'label' => 'Vendor',             'icon' => 'building-office-2',          'section' => 'PEMBELIAN',   'sort_order' => 4,  'permission' => 'purchase.vendor.view'],

            // ── PRODUKSI ───────────────────────────────────────────
            ['route_name' => 'production.orders',            'label' => 'Jadwal',             'icon' => 'wrench-screwdriver',         'section' => 'PRODUKSI',    'sort_order' => 1,  'permission' => 'production.order.view'],
            ['route_name' => 'production.recipes',           'label' => 'Resep',              'icon' => 'document-text',              'section' => 'PRODUKSI',    'sort_order' => 2,  'permission' => 'production.order.view'],

            // ── PENJUALAN ──────────────────────────────────────────
            ['route_name' => 'sales.customers.index',        'label' => 'Pelanggan',          'icon' => 'user-group',                 'section' => 'PENJUALAN',   'sort_order' => 1,  'permission' => 'sales.customer.view'],
            ['route_name' => 'sales.orders.index',           'label' => 'Pesanan',            'icon' => 'clipboard-document-list',    'section' => 'PENJUALAN',   'sort_order' => 2,  'permission' => 'sales.order.view'],

            // ── KOMUNIKASI ─────────────────────────────────────────
            ['route_name' => 'chat.index',                   'label' => 'WhatsApp',           'icon' => 'chat-bubble-left-right',     'section' => 'KOMUNIKASI',  'sort_order' => 1,  'permission' => null],

            // ── KEUANGAN ───────────────────────────────────────────
            ['route_name' => 'finance.dashboard',            'label' => 'Buku Kas',           'icon' => 'banknotes',                  'section' => 'KEUANGAN',    'sort_order' => 1,  'permission' => 'finance.dashboard.view'],
            ['route_name' => 'finance.inbox',                'label' => 'Validasi',           'icon' => 'inbox-arrow-down',           'section' => 'KEUANGAN',    'sort_order' => 2,  'permission' => 'finance.inbox.view'],
            ['route_name' => 'finance.payables',             'label' => 'Hutang',             'icon' => 'credit-card',                'section' => 'KEUANGAN',    'sort_order' => 3,  'permission' => null],

            // ── LAINNYA ────────────────────────────────────────────
            ['route_name' => 'dashboard',                    'label' => 'Utama',              'icon' => 'home',                       'section' => 'LAINNYA',     'sort_order' => 1,  'permission' => null],
            ['route_name' => 'cms.posts.index',              'label' => 'Artikel',            'icon' => 'newspaper',                  'section' => 'LAINNYA',     'sort_order' => 2,  'permission' => null],
            ['route_name' => 'settings.index',               'label' => 'Pengaturan',         'icon' => 'cog-6-tooth',                'section' => 'LAINNYA',     'sort_order' => 3,  'permission' => null],
        ];

        foreach ($items as $item) {
            NavigationItem::updateOrCreate(
                ['route_name' => $item['route_name']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
