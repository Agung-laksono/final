# Panduan Jobdesk, Role, dan Permission Sistem

Dokumen ini berisi panduan lengkap mengenai definisi setiap peran (role) dalam sistem, deskripsi pekerjaan (jobdesk) yang sesuai dengan peran tersebut, serta daftar hak akses (permission) yang secara bawaan (default) diberikan kepada peran tersebut.

> [!NOTE]
> Panduan ini didasarkan pada pengaturan sistem standar. Administrator (Super Admin) memiliki kewenangan untuk menambah atau mengurangi hak akses secara spesifik untuk masing-masing user melalui menu **Settings > Users & Roles**.

---

## 1. Manajemen Eksekutif

### Super Admin
- **Jobdesk:** Administrator tertinggi sistem. Bertanggung jawab penuh atas konfigurasi sistem, manajemen pengguna, dan pengaturan keamanan.
- **Hak Akses Khusus:** Memiliki akses absolut tanpa batas ke seluruh modul, fitur, persetujuan (approval), pengaturan sistem, dan penghapusan data. (Bypass seluruh pengecekan).

### Manager
- **Jobdesk:** Pimpinan yang mengawasi seluruh operasional bisnis lintas divisi. Membutuhkan pandangan menyeluruh (helicopter view) terhadap semua data di semua departemen tanpa harus mengelola hal teknis admin.
- **Hak Akses:** Diberikan seluruh hak akses yang ada di sistem (View, Create, Update, Delete) pada seluruh modul, identik dengan Super Admin namun tunduk pada validasi standar (tidak bypass sistem).

---

## 2. Divisi Gudang & Logistik (Inventory)

### Kepala Gudang
- **Jobdesk:** Mengawasi dan mengelola seluruh aktivitas gudang, mencakup pergerakan barang (Inbound & Outbound), pengelolaan master data barang dan gudang, serta stok opname.
- **Hak Akses (Permissions):**
  - **Master Data:** Kelola penuh (View, Create, Update, Delete) untuk Data Barang, Gudang/Lokasi.
  - **Operasional:** Kelola penuh Transfer, Mutasi, dan Opname.
  - **Inbound/Outbound:** Akses melihat Alokasi Kedatangan (Dispatch) dan Penerimaan (Receipt).
  - **Fulfillment:** Akses penuh ke Pemenuhan Produksi dan Pengiriman Penjualan.
  - **Lintas Divisi:** Dapat melihat Antrian Pembelian (Purchase Queue) dan Pesanan Penjualan (Sales Order) untuk persiapan barang.

### Staf Gudang
- **Jobdesk:** Eksekutor operasional harian gudang, memindahkan barang, menerima barang masuk, dan mengemas barang untuk keluar.
- **Hak Akses (Permissions):**
  - **View Only:** Melihat Data Barang, Gudang/Lokasi, Dashboard Inventory, Riwayat Mutasi, Sales Order, dan Production Order.
  - **Operasional Dasar:** Dapat membuat (Create) dan mengupdate Transfer Barang, Opname, dan Request Barang.
  - **Fulfillment:** Mengakses Penerimaan (Receipt), Pemenuhan bahan baku Produksi, serta Pengiriman Penjualan. (Tanpa hak Hapus/Delete).

### Staf Gudang PPIC *(Production Planning and Inventory Control)*
- **Jobdesk:** Menjembatani kebutuhan material antara gudang dan divisi produksi. Mengatur stok khusus untuk pemenuhan resep produksi.
- **Hak Akses (Permissions):**
  - **Inventory:** Melihat Data Barang dan Gudang. Mengelola Transfer, Permintaan (Request), dan Mutasi.
  - **Produksi:** Akses melihat (View) Dashboard Produksi, Resep, dan mengelola (Create, Update) Production Order.

### Staf Gudang Fulfillment
- **Jobdesk:** Staf gudang yang difokuskan 100% pada bagian Outbound (pengeluaran barang) untuk memenuhi pesanan pelanggan (Sales) dan pabrik (Produksi).
- **Hak Akses (Permissions):**
  - Hanya dapat melihat Data Barang, Gudang, Mutasi.
  - Hak akses terbatas HANYA pada menu **Pemenuhan Produksi** dan **Pengiriman Penjualan**.

---

## 3. Divisi Purchasing (Pembelian)

### Kepala Purchasing
- **Jobdesk:** Bertanggung jawab atas pengadaan barang, menyetujui vendor, dan melakukan persetujuan (approval) pembelian berdasarkan permintaan gudang atau divisi lain.
- **Hak Akses (Permissions):**
  - **Akses Penuh Pembelian:** Kelola penuh (CRUD) untuk Purchase Queue, Purchase Order, dan Vendor.
  - **Approval:** Memiliki hak `purchase.approve.update` dan `purchase.approve.delete` untuk menyetujui atau menolak pesanan pembelian.
  - **Akses Lintas Divisi:** Dapat mengakses menu Pengaturan dan melihat data Master Inventory (Barang & Gudang) sebagai referensi harga.

### Staf Purchasing
- **Jobdesk:** Mencari vendor, menginput penawaran, dan membuat draf pesanan pembelian (Purchase Order).
- **Hak Akses (Permissions):**
  - **Operasional Pembelian:** Dapat Membuat dan Mengubah Purchase Queue, Purchase Order, dan Vendor. (Tanpa hak Hapus/Delete).
  - **Akses Lintas Divisi:** Dapat melihat Data Barang dan Gudang di modul Inventory. (TIDAK bisa melakukan ACC Pembelian).

