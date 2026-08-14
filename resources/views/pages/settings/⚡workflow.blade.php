<?php

use function Livewire\Volt\{state, mount, layout, title};
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

layout('pages.settings.layout');
title('Workflow Delegation');

state([
    'require_finance_approval' => false,
    'require_inventory_approval' => false,
    'require_purchase_approval' => false,
]);

mount(function () {
    $this->require_finance_approval = filter_var(Setting::where('key', 'require_finance_approval')->value('value'), FILTER_VALIDATE_BOOLEAN);
    $this->require_inventory_approval = filter_var(Setting::where('key', 'require_inventory_approval')->value('value'), FILTER_VALIDATE_BOOLEAN);
    $this->require_purchase_approval = filter_var(Setting::where('key', 'require_purchase_approval')->value('value'), FILTER_VALIDATE_BOOLEAN);
});

$save = function () {
    Setting::updateOrCreate(['key' => 'require_finance_approval'], ['value' => $this->require_finance_approval ? 'true' : 'false']);
    Setting::updateOrCreate(['key' => 'require_inventory_approval'], ['value' => $this->require_inventory_approval ? 'true' : 'false']);
    Setting::updateOrCreate(['key' => 'require_purchase_approval'], ['value' => $this->require_purchase_approval ? 'true' : 'false']);

    // Clear caches
    Cache::forget('workflow_setting_require_finance_approval');
    Cache::forget('workflow_setting_require_inventory_approval');
    Cache::forget('workflow_setting_require_purchase_approval');

    \Flux::toast('Pengaturan workflow berhasil disimpan.', variant: 'success');
};

?>

<x-slot name="heading">Delegasi Peran & Workflow</x-slot>
<x-slot name="subheading">Atur pendelegasian verifikasi ke berbagai divisi seiring bisnis berkembang.</x-slot>

<form wire:submit="save" class="space-y-8 max-w-2xl">
    <div class="bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-900/30 rounded-xl p-4 flex items-start gap-4">
        <div class="shrink-0 mt-1">
            <flux:icon.information-circle class="w-6 h-6 text-blue-500" />
        </div>
        <div>
            <h4 class="font-semibold text-blue-900 dark:text-blue-300">Konsep Delegasi Bertahap (Granular Toggles)</h4>
            <p class="text-sm text-blue-700 dark:text-blue-400 mt-1">
                Secara bawaan, aplikasi berjalan dengan mode <strong>"Solo"</strong> (semua toggle dimatikan), yang berarti sistem melakukan auto-approve pada setiap tahapan transaksi untuk mempercepat pekerjaan Anda. 
                Saat Anda mulai merekrut tim baru, nyalakan toggle yang sesuai agar mereka memegang kendali verifikasi.
            </p>
        </div>
    </div>

    <!-- Divisi Keuangan -->
    <div class="space-y-4">
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 flex items-center justify-center">
                <flux:icon.banknotes class="w-4 h-4" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Divisi Keuangan (Finance)</h3>
        </div>
        
        <div class="flex items-start justify-between gap-6 p-4 rounded-xl border {{ $require_finance_approval ? 'border-emerald-300 bg-emerald-50/30 dark:border-emerald-800 dark:bg-emerald-900/10' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' }} transition-colors">
            <div>
                <flux:heading size="md">Membutuhkan Verifikasi Keuangan</flux:heading>
                <div class="text-sm text-zinc-500 mt-1 space-y-1">
                    <p>Jika <strong>Nyala</strong>: Semua pembayaran (uang masuk/keluar) akan ditahan di Inbox Finance. Staf Keuangan harus memvalidasi agar transaksi masuk ke Buku Besar (Ledger).</p>
                    <p>Jika <strong>Mati</strong>: Pembayaran yang disubmit oleh tim Sales atau Purchasing akan otomatis dianggap sah dan langsung memotong/menambah saldo Kas tanpa verifikasi kedua.</p>
                </div>
            </div>
            <div class="pt-1">
                <flux:switch wire:model="require_finance_approval" />
            </div>
        </div>
    </div>

    <!-- Divisi Gudang -->
    <div class="space-y-4">
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 flex items-center justify-center">
                <flux:icon.archive-box class="w-4 h-4" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Divisi Gudang (Inventory)</h3>
        </div>
        
        <div class="flex items-start justify-between gap-6 p-4 rounded-xl border {{ $require_inventory_approval ? 'border-amber-300 bg-amber-50/30 dark:border-amber-800 dark:bg-amber-900/10' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' }} transition-colors">
            <div>
                <flux:heading size="md">Membutuhkan Verifikasi Fisik Gudang</flux:heading>
                <div class="text-sm text-zinc-500 mt-1 space-y-1">
                    <p>Jika <strong>Nyala</strong>: Saat pesanan sampai, admin tidak bisa langsung merubah stok utama. Kepala Gudang wajib melakukan validasi fisik (QC) lewat modul penerimaan.</p>
                    <p>Jika <strong>Mati</strong>: Bukti penerimaan barang (Receipt) akan otomatis menambah/mengurangi stok master secara langsung (Auto-Verified).</p>
                </div>
            </div>
            <div class="pt-1">
                <flux:switch wire:model="require_inventory_approval" />
            </div>
        </div>
    </div>

    <!-- Divisi Pembelian -->
    <div class="space-y-4">
        <div class="flex items-center gap-2 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center">
                <flux:icon.shopping-cart class="w-4 h-4" />
            </div>
            <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Divisi Pembelian (Purchasing)</h3>
        </div>
        
        <div class="flex items-start justify-between gap-6 p-4 rounded-xl border {{ $require_purchase_approval ? 'border-blue-300 bg-blue-50/30 dark:border-blue-800 dark:bg-blue-900/10' : 'border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900' }} transition-colors">
            <div>
                <flux:heading size="md">Membutuhkan Persetujuan PO (Manager)</flux:heading>
                <div class="text-sm text-zinc-500 mt-1 space-y-1">
                    <p>Jika <strong>Nyala</strong>: Setiap dokumen Purchase Order (PO) yang dibuat staf harus disetujui (ACC) oleh Manager sebelum dikirim ke Vendor.</p>
                    <p>Jika <strong>Mati</strong>: Dokumen PO otomatis berstatus Disetujui saat dibuat.</p>
                </div>
            </div>
            <div class="pt-1">
                <flux:switch wire:model="require_purchase_approval" />
            </div>
        </div>
    </div>

    <div class="flex items-center gap-4 pt-6 border-t border-zinc-200 dark:border-zinc-800">
        <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
            Simpan Konfigurasi
        </flux:button>
        
        <div wire:loading wire:target="save" class="text-sm text-zinc-500 flex items-center gap-2">
            <flux:icon.arrow-path class="w-4 h-4 animate-spin" /> Menyimpan...
        </div>
    </div>
</form>
