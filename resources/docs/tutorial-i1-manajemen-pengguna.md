# 👥 Tutorial I.1 — Manajemen Pengguna

**Durasi video:** ~12 menit | **Role:** Super Admin

---

## Tujuan Tutorial

Cara menambah, mengedit, dan mengatur hak akses (role) untuk semua pengguna sistem.

---

## Langkah-Langkah

### 1. Membuka Manajemen Pengguna

1. Login sebagai **Super Admin**
2. Klik menu **LAINNYA → Pengaturan Aplikasi**
3. Di sidebar pengaturan → klik **Pengguna**
4. Tampil daftar semua pengguna aktif

### 2. Melihat Daftar Pengguna

Tabel pengguna menampilkan:
- Foto profil + nama
- Email & username
- Role yang dipegang
- Brand/divisi yang di-assign
- Status aktif/nonaktif

### 3. Menambah Pengguna Baru

1. Klik tombol **+ Tambah Pengguna**
2. Isi form:

| Field | Keterangan |
|-------|-----------|
| **Nama Lengkap** | Nama resmi karyawan |
| **Email** | Email aktif (untuk login & notifikasi) |
| **Username** | Nama unik untuk mention di sistem |
| **Nomor HP** | Untuk notifikasi WhatsApp via Fonnte |
| **Password** | Password awal (disarankan minta ganti saat pertama login) |
| **Foto Profil** | Upload foto karyawan (opsional) |
| **Brand** | Assign ke brand/divisi tertentu |

3. Klik **Simpan** — pengguna langsung bisa login

### 4. Assign Role ke Pengguna

Role menentukan apa yang bisa dilakukan pengguna di sistem.

1. Di daftar pengguna → klik ikon **🔑 Assign Role** di baris pengguna
2. Modal **Assign Role** terbuka
3. Pilih role yang sesuai dengan jabatan karyawan:
   - Misal: Staf gudang → pilih **"Staf Gudang"**
   - Misal: Kepala divisi purchasing → pilih **"Kepala Purchasing"**
4. Klik **Simpan**
5. Perubahan langsung aktif saat pengguna refresh halaman

### 5. Assign Pengguna ke Gudang

Untuk staf gudang — tentukan gudang mana yang boleh diakses:

1. Klik ikon **Pengaturan Gudang** di baris pengguna
2. Centang gudang-gudang yang boleh diakses staf ini
3. Klik **Simpan**

### 6. Edit Data Pengguna

1. Klik ikon **✏️ Edit** di baris pengguna
2. Ubah data yang diperlukan
3. Klik **Simpan Perubahan**

### 7. Nonaktifkan Pengguna

Gunakan ini saat karyawan resign (jangan hapus, agar data historis terjaga):

1. Klik tombol **Nonaktifkan** di baris pengguna
2. Konfirmasi
3. Pengguna tidak bisa login lagi, data historis tetap ada

---

## Panduan Assign Role per Jabatan

| Jabatan | Role yang Diberikan |
|---------|---------------------|
| Admin IT / Pemilik | Super Admin |
| Manager Operasional | Manager |
| Kepala Gudang | Kepala Gudang |
| Staf Gudang / Logistik | Staf Gudang |
| Staf Packing / Pengiriman | Staf Gudang Fulfillment |
| Staf PPIC | Staf Gudang PPIC |
| Kepala Purchasing | Kepala Purchasing |
| Staf Purchasing | Staf Purchasing |
| Sales Supervisor | Kepala Sales |
| Sales Executive | Staf Sales |
| Finance Manager | Kepala Finance |
| Staf Akuntansi | Staf Finance |
| Production Manager | Kepala Produksi |
| Operator Produksi | Staf Produksi |

---

## Yang Perlu Diperhatikan

- Satu pengguna hanya boleh memiliki **satu role aktif**
- Role **Super Admin** hanya untuk admin IT/sistem, bukan untuk kepala divisi
- Jika pengguna berganti jabatan, ubah rolenya segera

---

[← Kembali ke Indeks Tutorial](/docs/panduan-video-tutorial)
