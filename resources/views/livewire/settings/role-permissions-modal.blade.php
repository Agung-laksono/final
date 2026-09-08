<?php

use function Livewire\Volt\{state, on};
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

state([
    'roleId' => null,
    'role' => null,
    'selectedPermissions' => [],
    'viewMode' => 'matrix', // 'matrix' atau 'detailed'
    'searchFilter' => '',
]);

$getAvailablePermissions = function () {
    return Permission::pluck('name')->toArray();
};

$getPermissionDescriptions = function () {
    return [
        // SALES MODULE
        'sales.dashboard.view' => 'Membuka Halaman Dashboard Penjualan & Analytics Omzet',
        'sales.order.view' => 'Melihat Kartu Kanban SO, Tabel SO, serta Tombol \'Preview Print Faktur / Cetak SO\'',
        'sales.order.create' => 'Menampilkan Tombol \'+ Buat Sales Order Baru\' di Kanban & Navbar',
        'sales.order.update' => 'Menampilkan Tombol \'Edit Pesanan / Ubah Item Sales Order\'',
        'sales.order.delete' => 'Menampilkan Tombol \'Batalkan / Hapus Sales Order\'',
        'sales.approve.update' => '★ Tombol Ikon Persetujuan (ACC): Menampilkan tombol icon centang di kartu Kanban SO (kolom Pending Approval) untuk ACC pesanan ke gudang',
        'sales.payment.create' => '★ Tombol Ikon Pembayaran: Menampilkan tombol icon uang (Banknotes) di footer kartu Kanban SO untuk upload bukti bayar/DP',
        'sales.payment.validate' => '★ Tombol Validasi Uang Masuk: Menampilkan tombol verifikasi pembayaran di modal pembayaran (Wewenang Finance)',
        'sales.customer.view' => 'Melihat Tabel Data Pelanggan & Detail Profil Klien',
        'sales.customer.create' => 'Menampilkan Tombol \'+ Tambah Pelanggan Baru\'',
        'sales.customer.update' => 'Menampilkan Tombol \'Edit Data Pelanggan\'',
        'sales.customer.delete' => 'Menampilkan Tombol \'Hapus Pelanggan\'',

        // INVENTORY MODULE
        'inventory.dashboard.view' => 'Membuka Halaman Dashboard & Ringkasan Statistik Stok Gudang',
        'inventory.view' => 'Memunculkan Akses Menu Utama Inventory di Navigasi',
        'inventory.item.view' => 'Melihat Galeri Barang, Tabel Katalog, serta Tombol \'Preview Print Label Barcode / Cetak Kartu Stok\'',
        'inventory.item.create' => 'Menampilkan Tombol \'+ Tambah Barang Baru\' di Galeri Item & Form Master',
        'inventory.item.update' => 'Menampilkan Tombol \'Edit Barang\' & Pengubahan Stok Minimal / Harga Jual',
        'inventory.item.delete' => 'Menampilkan Tombol \'Hapus Barang\' di Galeri & Katalog Master',
        'inventory.sales.delivery' => '★ Tombol Pengiriman Gudang: Membuka menu & tombol \'Pengeluaran Barang / Packing Pengiriman SO\' di Gudang',
        'inventory.production.fulfillment' => '★ Tombol Pemenuhan Bahan: Membuka menu & tombol \'Alokasi / Pemenuhan Bahan Baku Produksi\' di Gudang',
        'inventory.dispatch.view' => 'Melihat Sub-menu & Tombol \'Alokasi Kedatangan / Penerimaan Barang Masuk\'',
        'inventory.receipt.view' => 'Melihat Sub-menu & Tombol \'Penerimaan Barang Supplier\'',
        'inventory.warehouse.view' => 'Melihat Daftar Lokasi & Ruang Gudang',
        'inventory.warehouse.create' => 'Menampilkan Tombol \'+ Tambah Gudang Baru\'',
        'inventory.warehouse.update' => 'Menampilkan Tombol \'Edit Gudang / Kapasitas\'',
        'inventory.warehouse.delete' => 'Menampilkan Tombol \'Hapus Gudang\'',
        'inventory.transfer.view' => 'Melihat Daftar Transfer Stok Antar Gudang',
        'inventory.transfer.create' => 'Menampilkan Tombol \'+ Buat Transfer Barang\'',
        'inventory.transfer.update' => 'Menampilkan Tombol \'Proses / Kirim Transfer Barang\'',
        'inventory.transfer.delete' => 'Menampilkan Tombol \'Batalkan / Hapus Transfer\'',
        'inventory.movement.view' => 'Melihat Tabel Kartu Stok & Riwayat Mutasi Barang',
        'inventory.opname.view' => 'Melihat Sesi Stok Opname Gudang',
        'inventory.opname.create' => 'Menampilkan Tombol \'+ Sesi Opname Baru\'',
        'inventory.opname.update' => 'Menampilkan Tombol \'Input Hasil Perhitungan / Penyesuaian Stok\'',
        'inventory.opname.delete' => 'Menampilkan Tombol \'Hapus Sesi Opname\'',
        'inventory.request.view' => 'Melihat Daftar Permintaan Barang (Request Material)',
        'inventory.request.create' => 'Menampilkan Tombol \'+ Buat Permintaan Barang\'',
        'inventory.request.update' => 'Menampilkan Tombol \'Proses / Setujui Permintaan Barang\'',
        'inventory.request.delete' => 'Menampilkan Tombol \'Tolak / Hapus Permintaan Barang\'',
        'inventory.kategori.create' => 'Tombol \'+ Quick Add Kategori Baru\' di Form Modal Barang',
        'inventory.kategori.update' => 'Tombol \'Edit Kategori\' di Form Modal Barang',
        'inventory.kategori.delete' => 'Tombol \'Hapus Kategori\' di Form Modal Barang',

        // PURCHASE MODULE
        'purchase.dashboard.view' => 'Membuka Dashboard & Analytics Pembelian (Purchasing)',
        'purchase.queue.view' => 'Melihat Antrian Permintaan PO (Purchase Queue)',
        'purchase.queue.create' => 'Menampilkan Tombol \'+ Buat Request Pembelian Baru\'',
        'purchase.queue.update' => 'Menampilkan Tombol \'Edit Request Pembelian\'',
        'purchase.queue.delete' => 'Menampilkan Tombol \'Hapus Request Pembelian\'',
        'purchase.approve.view' => 'Melihat Daftar Pembelian yang Membutuhkan ACC',
        'purchase.approve.update' => '★ Tombol ACC Purchase: Menampilkan tombol centang \'ACC / Disetujui\' di kartu Purchase Queue (Wewenang Kepala Purchasing)',
        'purchase.approve.delete' => '★ Tombol Tolak Purchase: Menampilkan tombol silang \'Tolak Request PO\'',
        'purchase.order.view' => 'Melihat Kartu Kanban PO, Tabel PO, serta Tombol \'Preview Print Surat PO / Cetak Pembelian\'',
        'purchase.order.create' => 'Menampilkan Tombol \'+ Buat PO Baru\'',
        'purchase.order.update' => 'Menampilkan Tombol \'Edit Purchase Order\'',
        'purchase.order.delete' => 'Menampilkan Tombol \'Batalkan / Hapus PO\'',
        'purchase.vendor.view' => 'Melihat Daftar Supplier / Vendor',
        'purchase.vendor.create' => 'Menampilkan Tombol \'+ Tambah Vendor Baru\'',
        'purchase.vendor.update' => 'Menampilkan Tombol \'Edit Vendor\'',
        'purchase.vendor.delete' => 'Menampilkan Tombol \'Hapus Vendor\'',

        // FINANCE MODULE
        'finance.dashboard.view' => 'Membuka Dashboard Keuangan & Laporan Arus Kas',
        'finance.inbox.view' => 'Melihat Inbox Pengajuan Transaksi Keuangan',
        'finance.inbox.create' => 'Menampilkan Tombol \'+ Input Pengeluaran / Kasbon Baru\'',
        'finance.inbox.update' => '★ Tombol Validasi Inbox: Menampilkan tombol \'Setujui / Cairkan Duit\' pada Inbox Keuangan',
        'finance.inbox.delete' => 'Menampilkan Tombol \'Tolak / Hapus Pengajuan Inbox\'',
        'finance.accounts.view' => 'Melihat Daftar Akun Bank & Kas',
        'finance.accounts.create' => 'Menampilkan Tombol \'+ Tambah Rekening / Akun Kas\'',
        'finance.accounts.update' => 'Menampilkan Tombol \'Edit Rekening / Akun Kas\'',
        'finance.accounts.delete' => 'Menampilkan Tombol \'Hapus Akun Kas\'',
        'finance.categories.view' => 'Melihat Kategori Akuntansi Pemasukan & Pengeluaran',
        'finance.categories.create' => 'Menampilkan Tombol \'+ Tambah Kategori Baru\'',
        'finance.categories.update' => 'Menampilkan Tombol \'Edit Kategori\'',
        'finance.categories.delete' => 'Menampilkan Tombol \'Hapus Kategori\'',
        'finance.ledger.view' => 'Melihat Buku Besar (General Ledger) & Tombol \'Print Laporan Jurnal\'',
        'finance.ledger.create' => 'Menampilkan Tombol \'+ Tambah Jurnal Manual\'',
        'finance.ledger.update' => 'Menampilkan Tombol \'Edit Jurnal / Transaksi\'',
        'finance.ledger.delete' => 'Menampilkan Tombol \'Hapus Jurnal\'',
        'finance.transfers.view' => 'Melihat Daftar Transfer Antar Kas / Bank Internal',
        'finance.transfers.create' => 'Menampilkan Tombol \'+ Transfer Kas Internal\'',
        'finance.transfers.update' => 'Menampilkan Tombol \'Edit Transfer Kas\'',
        'finance.transfers.delete' => 'Menampilkan Tombol \'Batalkan Transfer Kas\'',
        'finance.payables.view' => 'Melihat Daftar Hutang Pembelian (Payables)',
        'finance.payables.create' => 'Menampilkan Tombol \'+ Catat Hutang Baru\'',
        'finance.payables.update' => 'Menampilkan Tombol \'Bayar / Pelunasan Hutang\'',
        'finance.payables.delete' => 'Menampilkan Tombol \'Hapus Catatan Hutang\'',

        // PRODUCTION MODULE
        'production.dashboard.view' => 'Membuka Dashboard Operasional Produksi',
        'production.order.view' => 'Melihat Perintah Produksi (Work Order), Kartu Kanban Produksi, & Tombol \'Print SPK Produksi\'',
        'production.order.create' => 'Menampilkan Tombol \'+ Buat Perintah Produksi Baru\'',
        'production.order.update' => 'Menampilkan Tombol \'Update Status WIP / Selesaikan Fase Produksi\'',
        'production.order.delete' => 'Menampilkan Tombol \'Batalkan Produksi\'',
        'production.recipe.view' => 'Melihat Resep Produksi & Bill of Materials (BOM)',
        'production.recipe.create' => 'Menampilkan Tombol \'+ Buat Resep Produksi Baru\'',
        'production.recipe.update' => 'Menampilkan Tombol \'Edit Resep & Komposisi Bahan\'',
        'production.recipe.delete' => 'Menampilkan Tombol \'Hapus Resep Produksi\'',

        // SYSTEM & SETTINGS
        'users.view' => 'Melihat Daftar Pengguna & Jabatan Sistem',
        'users.create' => 'Menampilkan Tombol \'+ Tambah User Baru\'',
        'users.update' => 'Menampilkan Tombol \'Edit User & Penugasan Role/Gudang/Brand\'',
        'users.delete' => 'Menampilkan Tombol \'Nonaktifkan / Hapus User\'',
        'settings.view' => 'Membuka Menu Pengaturan Aplikasi Utama',
        'dashboard.main.view' => 'Membuka Halaman Dashboard Utama Sistem',
        'profile.view' => 'Melihat & Mengubah Profil Akun Sendiri',
        'profile.update' => 'Memperbarui Informasi Profil & Password',
        'profile.delete' => 'Menghapus Akun Sendiri',
        'docs.view' => 'Membuka Halaman Dokumentasi & Video Panduan',
    ];
};

