# 📥 Tutorial F.4 — Validasi Transaksi (Inbox Finance)

**Durasi video:** ~12 menit | **Role:** Kepala Finance

---

## Tujuan Tutorial

Cara memvalidasi semua transaksi keuangan yang masuk ke inbox Finance — pembayaran SO dari Sales maupun tagihan PO dari Purchasing.

---

## Mengapa Inbox Finance Penting?

Inbox Finance adalah **gerbang utama** keuangan perusahaan. Semua transaksi yang mempengaruhi saldo rekening **wajib divalidasi** di sini sebelum saldo berubah di sistem.

Transaksi yang masuk ke Inbox:
- 💰 **Pembayaran pelanggan** — diinput oleh Staf Sales
- 🏦 **Pembayaran ke vendor** — PO yang sudah selesai
- ↔️ **Mutasi internal** — transfer antar rekening perusahaan

---

## Langkah-Langkah

### 1. Membuka Inbox Finance

1. Klik menu **KEUANGAN → Validasi Transaksi**
2. Halaman inbox terbuka — tampil daftar transaksi yang menunggu

> Badge angka merah di menu menunjukkan jumlah transaksi pending yang harus divalidasi.

### 2. Memahami Tampilan Inbox

Setiap item di inbox menampilkan:
- **Tipe transaksi**: Pembayaran SO / Hutang PO / Mutasi
- **Nominal**: Jumlah uang yang terlibat
- **Tanggal**: Kapan transaksi diinput
- **Diinput oleh**: Nama staf yang menginput
- **Status**: Menunggu / Divalidasi / Ditolak

### 3. Mereview Detail Transaksi

1. Klik transaksi yang ingin di-review
2. Modal detail terbuka, tampil:
   - **Bukti transfer** (gambar/PDF yang diupload Sales)
   - **Nomor SO / PO** yang terkait
   - **Nama pelanggan / vendor**
   - **Rekening tujuan**
   - **Catatan dari Sales / Purchasing**

### 4. Memvalidasi (Menyetujui) Transaksi

Jika bukti transfer valid dan sesuai:

1. Klik tombol **✓ Validasi**
2. Pilih **Akun Keuangan** yang menerima/mengeluarkan dana
   - Contoh: "BCA - Rekening Operasional"
3. Konfirmasi
4. **Saldo akun otomatis bertambah/berkurang**
5. Transaksi tercatat di **Buku Besar** (Ledger)

### 5. Menolak Transaksi

Jika bukti tidak valid atau ada ketidaksesuaian:

1. Klik tombol **✗ Tolak**
2. Masukkan **Alasan Penolakan** (wajib)
3. Klik **Konfirmasi**
4. Notifikasi dikirim ke Staf Sales/Purchasing untuk koreksi

---

## Skenario Umum

| Skenario | Aksi Finance |
|----------|-------------|
| Pelanggan transfer, bukti jelas | ✓ Validasi → pilih rekening penerima |
| Bukti buram/tidak terbaca | ✗ Tolak → minta foto ulang ke Sales |
| Nominal tidak sesuai SO | ✗ Tolak → klarifikasi ke Sales |
| PO vendor sudah selesai | ✓ Validasi → catat hutang terbayar |

---

## Yang Perlu Diperhatikan

- Validasi yang sudah dilakukan **tidak bisa di-undo** kecuali oleh Super Admin
- Selalu cocokkan nominal di bukti dengan yang tertera di sistem
- Jika ragu, hubungi Staf Sales atau Purchasing untuk klarifikasi **sebelum** memvalidasi

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
