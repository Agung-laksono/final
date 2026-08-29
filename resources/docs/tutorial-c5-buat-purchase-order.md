# 🏭 Tutorial C.5 — Membuat Purchase Order (PO)

**Durasi video:** ~15 menit | **Role:** Staf Purchasing, Kepala Purchasing

---

## Tujuan Tutorial

Cara membuat Purchase Order lengkap dari nol hingga siap dikirim ke vendor.

---

## Langkah-Langkah

### 1. Membuka Form Buat PO

1. Klik menu **PEMBELIAN → Buat PO Baru**
2. Form pembuatan PO terbuka

### 2. Mengisi Data PO

#### Header PO

| Field | Keterangan |
|-------|-----------|
| **Vendor** | Pilih dari daftar vendor (ada avatar/logo vendor) |
| **Tanggal PO** | Tanggal pembuatan (otomatis hari ini) |
| **Estimasi Datang** | Perkiraan tanggal barang tiba |
| **Nomor PO** | Otomatis di-generate sistem |
| **Catatan** | Instruksi khusus ke vendor |

#### Menambah Item Barang

1. Klik **+ Tambah Item**
2. Cari dan pilih barang dari daftar
3. Isi detail per item:
   - **Jumlah** yang dipesan
   - **Satuan** (sesuai satuan barang)
   - **Harga Satuan** (format Rupiah otomatis)
   - **Diskon** per item (opsional)
4. Ulangi untuk setiap item yang dipesan

> **Tips**: Harga beli terakhir otomatis terisi dari riwayat harga barang. Ubah jika harga berubah dari vendor.

#### Total & Ringkasan

- **Sub-total** per item dihitung otomatis
- **Total PO** = jumlah semua item (dikurangi diskon)
- Format angka dalam **Rupiah** (Rp 1.500.000)

### 3. Menyimpan PO

**Pilihan penyimpanan:**

| Tombol | Status | Keterangan |
|--------|--------|-----------|
| **Simpan Draft** | Draft | Belum dikirim ke vendor, masih bisa diedit |
| **Kirim ke Vendor** | Dikirim | PO final, tidak bisa diedit besar |

### 4. Setelah PO Dibuat

PO muncul di **Kanban Purchase Order** (`purchase.orders.kanban`) di kolom:
- **Draft** — jika disimpan sebagai draft
- **Dikirim** — jika langsung dikirmkan

---

## Alur Lanjutan

Setelah PO dibuat:
1. Vendor konfirmasi → PO pindah ke kolom **Dikonfirmasi**
2. Barang datang → Staf Gudang lakukan **Penerimaan Fisik**
3. Finance catat **Hutang Pembelian** berdasarkan PO ini

---

## Yang Perlu Diperhatikan

- PO yang sudah berstatus **Dikirim** tidak bisa diubah item-nya — buat **Addendum** jika ada perubahan
- Pastikan vendor sudah ada di **Master Data Vendor** sebelum membuat PO

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
