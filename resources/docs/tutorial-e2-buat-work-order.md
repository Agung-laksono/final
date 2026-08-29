# 🏭 Tutorial E.2 — Membuat Work Order Produksi

**Durasi video:** ~12 menit | **Role:** Kepala Produksi

---

## Tujuan Tutorial

Cara membuat perintah kerja produksi (Work Order) berdasarkan resep/formula yang sudah ada, termasuk order maklon (sub-kontrak ke pihak luar).

---

## Langkah-Langkah

### 1. Prasyarat Sebelum Membuat Work Order

Pastikan sudah ada:
- ✅ **Resep Produksi** yang sesuai (Tutorial E.1)
- ✅ Stok bahan baku di gudang (atau PO bahan baku sudah dibuat)

### 2. Membuka Menu Produksi

1. Klik menu **PRODUKSI → Daftar Produksi**
2. Tampil **Kanban Work Order** dengan kolom-kolom status

### 3. Membuat Work Order Baru

Klik tombol **+ Buat Work Order Baru**. Isi form:

| Field | Keterangan |
|-------|-----------|
| **Resep / Produk** | Pilih resep produksi yang akan dijalankan |
| **Jumlah Target** | Berapa unit/kg/liter yang akan diproduksi |
| **Tanggal Mulai** | Jadwal mulai produksi |
| **Estimasi Selesai** | Target tanggal produksi selesai |
| **Gudang Output** | Kemana produk jadi akan masuk stok |
| **Prioritas** | Normal / Mendesak / Sangat Mendesak |
| **Catatan** | Instruksi khusus untuk tim produksi |

> Sistem otomatis menghitung **kebutuhan bahan baku** berdasarkan resep × jumlah target.

### 4. Membuat Order Maklon (Sub-Kontrak)

Jika produksi dikerjakan pihak luar (vendor maklon):

1. Aktifkan toggle **"Maklon / Vendor Luar"**
2. Pilih **Vendor Maklon** dari daftar
3. Isi **Biaya Jasa Maklon** per unit atau total
4. Sistem akan membuat PO terkait ke vendor maklon

### 5. Simpan Work Order

- Klik **Buat Work Order**
- Status awal: **Menunggu Bahan** (jika bahan baku belum siap) atau **Siap Berjalan**

### 6. Tampilan Kanban Produksi

Work Order tersusun dalam kolom Kanban:

| Kolom | Arti |
|-------|------|
| **Menunggu Bahan** | Bahan baku belum siap di gudang |
| **Siap Berjalan** | Bahan siap, menunggu mulai produksi |
| **Sedang Berjalan** | Produksi sedang dikerjakan |
| **QC / Finishing** | Produk jadi dalam tahap quality check |
| **Selesai** | Produk masuk ke stok gudang |
| **Dibatalkan** | Work order dibatalkan |

---

## Alur Lanjutan

```
Work Order Dibuat
    ↓
Staf Gudang PPIC: Siapkan & keluarkan bahan baku (Tutorial B.8)
    ↓
Status: Siap Berjalan → Staf Produksi mulai kerja
    ↓
Staf Produksi update status: Sedang Berjalan (Tutorial E.3)
    ↓
Produksi Selesai → Stok produk jadi bertambah
```

---

## Yang Perlu Diperhatikan

- Jika bahan baku kurang, sistem otomatis buat **Inventory Request** ke Gudang
- Work Order yang sudah **Selesai** tidak bisa diedit
- Untuk produksi bertahap / batch, gunakan fitur **Split Order** (Tutorial E.4)

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