---

## 4. Divisi Sales (Penjualan)

### Kepala Sales
- **Jobdesk:** Mengelola target penjualan, menyetujui pesanan dari pelanggan, dan mengelola database klien/pelanggan.
- **Hak Akses (Permissions):**
  - **Akses Penuh Penjualan:** Kelola penuh (CRUD) Customer dan Sales Order.
  - **Approval:** Memiliki hak `sales.approve.update` untuk meng-ACC / menyetujui Sales Order agar diteruskan ke gudang.
  - **Keuangan (Awal):** Dapat menginput/membuat Catatan Pembayaran (`sales.payment.create`) dari klien.
  - **Lintas Divisi:** Mengakses menu Pengaturan dan melihat ketersediaan barang di Inventory.

### Staf Sales
- **Jobdesk:** Melayani klien, membuat quotation (penawaran), dan menginput pesanan (Sales Order) baru.
- **Hak Akses (Permissions):**
  - **Operasional Penjualan:** Dapat Membuat dan Mengubah data Customer dan Sales Order. (Tanpa hak Hapus/Delete dan tanpa hak Approval/ACC).
  - **Keuangan (Awal):** Dapat menginput bukti bayar (`sales.payment.create`).
  - **Lintas Divisi:** Hanya bisa melihat Data Barang (untuk cek stok).

---

## 5. Divisi Finance (Keuangan)

### Kepala Finance
- **Jobdesk:** Mengelola arus kas perusahaan, menyetujui pencairan dana, validasi pembayaran pelanggan, dan memantau hutang piutang.
- **Hak Akses (Permissions):**
  - **Akses Penuh Keuangan:** Kelola penuh (CRUD) Inbox Keuangan, Akun Bank/Kas, Kategori Jurnal, Buku Besar (Ledger), Transfer Kas, dan Hutang (Payables).
  - **Validasi:** Memiliki hak `sales.payment.validate` untuk memvalidasi uang masuk dari Sales.
  - **Lintas Divisi:** Mengakses menu Pengaturan, melihat Sales Order, dan melihat Master Data Barang (Inventory).

### Staf Finance
- **Jobdesk:** Mencatat transaksi kasbon, menjurnal pengeluaran rutin, dan mengurus administrasi hutang harian.
- **Hak Akses (Permissions):**
  - **Operasional Keuangan:** Melihat Akun, Kategori, Buku Besar, dan Hutang. Dapat Membuat (Create) Buku Besar dan Transfer Kas. (Tanpa hak Hapus/Delete).
  - **Validasi Dasar:** Mengubah data di Inbox keuangan (`finance.inbox.update`).
  - **Lintas Divisi:** Melihat Sales Order dan Data Barang.

---

## 6. Divisi Produksi

### Kepala Produksi
- **Jobdesk:** Membuat standardisasi resep produksi (Bill of Materials) dan mengawasi jadwal serta hasil produksi pabrik/dapur.
- **Hak Akses (Permissions):**
  - **Akses Penuh Produksi:** Kelola penuh (CRUD) untuk Resep Produksi dan Perintah Produksi (Production Order).
  - **Lintas Divisi:** Dapat mengakses menu Pengaturan dan melihat Data Barang/Gudang di Inventory untuk memantau bahan baku.

### Staf Produksi
- **Jobdesk:** Melaksanakan kegiatan produksi sesuai resep dan mengupdate status pengerjaan (Work In Progress).
- **Hak Akses (Permissions):**
  - **Operasional Produksi:** Melihat Resep Produksi. Dapat Melihat dan Mengubah (Update status) Production Order. (Tidak bisa membuat/menghapus resep).
  - **Lintas Divisi:** Melihat Data Barang dan Gudang.

---

## 7. Divisi Marketing

### Kepala Marketing
- **Jobdesk:** Mengawasi kampanye pemasaran, menganalisis konversi penjualan dari iklan.
- **Hak Akses (Permissions):**
  - Melihat Dashboard Penjualan (Sales Dashboard).
  - Melihat Data Barang (Inventory).
  - Mengakses menu Pengaturan Aplikasi.

### Staf Marketing
- **Jobdesk:** Melaksanakan kampanye pemasaran, monitoring stok untuk promosi produk yang berlebih.
- **Hak Akses (Permissions):**
  - Melihat Dashboard Penjualan (Sales Dashboard).
  - Melihat Data Barang (Inventory).

---

> [!TIP]
> **Catatan Teknis untuk Hak Akses Khusus:**
> 1. Hak untuk membaca, mengubah, menambah, atau menghapus Konten (Berita/Artikel) diatur secara terpisah pada Modul **CMS (`cms.posts.*`)**. Saat ini tidak ada role default yang memiliki izin tersebut kecuali Manager/Super Admin. Anda dapat memberikannya secara manual kepada divisi terkait (misal: Marketing).
> 2. Hak **Notifikasi** (contoh: `inventory.notifikasi.view`) disetel secara spesifik untuk masing-masing role agar notifikasi yang masuk tidak saling tercampur antar divisi.