on(['open-role-permissions' => function (int $roleId) {
    $this->roleId = $roleId;
    $this->role = Role::with('permissions')->find($this->roleId);
    
    if ($this->role && $this->role->name !== 'Super Admin') {
        $this->selectedPermissions = $this->role->permissions->pluck('name')->toArray();
        \Flux::modal('role-permissions-modal')->show();
    }
}]);

$save = function () {
    if (!$this->roleId) return;
    
    $role = Role::find($this->roleId);
    if (!$role || $role->name === 'Super Admin') return;
    
    $role->syncPermissions($this->selectedPermissions);
    
    \Flux::toast("Wewenang untuk jabatan {$role->name} berhasil diperbarui!");
    \Flux::modal('role-permissions-modal')->close();
    
    // Memberitahu parent untuk refresh table
    $this->dispatch('permissions-updated');
};

?>

<div>
    <template x-teleport="body">
        <flux:modal name="role-permissions-modal" class="w-full" style="width: 850px; max-width: 92vw;" scroll="body" :dismissible="false">
            <div class="space-y-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <flux:heading size="lg">Atur Wewenang & Fitur Jabatan</flux:heading>
                        <flux:subheading>
                            Kelola akses fitur dan tombol operasional spesifik untuk tiap divisi.
                        </flux:subheading>
                    </div>

                    {{-- Switcher Mode Tampilan --}}
                    <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 p-1 rounded-xl border border-zinc-200 dark:border-zinc-700 shrink-0">
                        <button type="button" 
                                wire:click="$set('viewMode', 'matrix')" 
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 {{ $viewMode === 'matrix' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                            <flux:icon.table-cells class="w-3.5 h-3.5" />
                            Matriks (CRUD)
                        </button>
                        <button type="button" 
                                wire:click="$set('viewMode', 'detailed')" 
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all flex items-center gap-1.5 {{ $viewMode === 'detailed' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-zinc-100 shadow-sm' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100' }}">
                            <flux:icon.list-bullet class="w-3.5 h-3.5 text-violet-500" />
                            Detail Fitur & Tombol
                        </button>
                    </div>
                </div>

                @if($role)
                    <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-violet-50/50 dark:bg-violet-950/20 border border-violet-200/60 dark:border-violet-800/40">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-violet-600 flex items-center justify-center text-white font-bold shadow-sm">
                                <flux:icon.shield-check class="w-5 h-5" />
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Jabatan: {{ $role->name }}</span>
                                <span class="text-xs text-zinc-500">Centang wewenang untuk menampilkan tombol/akses terkait</span>
                            </div>
                        </div>

                        {{-- Search Filter Input --}}
                        <div class="w-52">
                            <flux:input wire:model.live.debounce.200ms="searchFilter" placeholder="Cari fitur/tombol..." size="sm" icon="magnifying-glass" />
                        </div>
                    </div>

                    @php
                        $permissions = $this->getAvailablePermissions();
                        $descriptions = $this->getPermissionDescriptions();
                        
                        // Filter jika ada kata pencarian
                        if (!empty($this->searchFilter)) {
                            $query = strtolower($this->searchFilter);
                            $permissions = array_filter($permissions, function($p) use ($query, $descriptions) {
                                $desc = strtolower($descriptions[$p] ?? '');
                                return str_contains(strtolower($p), $query) || str_contains($desc, $query);
                            });
                        }
                    @endphp

                    {{-- MODE 1: MATRIKS TABLE --}}
                    @if($viewMode === 'matrix')
                        <div class="pt-2 overflow-x-auto">
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden min-w-[650px]">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-xs uppercase tracking-wider text-zinc-500 font-bold border-b border-zinc-200 dark:border-zinc-700">
                                        <tr>
                                            <th class="px-4 py-3 whitespace-nowrap">Modul Fitur</th>
                                            <th class="px-4 py-3 text-center whitespace-nowrap">Lihat (View)</th>
                                            <th class="px-4 py-3 text-center whitespace-nowrap">Tambah (Create)</th>
                                            <th class="px-4 py-3 text-center whitespace-nowrap">Ubah (Update)</th>
                                            <th class="px-4 py-3 text-center whitespace-nowrap text-rose-500">Hapus (Delete)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @php
                                            $grouped = [];
                                            foreach ($permissions as $p) {
                                                $parts = explode('.', $p);
                                                if (count($parts) >= 2) {
                                                    $action = array_pop($parts); // Ambil elemen terakhir (view, create, dll)
                                                    $module = implode(' › ', $parts); // Gabung sisanya dengan separator
                                                    $grouped[$module][$action] = $p;
                                                } else {
                                                    $grouped['Lainnya'][$p] = $p;
                                                }
                                            }
                                        @endphp

                                        @foreach($grouped as $module => $actions)
                                            @if($module !== 'Lainnya')
                                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                                <td class="px-4 py-3 font-semibold capitalize text-zinc-900 dark:text-zinc-100 border-r border-zinc-200 dark:border-zinc-700/50 bg-zinc-50/30 dark:bg-zinc-800/10 whitespace-nowrap">
                                                    {{ $module }}
                                                </td>
                                                @foreach(['view', 'create', 'update', 'delete'] as $action)
                                                    <td class="px-4 py-3 text-center">
                                                        @if(isset($actions[$action]))
                                                            @php 
                                                                $permKey = $actions[$action];
                                                                $descText = $descriptions[$permKey] ?? $permKey;
                                                            @endphp
                                                            <div class="flex justify-center items-center gap-1 group/item relative" title="{{ $descText }}">
                                                                <flux:checkbox wire:model="selectedPermissions" value="{{ $permKey }}" />
                                                                
                                                                {{-- Special indicator if description exists --}}
                                                                @if(isset($descriptions[$permKey]))
                                                                    <div class="w-1.5 h-1.5 rounded-full bg-violet-400 opacity-60 group-hover/item:opacity-100"></div>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <span class="text-zinc-300 dark:text-zinc-600 font-mono">-</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- MODE 2: DETAIL FITUR & TOMBOL --}}
                    @if($viewMode === 'detailed')
                        <div class="pt-2 max-h-[460px] overflow-y-auto pr-1 space-y-4" style="scrollbar-width: thin;">
                            @php
                                $groupedDetailed = [];
                                foreach ($permissions as $p) {
                                    $parts = explode('.', $p);
                                    $mod = count($parts) >= 2 ? ucfirst($parts[0]) : 'Sistem';
                                    $groupedDetailed[$mod][] = $p;
                                }
                            @endphp

                            @foreach($groupedDetailed as $modName => $permList)
                                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60 overflow-hidden shadow-sm">
                                    <div class="px-4 py-2.5 bg-zinc-100/70 dark:bg-zinc-800 font-bold text-xs uppercase tracking-wider text-zinc-700 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                                        <span>Modul {{ $modName }}</span>
                                        <span class="text-[11px] font-normal text-zinc-400">{{ count($permList) }} Akses</span>
                                    </div>
                                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                        @foreach($permList as $pKey)
                                            @php
                                                $desc = $descriptions[$pKey] ?? 'Akses wewenang ' . $pKey;
                                                $isSpecial = str_contains($desc, '★') || str_contains($pKey, 'approve') || str_contains($pKey, 'payment') || str_contains($pKey, 'delivery');
                                            @endphp
                                            <label class="flex items-start gap-3 px-4 py-3 hover:bg-violet-50/40 dark:hover:bg-violet-950/20 cursor-pointer transition-colors">
                                                <div class="pt-0.5 shrink-0">
                                                    <flux:checkbox wire:model="selectedPermissions" value="{{ $pKey }}" />
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ $pKey }}</span>
                                                        @if($isSpecial)
                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-700/50">
                                                                Tombol Akses Khusus
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed">
                                                        {{ $desc }}
                                                    </p>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                @endif

                <div class="flex mt-6 gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost"> Batal </flux:button>
                    </flux:modal.close>
                    <flux:button icon="check" wire:click="save" wire:target="save" wire:loading.attr="disabled" variant="primary"> Simpan Wewenang </flux:button>
                </div>
            </div>
        </flux:modal>
    </template>
</div>

