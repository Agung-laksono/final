# 🔄 Tutorial Z.2 — Alur Lengkap: SO hingga Pengiriman & Pembayaran

**Durasi video:** ~20 menit | **Role:** Staf Sales → Kepala Sales → Staf Gudang → Finance

---

## Tujuan Tutorial

Mendemonstrasikan alur kerja end-to-end dari pesanan pelanggan masuk hingga barang dikirim dan pembayaran lunas — melibatkan 4 divisi sekaligus.

---

## Gambaran Alur

```
[SALES] Buat SO → [KEPALA SALES] Approve → [GUDANG] Pack & Kirim
    ↓
[SALES] Upload Bukti Bayar → [FINANCE] Validasi → SO Selesai ✅
```

---

## Langkah Detail

### Tahap 1 — [Staf Sales] Membuat Sales Order

*Login sebagai: Staf Sales*

1. **PENJUALAN → Buat SO Baru**
2. Pilih pelanggan: *"PT. Maju Bersama"*
3. Tambah item: *"Produk A"* — 50 pcs @ Rp 50.000 = **Rp 2.500.000**
4. Tambah item: *"Produk B"* — 20 pcs @ Rp 75.000 = **Rp 1.500.000**
5. Total SO: **Rp 4.000.000**
6. Klik **Ajukan** → status: *Menunggu Approval*

---

### Tahap 2 — [Kepala Sales] Menyetujui SO

*Login sebagai: Kepala Sales*

1. Lihat badge notifikasi di menu **PENJUALAN** → muncul angka **1**
2. **PENJUALAN → Daftar Sales Order** → filter *Menunggu Approval*
3. Buka SO dari *"PT. Maju Bersama"*
4. Review: pelanggan ✅, harga ✅, diskon ✅
5. Klik **✓ Setujui** → status berubah: *Diproses*
6. Notifikasi otomatis ke Staf Gudang

---

### Tahap 3 — [Staf Gudang Fulfillment] Packing & Pengiriman

*Login sebagai: Staf Gudang Fulfillment*

1. **INVENTORY → Pengiriman Penjualan**
2. Cari SO dari *"PT. Maju Bersama"* → status: *Siap Dikemas*
3. Klik **Proses Packing**:
   - Centang: *Produk A* → 50 pcs ✅
   - Centang: *Produk B* → 20 pcs ✅
4. Klik **Konfirmasi Packing** → status: *Dikemas*
5. Klik **Input Pengiriman**:
   - Kurir: *JNE*
   - Nomor Resi: *JNE123456789*
6. Klik **Kirim** → status SO: *Dikirim* — Stok berkurang otomatis

---

### Tahap 4 — [Staf Sales] Upload Bukti Pembayaran

*Login sebagai: Staf Sales*

1. Pelanggan konfirmasi sudah transfer Rp 4.000.000
2. **PENJUALAN → Daftar SO** → buka SO *"PT. Maju Bersama"*
3. Klik **💳 Input Pembayaran**:
   - Jumlah: *Rp 4.000.000*
   - Tanggal: *hari ini*
   - Metode: *Transfer Bank BCA*
   - Upload foto struk transfer
4. Klik **Simpan** → status pembayaran: *Menunggu Validasi*

---

### Tahap 5 — [Kepala Finance] Validasi Pembayaran

*Login sebagai: Kepala Finance*

1. Badge di menu **KEUANGAN** → angka **1** muncul
2. **KEUANGAN → Validasi Transaksi**
3. Buka transaksi dari SO *"PT. Maju Bersama"*
4. Lihat bukti transfer → cocokkan nominal Rp 4.000.000 ✅
5. Pilih akun penerima: *"BCA - Rekening Penjualan"*
6. Klik **✓ Validasi**
7. Saldo BCA + Rp 4.000.000 ✅
8. Status SO: **Lunas** ✅

---

## Ringkasan Timeline

| Waktu | Aksi | Role |
|-------|------|------|
| 08:00 | Buat SO | Staf Sales |
| 08:15 | Approve SO | Kepala Sales |
| 09:00 | Packing & kirim | Staf Gudang |
| 13:00 | Pelanggan transfer, upload bukti | Staf Sales |
| 14:00 | Validasi pembayaran | Kepala Finance |
| 14:05 | SO **Selesai & Lunas** ✅ | Sistem |

---

## Alur Terkait

- [Tutorial D.4 — Membuat Sales Order](/docs/tutorial-d4-buat-sales-order)
- [Tutorial D.5 — Approval Sales Order](/docs/tutorial-d5-approval-sales-order)
- [Tutorial B.7 — Pengiriman Penjualan](/docs/tutorial-b7-pengiriman-penjualan)
- [Tutorial D.7 — Input Pembayaran](/docs/tutorial-d7-input-pembayaran)
- [Tutorial F.4 — Inbox Finance](/docs/tutorial-f4-inbox-finance)

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
