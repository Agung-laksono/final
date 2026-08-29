# 💳 Tutorial D.7 — Input Pembayaran & Bukti Transfer

**Durasi video:** ~10 menit | **Role:** Staf Sales, Kepala Sales

---

## Tujuan Tutorial

Cara menginput pembayaran dari pelanggan dan mengupload bukti transfer setelah SO selesai dikirim.

---

## Langkah-Langkah

### 1. Kapan Input Pembayaran Dilakukan?

Input pembayaran dilakukan **setelah pelanggan mentransfer**, biasanya:
- Saat barang sudah dikirim (untuk pembayaran setelah terima)
- Saat SO dibuat (untuk pembayaran di muka / DP)
- Cicilan ke-N sesuai jadwal

### 2. Membuka Modal Pembayaran

1. Buka **Daftar Sales Order** → cari SO yang bersangkutan
2. Klik SO → buka **Modal Detail SO**
3. Di bagian bawah modal → klik tombol **💳 Input Pembayaran**

### 3. Mengisi Form Pembayaran

| Field | Keterangan |
|-------|-----------|
| **Jumlah Bayar** | Nominal yang ditransfer pelanggan (format Rupiah) |
| **Tanggal Bayar** | Tanggal transfer dari pelanggan |
| **Metode Bayar** | Transfer Bank, Tunai, QRIS, dll |
| **Bank / Rekening** | Rekening tujuan pelanggan mentransfer |
| **Bukti Transfer** | Upload foto/screenshot struk transfer |
| **Catatan** | Keterangan tambahan (opsional) |

> **Upload Bukti**: Klik area upload → pilih foto dari galeri HP atau file dari komputer. Format: JPG, PNG, PDF. Ukuran maks 5MB.

### 4. Menyimpan Pembayaran

- Klik **Simpan Pembayaran**
- Status pembayaran: **Menunggu Validasi Finance**
- Notifikasi otomatis dikirim ke tim **Keuangan (Finance)**

### 5. Melihat Status Pembayaran

Di **Detail SO** → tab **Pembayaran**:
- Riwayat semua pembayaran untuk SO ini
- Status tiap pembayaran: Menunggu / Divalidasi / Ditolak
- Sisa tagihan yang belum dibayar (jika cicilan)

---

## Alur Setelah Input Pembayaran

```
Pembayaran diinput oleh Sales
    ↓
Finance review bukti transfer (Tutorial F.4)
    ↓
Finance Validasi → Saldo akun bertambah
    ↓
SO berubah status → Lunas ✅
```

---

## Yang Perlu Diperhatikan

- Jangan input pembayaran sebelum benar-benar menerima bukti dari pelanggan
- Jika pembayaran ditolak Finance → Finance akan minta konfirmasi ulang dengan alasan
- Untuk pembayaran cicilan: input setiap kali ada transfer masuk

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
