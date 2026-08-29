# ✅ Tutorial D.5 — Approval Sales Order

**Durasi video:** ~8 menit | **Role:** Kepala Sales

---

## Tujuan Tutorial

Cara mereview dan menyetujui atau menolak Sales Order yang diajukan oleh Staf Sales.

---

## Langkah-Langkah

### 1. Melihat SO yang Menunggu Approval

Dua cara untuk menemukan SO pending:

**Cara 1 — Melalui Badge Notifikasi**
- Badge angka merah di menu **PENJUALAN** menunjukkan jumlah SO yang menunggu

**Cara 2 — Filter di Daftar SO**
1. Klik menu **PENJUALAN → Daftar Sales Order**
2. Filter status: **Menunggu Approval**
3. Muncul semua SO yang menunggu persetujuan Anda

### 2. Mereview Detail SO

1. Klik nama/nomor SO untuk membuka **Modal Detail SO**
2. Cek semua informasi:
   - **Pelanggan** — siapa yang memesan?
   - **Item barang** — apa saja yang dipesan, berapa jumlahnya?
   - **Harga** — apakah harga jual sudah sesuai kebijakan?
   - **Diskon** — apakah diskon yang diberikan wajar?
   - **Total nilai** — sesuai dengan kontrak/kesepakatan?
   - **Catatan** — ada instruksi khusus?

### 3. Menyetujui SO (Approve)

Jika semua data sudah benar:

1. Klik tombol **✓ Setujui** di modal detail SO
2. Konfirmasi dengan klik **Ya, Setujui**
3. Status SO berubah menjadi **Diproses**
4. Notifikasi otomatis dikirim ke **Staf Gudang** untuk menyiapkan barang

### 4. Menolak SO (Reject)

Jika ada yang perlu direvisi:

1. Klik tombol **✗ Tolak**
2. Masukkan **Alasan Penolakan** (wajib diisi)
3. Klik **Konfirmasi Tolak**
4. Status SO menjadi **Ditolak**
5. Staf Sales menerima notifikasi dan bisa membuat SO baru

---

## Yang Perlu Diperhatikan

- SO yang disetujui **tidak bisa dibatalkan** kecuali oleh Super Admin atau Manager
- Jika harga perlu direvisi, **tolak dulu** dan minta Staf Sales buat ulang
- SO yang ditolak tidak mengurangi stok barang

---

## Alur Setelah Approval

```
SO Disetujui ✅
    ↓
Staf Gudang: Siapkan barang & packing
    ↓
Konfirmasi pengiriman
    ↓
Sales: Upload bukti bayar pelanggan
    ↓
Finance: Validasi pembayaran → SO Selesai
```

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
