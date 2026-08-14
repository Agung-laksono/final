# 📖 Alur Kerja Sistem (Micro-Steps)

Sistem ERP ini mengintegrasikan divisi Sales, Gudang, Produksi, Purchasing, dan Finance. Anda bisa mengaturnya menjadi **Mode Solo (Otomatis)** atau **Mode Tim (Verifikasi Ketat)** melalui fitur Toggle Workflow.

Berikut adalah perjalanan dari awal hingga akhir, dipecah menjadi langkah-langkah kecil (micro-steps).

---

## 1. 🛒 Fase Penjualan (Sales)

### Step 1: Input Pesanan
- **Aksi**: Tim Sales membuat pesanan pelanggan (*Sales Order* / SO).
- **Mode Tim**: Status menjadi `Menunggu Persetujuan`. Butuh klik *Approve* dari Manajemen.
- **Mode Solo**: Status otomatis `Diproses`. Lanjut ke penyiapan barang.

### Step 2: Penyiapan Barang (Fulfillment)
- **Aksi**: Sistem mengecek ketersediaan fisik stok di Gudang.
- **Stok Cukup**: Barang langsung siap untuk dikemas. Lanjut ke [Fase 5: Pengiriman](#5-fase-pengiriman-barang).
- **Stok Kurang**: Sistem mendeteksi defisit dan otomatis menerbitkan **Tiket Permintaan Gudang (Inventory Request)**. Lanjut ke fase berikutnya.

---

## 2. 📦 Fase Gudang (Pivot Keputusan)

### Step 3: Tinjauan Permintaan Gudang
- **Aksi**: Kepala Gudang membuka *Kanban Permintaan*.
- **Analisis**: Gudang melihat posisi stok saat ini (berapa fisik, berapa yang masih antre produksi, dan berapa yang sedang dibeli).

### Step 4: Routing (Pengalihan)
- **Keputusan A (Produksi)**: Jika barang adalah produk jadi, permintaan dialihkan ke pabrik. Lanjut ke [Fase 3: Produksi](#3-fase-produksi).
- **Keputusan B (Beli)**: Jika barang berupa bahan baku atau barang trading, dialihkan ke tim pembelian. Lanjut ke [Fase 4: Purchasing](#4-fase-pembelian).

---

## 3. 🏭 Fase Produksi (Pabrik & Maklon)

### Step 5: Ekstraksi Resep (BOM) & SPK
- **Aksi**: Sistem membongkar resep/BOM dari produk jadi. Jika bahan baku untuk membuat produk ini ternyata kurang, sistem akan *otomatis membuat tiket pembelian bahan baku*.
- **Terbit SPK**: Surat Perintah Kerja diterbitkan.
  - **Mode Tim**: SPK `Pending Approval`.
  - **Mode Solo**: SPK otomatis disetujui.

### Step 6: Eksekusi Produksi
- **Aksi**: Pekerja pabrik (atau vendor luar/Maklon) mengerjakan SPK tersebut hingga selesai.

### Step 7: Penerimaan & QC Produksi
- **Aksi**: Barang jadi diserahkan kembali ke Gudang.
- **Mode Tim**: Masuk kolom `Receiving`. Orang QC wajib lapor berapa *Qty Lolos* dan *Qty Cacat*.
- **Mode Solo**: Produksi dianggap langsung masuk 100% mulus (Bypass QC), stok Gudang Utama otomatis bertambah.

---

## 4. 💳 Fase Pembelian (Purchasing)

### Step 8: Antrean Pembelian (PO)
- **Aksi**: Tim Purchasing melihat daftar bahan/barang yang perlu dibeli, memilih Vendor, dan mencetak *Purchase Order* (PO).
- **Mode Tim**: PO `Pending Approval` manajemen.
- **Mode Solo**: PO otomatis *Approved*.

### Step 9: Terima Kiriman Vendor (Good Receipt)
- **Aksi**: Truk vendor datang membawa barang.
- **Mode Tim**: Wajib dicek oleh QC penerimaan.
- **Mode Solo**: Barang otomatis masuk menjadi penambahan stok di Gudang.

---

## 5. 🚚 Fase Pengiriman Barang

*Siklus kembali ke pesanan Sales awal karena kini barang sudah tersedia (berkat Produksi atau Pembelian).*

### Step 10: Pengeluaran Stok (Outbound)
- **Aksi**: Tim logistik/gudang memuat barang ke truk pengiriman.
- **Mode Tim**: Memerlukan validasi `Draft Pengiriman` dari Kepala Gudang.
- **Mode Solo**: Admin bebas potong stok langsung.
- **Hasil**: Barang dikirim ke pelanggan. Status SO menjadi `Terkirim`.

---

## 6. 💰 Fase Keuangan (Finance)

Seluruh pergerakan barang menciptakan implikasi uang:

### Step 11: Tagihan Pelanggan (Piutang / AR)
- **Aksi**: Saat pesanan Sales diproses, Finance otomatis mencatat piutang. 
- **Pembayaran**: Kasir mencatat uang masuk. SO berubah menjadi `Lunas`.

### Step 12: Tagihan Vendor (Hutang / AP)
- **Aksi**: Saat barang Vendor / Jasa Maklon selesai, Finance otomatis mencatat Hutang (tagihan).
- **Pembayaran**: Finance mentransfer vendor, mencatat pengeluaran di sistem.

> **Ringkasan**: Seluruh tahapan ERP dari pesanan hingga perputaran uang di aplikasi ini saling mengunci. Namun, dengan *Settings Workflow*, bisnis kecil bisa mempercepat proses tanpa klik berlebihan, sementara bisnis besar tetap terproteksi oleh lapisan validasi QC & Manajemen.
