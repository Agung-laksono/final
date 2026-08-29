# 📦 Tutorial B.3 — Master Data Barang

**Durasi video:** ~15 menit | **Role:** Kepala Gudang, Staf Gudang

---

## Tujuan Tutorial

Cara mengelola data master barang: menambah, mengedit, melihat detail stok, dan mencetak label.

---

## Langkah-Langkah

### 1. Membuka Daftar Barang

1. Klik menu **INVENTORY → Data Barang**
2. Tampil daftar semua barang dengan info: nama, SKU, stok total, harga, satuan

### 2. Filter & Pencarian Barang

- **Search**: Ketik nama atau kode SKU barang
- **Filter Kategori**: Pilih kategori untuk menyaring
- **Filter Sub-Kategori / Tipe**: Penyaringan lebih spesifik
- **Filter Stok Menipis**: Tampilkan barang di bawah stok minimum

### 3. Menambah Barang Baru

Klik tombol **+ Tambah Barang**. Isi form:

| Field | Keterangan |
|-------|-----------|
| **Nama Barang** | Nama lengkap produk |
| **Kode SKU** | Kode unik barang (otomatis atau manual) |
| **Kategori** | Pilih dari daftar (+ bisa tambah inline) |
| **Sub-Kategori** | Pengelompokan lebih detail |
| **Tipe** | Jenis/varian barang |
| **Satuan** | pcs, kg, liter, box, dll |
| **Harga Beli** | Harga perolehan rata-rata (format Rupiah) |
| **Harga Jual** | Harga standar penjualan |
| **Stok Minimum** | Ambang batas alert stok menipis |
| **Foto Barang** | Upload gambar produk |

> **Tips Inline Add**: Jika kategori belum ada, klik **"+ Tambah Kategori Baru"** langsung dari form tanpa menutup halaman.

### 4. Menyimpan Barang

- Klik **Simpan** — barang langsung aktif di sistem
- Stok awal = 0 (stok ditambah melalui penerimaan barang)

### 5. Melihat Detail Barang

Klik nama barang → halaman **Detail Barang**:
- **Tab Stok**: Jumlah stok per gudang
- **Tab Riwayat Harga**: Grafik perubahan harga beli dari waktu ke waktu
- **Tab Mutasi**: Riwayat keluar-masuk barang ini
- **Tab Label**: Daftar label/barcode yang sudah dicetak

### 6. Edit Barang

1. Dari daftar barang → klik ikon **Edit (pensil)**
2. Ubah data yang diperlukan
3. Klik **Simpan Perubahan**

### 7. Cetak Label Barang

1. Dari detail barang → tab **Label**
2. Klik **+ Generate Label Baru**
3. Tentukan jumlah label
4. Klik **Cetak** — tampil halaman print-ready

---

## Yang Perlu Diperhatikan

- Menghapus barang hanya bisa jika belum pernah ada transaksi (gunakan nonaktifkan saja)
- Perubahan harga beli tidak mengubah transaksi historis, hanya untuk kalkulasi ke depan

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
