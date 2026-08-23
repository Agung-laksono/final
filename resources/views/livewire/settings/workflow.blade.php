<?php

use function Livewire\Volt\{state, mount};
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

state([
    // Workflow Settings
    'requireSalesApproval' => false,
    'requireFinanceApproval' => false,
    'requirePurchaseApproval' => false,
    'requireInventoryApproval' => false,
    'requireOutboundApproval' => false,
    'requireProductionApproval' => false,
    'requireQcApproval' => false,
    'gudangHandlesShipping' => false,
]);

mount(function () {
    // Workflow Settings Mount
    $this->requireSalesApproval = Setting::where('key', 'require_sales_approval')->value('value') == '1';
    $this->requireFinanceApproval = Setting::where('key', 'require_finance_approval')->value('value') == '1';
    $this->requirePurchaseApproval = Setting::where('key', 'require_purchase_approval')->value('value') == '1';
    $this->requireInventoryApproval = Setting::where('key', 'require_inventory_approval')->value('value') == '1';
    $this->requireOutboundApproval = Setting::where('key', 'require_outbound_approval')->value('value') == '1';
    $this->requireProductionApproval = Setting::where('key', 'require_production_approval')->value('value') == '1';
    $this->requireQcApproval = Setting::where('key', 'require_qc_approval')->value('value') == '1';
    $this->gudangHandlesShipping = Setting::where('key', 'gudang_handles_shipping')->value('value') == '1';
});

$save = function () {
    // Bool Settings
    $boolSettings = [
        'require_sales_approval' => $this->requireSalesApproval,
        'require_finance_approval' => $this->requireFinanceApproval,
        'require_purchase_approval' => $this->requirePurchaseApproval,
        'require_inventory_approval' => $this->requireInventoryApproval,
        'require_outbound_approval' => $this->requireOutboundApproval,
        'require_production_approval' => $this->requireProductionApproval,
        'require_qc_approval' => $this->requireQcApproval,
        'gudang_handles_shipping' => $this->gudangHandlesShipping,
    ];

    foreach ($boolSettings as $key => $boolVal) {
        $val = $boolVal ? '1' : '0';
        Setting::updateOrCreate(['key' => $key], ['value' => $val]);
        Cache::put("setting_{$key}", $val, now()->addHour());
        
        Cache::forget("workflow_setting_{$key}"); // Bersihkan cache workflow
        if ($key === 'gudang_handles_shipping') {
            Cache::forget('setting_gudang_handles_shipping');
            \App\Events\SettingUpdated::safeDispatch('gudang_handles_shipping', $val);
        }
    }

    \Flux::toast('Pengaturan Workflow & Operasi Gudang berhasil disimpan!');
};

?>

<x-pages::settings.layout :heading="__('Workflow Delegation')" :subheading="__('Atur alur kerja antar departemen dan operasi fisik gudang.')">
    
    <form wire:submit="save" class="space-y-8">
        
        <div class="space-y-4">
            <flux:heading size="lg">Operasi Gudang & Logistik Pengiriman</flux:heading>
            <flux:subheading>Pengaturan tanggung jawab tim gudang dan alur barang masuk/keluar.</flux:subheading>

            <div class="flex flex-col gap-4 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:switch wire:model="gudangHandlesShipping" 
                             label="Tim Gudang Mengelola Pengiriman & Input Resi Ekspedisi (Logistik)" 
                             description="Jika aktif, tim Gudang bertugas menginput nama kurir/ekspedisi dan nomor resi pengiriman pada menu Surat Jalan / Sales Delivery. Jika nonaktif, proses ini dilakukan oleh Sales." />
                
                <flux:switch wire:model="requireOutboundApproval" label="Kepala Gudang: Verifikasi Pengeluaran Barang (Outbound)" description="Jika aktif, stok fisik tidak terpotong saat staff packing melakukan scan, melainkan membutuhkan persetujuan dari Kepala Gudang." />
                
                <flux:switch wire:model="requireInventoryApproval" label="Gudang Inbox: Verifikasi Penerimaan Barang Supplier (Inbound)" description="Jika aktif, barang yang datang dari supplier tidak otomatis menambah stok sistem sebelum orang gudang memverifikasinya." />
            </div>
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="lg">Alur Penjualan (Order-to-Cash)</flux:heading>
            <flux:subheading>Pengaturan alur saat ada pesanan masuk dari pelanggan.</flux:subheading>

            <div class="flex flex-col gap-4 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:switch wire:model="requireSalesApproval" label="Sales Manager: Butuh Persetujuan Sales Order" description="Jika aktif, SO baru (Draft) harus disetujui Manajer sebelum lanjut ke pembayaran/packing." />
                
                <flux:switch wire:model="requireFinanceApproval" label="Finance: Butuh Validasi Pembayaran" description="Jika aktif, staf Finance harus memverifikasi bukti mutasi bank secara manual sebelum statusnya menjadi Lunas." />
            </div>
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="lg">Alur Pembelian (Procure-to-Pay)</flux:heading>
            <flux:subheading>Pengaturan alur belanja stok/bahan baku ke Supplier.</flux:subheading>

            <div class="flex flex-col gap-4 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:switch wire:model="requirePurchaseApproval" label="Purchasing Manager: Butuh Persetujuan PO Baru" description="Jika aktif, Purchase Order (PO) butuh tanda tangan manajer sebelum dokumennya sah." />
            </div>
        </div>

        <flux:separator />

        <div class="space-y-4">
            <flux:heading size="lg">Alur Produksi (Make-to-Order)</flux:heading>
            <flux:subheading>Pengaturan alur saat memproduksi atau merakit barang.</flux:subheading>

            <div class="flex flex-col gap-4 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/50">
                <flux:switch wire:model="requireProductionApproval" label="Kepala Produksi: Persetujuan Surat Perintah Kerja (SPK)" description="Jika aktif, mesin/operator tidak bisa memulai SPK sebelum kepala produksi menyetujuinya." />
                
                <flux:switch wire:model="requireQcApproval" label="Quality Control: Verifikasi Barang Jadi" description="Jika aktif, barang jadi hasil produksi harus diinspeksi oleh QC sebelum diizinkan masuk ke gudang penyimpanan." />
            </div>
        </div>

        <div class="flex items-center gap-4 pt-4">
            <flux:button icon="check" variant="primary" type="submit"> Simpan Pengaturan Workflow </flux:button>
            <flux:text class="text-sm">Perubahan akan langsung diterapkan ke seluruh alur kerja.</flux:text>
        </div>
    </form>

</x-pages::settings.layout>
