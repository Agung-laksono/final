# 🛒 Tutorial D.4 — Membuat Sales Order (SO)

**Durasi video:** ~15 menit | **Role:** Staf Sales

---

## Tujuan Tutorial

Cara membuat Sales Order (pesanan penjualan) yang lengkap dan benar dari awal hingga siap diproses.

---

## Langkah-Langkah

### 1. Membuka Form Buat SO

1. Klik menu **PENJUALAN → Buat SO Baru**
2. Form pembuatan SO terbuka di halaman penuh

### 2. Mengisi Data SO

#### Header SO

| Field | Keterangan |
|-------|-----------|
| **Pelanggan** | Pilih dari daftar pelanggan (ketik untuk search) |
| **Tanggal SO** | Tanggal pesanan dibuat |
| **Estimasi Kirim** | Perkiraan tanggal pengiriman ke pelanggan |
| **Nomor SO** | Otomatis di-generate sistem |
| **Gudang** | Pilih gudang asal pengiriman |

> **Pelanggan belum terdaftar?** Buat dulu di menu **Data Pelanggan** atau klik **"+ Pelanggan Baru"** jika tersedia di form.

#### Menambah Item Pesanan

1. Klik **+ Tambah Item**
2. Cari barang dengan mengetik nama atau SKU
3. Isi per item:
   - **Jumlah** yang dipesan
   - **Harga Jual** (otomatis terisi dari harga standar, bisa diubah)
   - **Diskon** per item (nominal atau persen)
4. Ulangi untuk semua item pesanan

#### Ringkasan Harga

| Baris | Keterangan |
|-------|-----------|
| Sub-total | Jumlah semua item |
| Diskon SO | Diskon tambahan untuk seluruh order |
| **Total SO** | Nilai akhir yang dibayar pelanggan |

> Format angka otomatis dalam **Rupiah** (contoh: Rp 2.500.000)

#### Catatan & Instruksi Pengiriman

- **Catatan Internal**: Instruksi khusus untuk tim gudang/produksi
- **Alamat Kirim**: Bisa berbeda dari alamat pelanggan terdaftar
- **Metode Bayar**: Tunai, Transfer, Kredit, dll

### 3. Menyimpan Sales Order

| Tombol | Status | Keterangan |
|--------|--------|-----------|
| **Simpan Draft** | Draft | Belum masuk antrian, masih bisa diedit penuh |
| **Ajukan** | Menunggu Approval | Dikirim ke Kepala Sales untuk disetujui |

### 4. Setelah SO Diajukan

SO muncul di **Daftar Sales Order** dengan status **Menunggu Approval** (jika workflow aktif) atau langsung **Diproses** (jika workflow solo/otomatis).

---

## Alur Lanjutan

```
SO Diajukan
    ↓
Kepala Sales Approve (Tutorial D.5)
    ↓
Gudang Siapkan Barang & Packing (Tutorial B.7)
    ↓
Sales Input Bukti Bayar (Tutorial D.7)
    ↓
Finance Validasi Pembayaran (Tutorial F.4)
    ↓
SO Selesai ✅
```

---

## Yang Perlu Diperhatikan

- Cek ketersediaan stok barang sebelum membuat SO — bisa dicek dari menu **Data Barang**
- SO yang sudah diajukan **tidak bisa diedit** — hubungi Kepala Sales untuk cancel jika ada kesalahan
- Stok baru benar-benar berkurang saat gudang konfirmasi **pengiriman**, bukan saat SO dibuat

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
