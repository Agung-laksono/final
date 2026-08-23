<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class KnowledgeFormatter
{
    /**
     * Format any supported ERP Eloquent Model into a rich Indonesian knowledge string.
     */
    public static function format(Model $model): string
    {
        $class = get_class($model);

        try {
            switch ($class) {
                case 'Modules\Inventory\Models\Item':
                    return static::formatItem($model);

                case 'Modules\Sales\Models\SalesOrder':
                    return static::formatSalesOrder($model);

                case 'Modules\Sales\Models\Customer':
                    return static::formatCustomer($model);

                case 'Modules\Purchase\Models\Vendor':
                    return static::formatVendor($model);

                case 'Modules\Purchase\Models\PurchaseOrder':
                    return static::formatPurchaseOrder($model);

                case 'Modules\Finance\Models\FinanceTransaction':
                    return static::formatFinanceTransaction($model);

                case 'Modules\Finance\Models\FinanceAccount':
                    return static::formatFinanceAccount($model);

                case 'Modules\Production\Models\ProductionOrder':
                    return static::formatProductionOrder($model);

                case 'Modules\Inventory\Models\Category':
                    return static::formatCategory($model);

                case 'Modules\Inventory\Models\SubCategory':
                    return static::formatSubCategory($model);

                case 'Modules\Inventory\Models\Type':
                    return static::formatType($model);

                case 'Modules\Inventory\Models\Unit':
                    return static::formatUnit($model);

                case 'Modules\Inventory\Models\Warehouse':
                    return static::formatWarehouse($model);

                case 'App\Models\Brand':
                    return static::formatBrand($model);

                case 'Modules\Sales\Models\Quotation':
                    return static::formatQuotation($model);

                case 'Modules\Sales\Models\SalesReturn':
                    return static::formatSalesReturn($model);

                case 'Modules\Purchase\Models\PurchaseReceipt':
                    return static::formatPurchaseReceipt($model);

                case 'Modules\Purchase\Models\PurchaseReturn':
                    return static::formatPurchaseReturn($model);

                case 'Modules\Inventory\Models\StockAdjustment':
                    return static::formatStockAdjustment($model);

                case 'Modules\Inventory\Models\StockTransfer':
                    return static::formatStockTransfer($model);

                case 'Modules\Production\Models\ProductionRecipe':
                    return static::formatProductionRecipe($model);

                case 'App\Models\User':
                    return static::formatUser($model);

                case 'Modules\Communication\Models\WaMessage':
                    return static::formatWaMessage($model);

                default:
                    return static::formatGeneric($model);
            }
        } catch (\Exception $e) {
            return static::formatGeneric($model);
        }
    }

    protected static function formatItem(Model $item): string
    {
        $item->loadMissing(['unit', 'category', 'subCategory', 'type', 'warehouses']);

        $categoryName = $item->category->name ?? 'Tanpa Kategori';
        $subCategoryName = $item->subCategory->name ?? '';
        $typeName = $item->type->name ?? 'Umum';
        $unitName = $item->unit->name ?? 'Pcs';

        $purchasePrice = 'Rp ' . number_format($item->purchase_price ?? 0, 0, ',', '.');
        $sellingPrice = 'Rp ' . number_format($item->selling_price ?? 0, 0, ',', '.');
        $status = ($item->is_active ?? 1) ? 'Aktif' : 'Non-Aktif';

        // Stocks per warehouse
        $warehouseStocksText = [];
        $totalStock = 0;
        if ($item->relationLoaded('warehouses') && $item->warehouses) {
            foreach ($item->warehouses as $wh) {
                $whStock = (float) ($wh->pivot->stock ?? 0);
                $totalStock += $whStock;
                $warehouseStocksText[] = "{$wh->name}: {$whStock} {$unitName}";
            }
        }
        $warehouseStr = !empty($warehouseStocksText) ? implode(', ', $warehouseStocksText) : 'Belum ada stok di gudang';

        // ATP Stock
        $atp = method_exists($item, 'getATP') ? $item->getATP() : $totalStock;

        $desc = trim(strip_tags($item->description ?? ''));
        $descStr = $desc ? " Deskripsi: {$desc}." : "";

        return "Master Data Produk (Item): {$item->name} (Kode: {$item->code}). " .
               "Kategori Utama: {$categoryName}. Sub Kategori: " . ($subCategoryName ?: '-') . ". " .
               "Tipe Barang: {$typeName}. Satuan: {$unitName}. " .
               "Harga Beli: {$purchasePrice}. Harga Jual: {$sellingPrice}. " .
               "Total Stok Fisik: {$totalStock} {$unitName}. Stok Per Gudang: [{$warehouseStr}]. " .
               "Stok Siap Jual (ATP): {$atp} {$unitName}. Minimum Stok: {$item->min_stock}, Maksimum Stok: {$item->max_stock}. " .
               "Status: {$status}.{$descStr}";
    }

    protected static function formatSalesOrder(Model $so): string
    {
        $so->loadMissing(['customer', 'items.item', 'courierVendor', 'creator']);

        $customerName = $so->customer->name ?? 'Pelanggan Umum';
        $customerPhone = $so->customer->phone ?? '-';
        $courierName = $so->courierVendor->name ?? 'Kurir Internal';
        $creatorName = $so->creator->name ?? 'Sistem';

        $totalVal = floatval($so->grand_total ?? ($so->total_amount ?? 0));
        $paidVal = floatval($so->paid_amount ?? 0);
        $unpaidVal = max(0, $totalVal - $paidVal);

        $total = 'Rp ' . number_format($totalVal, 0, ',', '.');
        $paid = 'Rp ' . number_format($paidVal, 0, ',', '.');
        $unpaid = 'Rp ' . number_format($unpaidVal, 0, ',', '.');
        $orderDate = $so->order_date ? date('d-m-Y', strtotime($so->order_date)) : date('d-m-Y', strtotime($so->created_at));

        $statusText = match ($so->status) {
            'approved' => 'Disetujui (Approved)',
            'processing' => 'Diproses (Processing)',
            'completed' => 'Selesai (Completed)',
            'cancelled' => 'Dibatalkan (Cancelled)',
            default => 'Draf (Draft)',
        };

        $paymentStatusText = match ($so->payment_status) {
            'paid' => 'Lunas (Paid)',
            'partial' => 'Sebagian (Partial)',
            default => 'Belum Lunas (Unpaid)',
        };

        // Format ordered items
        $itemsText = [];
        if ($so->relationLoaded('items') && $so->items) {
            foreach ($so->items as $idx => $soItem) {
                $itemName = $soItem->item->name ?? ($soItem->item_name ?? 'Produk');
                $qty = $soItem->qty ?? 1;
                $price = 'Rp ' . number_format($soItem->unit_price ?? 0, 0, ',', '.');
                $subtotal = 'Rp ' . number_format($soItem->subtotal ?? ($soItem->qty * $soItem->unit_price), 0, ',', '.');
                $num = $idx + 1;
                $itemsText[] = "{$num}. {$itemName} (Jumlah: {$qty}, Harga Satuan: {$price}, Subtotal: {$subtotal})";
            }
        }
        $itemListStr = !empty($itemsText) ? implode('; ', $itemsText) : 'Tidak ada rincian barang';

        $notes = trim($so->notes ?? '');
        $notesStr = $notes ? " Catatan Pesanan: {$notes}." : "";

        return "Transaksi Penjualan (Sales Order): Nomor SO {$so->so_number}. Tanggal: {$orderDate}. " .
               "Pelanggan / Customer: {$customerName} (Telepon: {$customerPhone}). " .
               "Status Pesanan: {$statusText}. Status Pembayaran: {$paymentStatusText}. " .
               "Total Transaksi: {$total}. Jumlah Terbayar: {$paid}. Sisa Tagihan (Piutang): {$unpaid}. Dibuat Oleh: {$creatorName}. Ekspedisi/Kurir: {$courierName}. " .
               "Daftar Barang Yang Dipesan: [{$itemListStr}].{$notesStr}";
    }

    protected static function formatCustomer(Model $customer): string
    {
        $status = ($customer->is_active ?? 1) ? 'Aktif' : 'Non-Aktif';
        $creditLimit = 'Rp ' . number_format($customer->credit_limit ?? 0, 0, ',', '.');
        $code = $customer->code ?? "CUST-{$customer->id}";
        $cityStr = $customer->city ? " Kota/Kabupaten: {$customer->city}." : "";

        return "Data Pelanggan (Customer): {$customer->name} (Kode: {$code}). " .
               "No. Telepon: {$customer->phone}. Email: {$customer->email}. Alamat: {$customer->address}.{$cityStr} " .
               "Batas Kredit (Credit Limit): {$creditLimit}. Status Pelanggan: {$status}.";
    }

    protected static function formatVendor(Model $vendor): string
    {
        $status = ($vendor->is_active ?? 1) ? 'Aktif' : 'Non-Aktif';
        $code = $vendor->code ?? "VND-{$vendor->id}";
        $cityStr = $vendor->city ? " Kota/Kabupaten: {$vendor->city}." : "";

        return "Data Vendor / Pemasok (Supplier): {$vendor->name} (Kode: {$code}). " .
               "No. Telepon: {$vendor->phone}. Email: {$vendor->email}. Alamat: {$vendor->address}.{$cityStr} " .
               "Status Vendor: {$status}.";
    }

    protected static function formatPurchaseOrder(Model $po): string
    {
        $po->loadMissing(['vendor', 'items.item']);

        $vendorName = $po->vendor->name ?? 'Vendor Umum';
        $totalVal = floatval($po->grand_total ?? ($po->total_amount ?? 0));
        $paidVal = floatval($po->paid_amount ?? 0);
        $unpaidVal = max(0, $totalVal - $paidVal);

        $total = 'Rp ' . number_format($totalVal, 0, ',', '.');
        $paid = 'Rp ' . number_format($paidVal, 0, ',', '.');
        $unpaid = 'Rp ' . number_format($unpaidVal, 0, ',', '.');
        $orderDate = $po->order_date ? date('d-m-Y', strtotime($po->order_date)) : date('d-m-Y', strtotime($po->created_at));

        $itemsText = [];
        if ($po->relationLoaded('items') && $po->items) {
            foreach ($po->items as $idx => $poItem) {
                $itemName = $poItem->item->name ?? ($poItem->item_name ?? 'Produk');
                $qty = $poItem->requested_qty ?? ($poItem->qty ?? 1);
                $price = 'Rp ' . number_format($poItem->unit_price ?? 0, 0, ',', '.');
                $subtotal = 'Rp ' . number_format($poItem->subtotal ?? 0, 0, ',', '.');
                $num = $idx + 1;
                $itemsText[] = "{$num}. {$itemName} (Jumlah: {$qty}, Harga Satuan: {$price}, Subtotal: {$subtotal})";
            }
        }
        $itemListStr = !empty($itemsText) ? implode('; ', $itemsText) : 'Tidak ada rincian barang';

        return "Transaksi Pembelian (Purchase Order): Nomor PO {$po->po_number}. Tanggal: {$orderDate}. " .
               "Vendor / Supplier: {$vendorName}. Status Pembelian: {$po->status}. Status Pembayaran: {$po->payment_status}. " .
               "Total Nilai Pembelian: {$total}. Jumlah Terbayar: {$paid}. Sisa Hutang Tagihan: {$unpaid}. " .
               "Daftar Barang Dibeli: [{$itemListStr}].";
    }

    protected static function formatFinanceTransaction(Model $trx): string
    {
        $trx->loadMissing(['account', 'category']);

        $accountName = $trx->account->name ?? 'Kas Utama';
        $categoryName = $trx->category->name ?? 'Umum';
        $amount = 'Rp ' . number_format($trx->amount ?? 0, 0, ',', '.');
        $date = $trx->transaction_date ? date('d-m-Y', strtotime($trx->transaction_date)) : date('d-m-Y', strtotime($trx->created_at));

        $typeText = match ($trx->type) {
            'income' => 'Kas Masuk (Pemasukan)',
            'expense' => 'Kas Keluar (Pengeluaran)',
            'transfer' => 'Transfer Antar Rekening',
            default => $trx->type,
        };

        $code = $trx->transaction_number ?? ($trx->code ?? "TRX-{$trx->id}");
        $ref = $trx->reference_number ? " No. Referensi: {$trx->reference_number}." : "";
        $desc = $trx->description ? " Keterangan: {$trx->description}." : "";

        return "Transaksi Keuangan: Kode {$code}. Tanggal: {$date}. " .
               "Jenis Transaksi: {$typeText}. Rekening / Akun Kas: {$accountName}. Kategori: {$categoryName}. " .
               "Nominal: {$amount}.{$ref}{$desc}";
    }

    protected static function formatFinanceAccount(Model $acc): string
    {
        $balance = 'Rp ' . number_format($acc->current_balance ?? ($acc->balance ?? 0), 0, ',', '.');
        $status = ($acc->is_active ?? 1) ? 'Aktif' : 'Non-Aktif';
        $accNumber = $acc->account_number ? " No. Rekening: {$acc->account_number}." : "";

        return "Rekening & Akun Kas Keuangan: {$acc->name}. Jenis: {$acc->type}.{$accNumber} " .
               "Saldo Kas Saat Ini (Current Balance): {$balance}. Status Rekening: {$status}.";
    }

    protected static function formatProductionOrder(Model $po): string
    {
        $po->loadMissing(['item']);
        $itemName = $po->item->name ?? 'Produk Hasil';
        $code = $po->production_code ?? ($po->code ?? "PRD-{$po->id}");
        $status = $po->status ?? 'Draft';
        $requested = $po->requested_qty ?? 0;
        $fulfilled = $po->fulfilled_qty ?? 0;

        return "Perintah Produksi (Production Order): Kode {$code}. Produk Yang Diproduksi: {$itemName}. " .
               "Target Jumlah Produksi: {$requested} Unit. Jumlah Selesai: {$fulfilled} Unit. Status Produksi: {$status}.";
    }

    protected static function formatCategory(Model $cat): string
    {
        $subNames = [];
        if ($cat->relationLoaded('subCategories') || method_exists($cat, 'subCategories')) {
            $cat->loadMissing('subCategories');
            foreach ($cat->subCategories as $sub) {
                $subNames[] = $sub->name;
            }
        }
        $subStr = !empty($subNames) ? implode(', ', $subNames) : 'Tidak ada sub-kategori';
        $itemCount = method_exists($cat, 'items') ? $cat->items()->count() : 0;

        return "Master Kategori Barang: {$cat->name} (ID: {$cat->id}). " .
               "Daftar Sub Kategori Turunan: [{$subStr}]. Total Produk Terkait: {$itemCount} item.";
    }

    protected static function formatSubCategory(Model $sub): string
    {
        $sub->loadMissing('category');
        $parentCatName = $sub->category->name ?? 'Tanpa Kategori Induk';
        $itemCount = method_exists($sub, 'items') ? $sub->items()->count() : 0;

        return "Master Sub-Kategori Barang: {$sub->name} (ID: {$sub->id}). " .
               "Kategori Induk: {$parentCatName}. Total Produk Terkait: {$itemCount} item.";
    }

    protected static function formatType(Model $type): string
    {
        $itemCount = method_exists($type, 'items') ? $type->items()->count() : 0;
        return "Master Tipe Barang: {$type->name} (ID: {$type->id}). Total Produk Terkait: {$itemCount} item.";
    }

    protected static function formatUnit(Model $unit): string
    {
        $codeStr = isset($unit->code) && $unit->code ? " (Kode: {$unit->code})" : "";
        $itemCount = method_exists($unit, 'items') ? $unit->items()->count() : 0;

        return "Master Satuan Barang (Unit): {$unit->name}{$codeStr} (ID: {$unit->id}). Total Produk Terkait: {$itemCount} item.";
    }

    protected static function formatWarehouse(Model $wh): string
    {
        $addressStr = isset($wh->address) && $wh->address ? " Alamat: {$wh->address}." : "";
        $codeStr = isset($wh->code) && $wh->code ? " (Kode: {$wh->code})" : "";

        return "Master Gudang Penyimpanan: {$wh->name}{$codeStr} (ID: {$wh->id}).{$addressStr}";
    }

    protected static function formatBrand(Model $brand): string
    {
        $taglineStr = $brand->tagline ? " Tagline: {$brand->tagline}." : "";
        $phoneStr = $brand->phone ? " Telepon: {$brand->phone}." : "";
        $addressStr = $brand->address ? " Alamat: {$brand->address}." : "";

        return "Master Brand / Merek Perusahaan: {$brand->name}.{$taglineStr}{$phoneStr}{$addressStr}";
    }

    protected static function formatQuotation(Model $q): string
    {
        $q->loadMissing('customer');
        $custName = $q->customer->name ?? 'Pelanggan';
        $total = 'Rp ' . number_format($q->total_amount ?? 0, 0, ',', '.');
        $number = $q->quotation_number ?? "QT-{$q->id}";

        return "Penawaran Harga (Quotation): Nomor {$number}. Pelanggan: {$custName}. Status: {$q->status}. Total Penawaran: {$total}.";
    }

    protected static function formatSalesReturn(Model $sr): string
    {
        $sr->loadMissing('customer');
        $custName = $sr->customer->name ?? 'Pelanggan';
        $number = $sr->return_number ?? "SR-{$sr->id}";
        $reason = $sr->reason ? " Alasan Retur: {$sr->reason}." : "";

        return "Retur Penjualan (Sales Return): Nomor {$number}. Pelanggan: {$custName}. Status: {$sr->status}.{$reason}";
    }

    protected static function formatPurchaseReceipt(Model $pr): string
    {
        $pr->loadMissing('vendor');
        $vendorName = $pr->vendor->name ?? 'Vendor';
        $number = $pr->receipt_number ?? "PR-{$pr->id}";

        return "Penerimaan Barang Pembelian (Purchase Receipt): Nomor {$number}. Vendor: {$vendorName}. Status: {$pr->status}.";
    }

    protected static function formatPurchaseReturn(Model $prt): string
    {
        $prt->loadMissing('vendor');
        $vendorName = $prt->vendor->name ?? 'Vendor';
        $number = $prt->return_number ?? "PRT-{$prt->id}";

        return "Retur Pembelian (Purchase Return): Nomor {$number}. Vendor: {$vendorName}. Status: {$prt->status}.";
    }

    protected static function formatStockAdjustment(Model $sa): string
    {
        $sa->loadMissing(['item', 'warehouse']);
        $itemName = $sa->item->name ?? 'Barang';
        $whName = $sa->warehouse->name ?? 'Gudang';
        $number = $sa->reference_number ?? "SA-{$sa->id}";
        $diff = $sa->adjusted_qty ?? 0;

        return "Stok Opname / Penyesuaian Stok: Referensi {$number}. Gudang: {$whName}. Barang: {$itemName}. Selisih Stok: {$diff}. Status: {$sa->status}.";
    }

    protected static function formatStockTransfer(Model $st): string
    {
        $st->loadMissing(['fromWarehouse', 'toWarehouse', 'item']);
        $itemName = $st->item->name ?? 'Barang';
        $from = $st->fromWarehouse->name ?? 'Gudang Asal';
        $to = $st->toWarehouse->name ?? 'Gudang Tujuan';
        $number = $st->transfer_number ?? "ST-{$st->id}";
        $qty = $st->qty ?? 0;

        return "Mutasi / Transfer Stok Antar Gudang: Nomor {$number}. Dari: {$from} Ke: {$to}. Barang: {$itemName} (Jumlah: {$qty}). Status: {$st->status}.";
    }

    protected static function formatProductionRecipe(Model $recipe): string
    {
        $recipe->loadMissing('item');
        $itemName = $recipe->item->name ?? 'Barang Hasil';

        return "Resep / BOM Produksi: {$recipe->name}. Produk Hasil Produksi: {$itemName}. Keterangan: {$recipe->description}.";
    }

    protected static function formatUser(Model $user): string
    {
        $roleStr = method_exists($user, 'getRoleNames') ? implode(', ', $user->getRoleNames()->toArray()) : 'Pengguna';
        return "Pengguna / Staff ERP: {$user->name} (Username: {$user->username}, Email: {$user->email}). Jabatan/Role: [{$roleStr}].";
    }

    protected static function formatWaMessage(Model $msg): string
    {
        $dir = $msg->direction === 'in' ? 'Pesan Masuk dari Pelanggan' : 'Pesan Keluar dari Agen';
        $text = trim($msg->message ?? '');
        return "Riwayat Obrolan WhatsApp ({$dir}): \"{$text}\". Tanggal: {$msg->created_at}.";
    }

    protected static function formatGeneric(Model $model): string
    {
        $classBasename = class_basename($model);
        $attributes = $model->toArray();
        unset($attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at'], $attributes['password']);

        $pairs = [];
        foreach ($attributes as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            if ($value !== null && $value !== '') {
                $pairs[] = "{$key}: {$value}";
            }
        }

        return "Data ERP {$classBasename} (ID: {$model->id}): " . implode(', ', $pairs);
    }
}
