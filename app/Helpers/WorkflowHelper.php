<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (!function_exists('get_workflow_setting')) {
    /**
     * Dapatkan nilai pengaturan workflow secara aman dengan cache
     */
    function get_workflow_setting(string $key, bool $default = true): bool
    {
        // Cache selama 24 jam untuk performa, cache dibersihkan saat setting di-update
        return Cache::remember("workflow_setting_{$key}", 86400, function () use ($key, $default) {
            try {
                $setting = Setting::where('key', $key)->first();
                if ($setting) {
                    return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
                }
                return $default;
            } catch (\Exception $e) {
                return $default; // Fallback aman jika belum ada tabel/migrasi
            }
        });
    }
}

// ------------------------------------------------------------------
// FINANCE
// ------------------------------------------------------------------
if (!function_exists('requires_finance_approval')) {
    /**
     * Apakah pembayaran dari Sales/Purchase butuh validasi manual oleh Finance?
     * Jika false, pembayaran langsung auto-verified masuk ledger.
     */
    function requires_finance_approval(): bool
    {
        return get_workflow_setting('require_finance_approval', false);
    }
}

// ------------------------------------------------------------------
// PURCHASE
// ------------------------------------------------------------------
if (!function_exists('requires_purchase_approval')) {
    /**
     * Apakah pembuatan PO baru butuh persetujuan manajer?
     * Jika false, PO langsung berstatus Approved.
     */
    function requires_purchase_approval(): bool
    {
        return get_workflow_setting('require_purchase_approval', false);
    }
}

// ------------------------------------------------------------------
// SALES
// ------------------------------------------------------------------
if (!function_exists('requires_sales_approval')) {
    /**
     * Apakah pembuatan Sales Order (SO) baru butuh persetujuan manajer?
     * Jika false, SO langsung berstatus Processing.
     */
    function requires_sales_approval(): bool
    {
        return get_workflow_setting('require_sales_approval', false);
    }
}

// ------------------------------------------------------------------
// INVENTORY (GUDANG)
// ------------------------------------------------------------------
if (!function_exists('requires_inventory_approval')) {
    /**
     * (Alias Inbound) Apakah penerimaan barang butuh verifikasi fisik oleh tim Gudang?
     * Jika false, barang masuk langsung auto-verified ke master stok.
     */
    function requires_inventory_approval(): bool
    {
        return get_workflow_setting('require_inventory_approval', false);
    }
}

if (!function_exists('requires_outbound_approval')) {
    /**
     * Apakah pengeluaran barang (Packing/Shipping) butuh persetujuan Kepala Gudang?
     * Jika false, saat staf scan barang, stok langsung otomatis dipotong.
     */
    function requires_outbound_approval(): bool
    {
        return get_workflow_setting('require_outbound_approval', false);
    }
}

// ------------------------------------------------------------------
// PRODUCTION & QC
// ------------------------------------------------------------------
if (!function_exists('requires_production_approval')) {
    /**
     * Apakah SPK Produksi butuh disetujui Manajer Produksi sebelum dimulai?
     * Jika false, SPK langsung berstatus diproses dan stok bahan baku dipotong.
     */
    function requires_production_approval(): bool
    {
        return get_workflow_setting('require_production_approval', false);
    }
}

if (!function_exists('requires_qc_approval')) {
    /**
     * Apakah barang jadi hasil produksi butuh dicek QC?
     * Jika false, barang hasil produksi otomatis masuk ke stok Gudang Utama.
     */
    function requires_qc_approval(): bool
    {
        return get_workflow_setting('require_qc_approval', false);
    }
}
