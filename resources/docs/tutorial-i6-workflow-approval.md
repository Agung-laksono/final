# 🔧 Tutorial I.6 — Pengaturan Workflow Approval

**Durasi video:** ~8 menit | **Role:** Super Admin

---

## Tujuan Tutorial

Cara mengonfigurasi alur persetujuan (approval workflow) untuk menyesuaikan kebutuhan bisnis — apakah membutuhkan verifikasi bertingkat atau proses otomatis.

---

## Konsep Workflow: Solo vs Tim

Aplikasi mendukung dua mode operasi:

| Mode | Deskripsi | Cocok Untuk |
|------|-----------|-------------|
| **Mode Solo (Otomatis)** | Setiap transaksi langsung diproses tanpa perlu approval | Bisnis kecil, pemilik = operator |
| **Mode Tim (Verifikasi)** | Setiap transaksi butuh persetujuan kepala divisi | Bisnis menengah-besar dengan tim terpisah |

---

## Langkah-Langkah

### 1. Membuka Pengaturan Workflow

1. Login sebagai **Super Admin**
2. Klik **Pengaturan → Workflow**
3. Halaman konfigurasi workflow terbuka

### 2. Memahami Toggle Workflow

Setiap modul memiliki toggle tersendiri:

| Toggle | Jika ON (Tim) | Jika OFF (Solo) |
|--------|--------------|-----------------|
| **Sales Order** | SO butuh approval Kepala Sales | SO langsung Diproses |
| **Purchase Order** | PO butuh approval Kepala Purchasing | PO langsung Approved |
| **Work Order** | WO butuh approval Kepala Produksi | WO langsung Siap Berjalan |
| **Penerimaan Barang QC** | Butuh verifikasi QC dulu | Barang otomatis masuk stok |

### 3. Mengaktifkan/Menonaktifkan Workflow

1. Klik toggle yang ingin diubah
2. Konfirmasi perubahan
3. **Langsung aktif** — tidak perlu restart

### 4. Rekomendasi Pengaturan

#### Untuk Bisnis Baru / Tim Kecil
```
Sales Order     → OFF (Solo)
Purchase Order  → OFF (Solo)
Work Order      → OFF (Solo)
QC Penerimaan   → OFF (Solo)
```
*Semua proses otomatis, tidak perlu klik approval berulang*

#### Untuk Bisnis Berkembang / Tim Terpisah
```
Sales Order     → ON (Tim) — Kepala Sales approve
Purchase Order  → ON (Tim) — Kepala Purchasing approve
Work Order      → OFF (Solo) — Kepala Produksi langsung buat
QC Penerimaan   → ON (Tim) — Wajib verifikasi fisik barang
```

#### Untuk Bisnis Besar / Kontrol Ketat
```
Semua toggle    → ON (Tim)
```
*Setiap transaksi terverifikasi bertingkat*

---

## Dampak Perubahan Workflow

> **Penting**: Perubahan workflow **hanya berlaku untuk transaksi baru** setelah perubahan disimpan. Transaksi yang sudah berjalan tidak terpengaruh.

---

## Yang Perlu Diperhatikan

- Jika workflow **Sales** di-ON tapi tidak ada Kepala Sales yang login → SO akan menumpuk
- Pastikan sudah ada pengguna dengan role yang tepat **sebelum** mengaktifkan workflow Tim
- Selama masa transisi, beritahu semua tim tentang perubahan alur kerja

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
