# 🏢 Struktur Organisasi & Alur Kerja

Berdasarkan sistem dan alur kerja aplikasi, berikut adalah pemisahan antara **Struktur Organisasi** (pembagian peran) dan **Alur Kerja Operasional** (bagaimana mereka berinteraksi).

## 1. Bagan Struktur Organisasi

Ini adalah garis komando murni untuk menunjukkan siapa melapor kepada siapa tanpa adanya alur kerja yang menyilang.

```mermaid
graph TD
    %% Styling
    classDef topLevel fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#f8fafc
    classDef gudang fill:#0284c7,stroke:#0369a1,stroke-width:2px,color:#f0f9ff
    classDef purchase fill:#059669,stroke:#047857,stroke-width:2px,color:#ecfdf5
    classDef sales fill:#ea580c,stroke:#c2410c,stroke-width:2px,color:#fff7ed
    classDef finance fill:#9333ea,stroke:#7e22ce,stroke-width:2px,color:#faf5ff
    classDef prod fill:#b45309,stroke:#92400e,stroke-width:2px,color:#fffbeb

    M[Manajemen & Super Admin]:::topLevel

    M --- G[Gudang & Logistik]:::gudang
    M --- S[Sales & Marketing]:::sales
    M --- P[Purchasing]:::purchase
    M --- R[Produksi]:::prod
    M --- F[Finance & Keuangan]:::finance

    G --- G1["Kepala Gudang (PPIC, Opname)"]:::gudang
    G1 --- G2["Staf Gudang (Inbound & Fulfillment)"]:::gudang

    S --- S1["Kepala Sales (ACC Pesanan)"]:::sales
    S1 --- S2["Tim Sales (Input SO)"]:::sales

    P --- P1["Kepala Purchasing (ACC Vendor)"]:::purchase
    P1 --- P2["Staf Purchasing (Buat PO)"]:::purchase

    R --- R1["Kepala Produksi (Kelola Resep/BOM)"]:::prod
    R1 --- R2["Tim Pabrik/Lapangan (Eksekusi SPK)"]:::prod

    F --- F1["Kepala Finance (Validasi Pembayaran)"]:::finance
    F1 --- F2["Staf Finance (Pencatatan Ledger)"]:::finance
```

---

## 2. Alur Kerja (Order to Cash)

Berikut adalah urutan proses (*Sequence Diagram*) dari saat Sales mendapatkan pesanan, proses pemenuhan barang oleh Gudang, hingga pelunasan tagihan oleh Finance.

```mermaid
graph LR
    classDef step fill:#1e293b,stroke:#3b82f6,stroke-width:2px,color:#fff
    classDef decision fill:#334155,stroke:#f59e0b,stroke-width:2px,color:#fff
    classDef done fill:#065f46,stroke:#10b981,stroke-width:2px,color:#fff
    
    A(["1. Sales Input Pesanan"]):::step
    B{"2. Cek Pembayaran"}:::decision
    C(["3. Penyiapan Gudang"]):::step
    D{"4. Stok Barang Cukup?"}:::decision
    E{"5. Cek Bahan Baku"}:::decision
    F(["6. Beli Bahan (PO)"]):::step
    G(["7. Produksi (SPK)"]):::step
    H(["8. Kirim Barang"]):::step
    I(["9. Pesanan Selesai"]):::done

    %% Alur Awal
    A --> B
    B -- Ditolak --> A
    B -- ACC --> C
    
    C --> D
    
    %% Alur Produksi & Pembelian (Jika Stok Kurang)
    D -- Kurang --> E
    E -- Bahan Kurang --> F
    F -- Bahan Tiba --> G
    E -- Bahan Cukup --> G
    G -- Barang Jadi --> C
    
    %% Alur Pengiriman (Jika Stok Cukup)
    D -- Cukup --> H
    H --> I
```

## Deskripsi Singkat

1. **Gudang & Logistik (Jantung Fisik)**
   Bertugas menerima barang masuk (Inbound) dari vendor, menyiapkan barang keluar (Fulfillment) untuk pelanggan Sales, dan memastikan fisik stok akurat (Stock Opname). Di lapangan, Staf Gudang mengomando tim angkat/packing tanpa mengharuskan kuli/pekerja lapangan memegang aplikasi.
   
2. **Sales & Marketing (Garda Depan)**
   Mencari pelanggan dan memasukkan pesanan pesanan (Sales Order) ke sistem. Pesanan ini akan terlihat di Kanban Gudang setelah mendapat konfirmasi pembayaran dari tim Finance.

3. **Purchasing (Pengadaan)**
   Memastikan gudang tidak pernah kehabisan barang dagangan maupun bahan baku produksi dengan cara merespons peringatan dari Kepala Gudang dan melakukan pemesanan (Purchase Order) ke vendor eksternal.

4. **Produksi (Perakitan)**
   Jika perusahaan memiliki sistem rakit (manufaktur), divisi ini meminta bahan baku dari Kepala Gudang (PPIC), memproses SPK, dan meretur barang jadi kembali ke Gudang untuk dijual oleh tim Sales.

5. **Finance (Arus Kas)**
   Memvalidasi bahwa pesanan yang dibuat tim Sales benar-benar sudah dibayar (mencegah *fraud*), melunasi tagihan vendor dari tim Purchasing, serta menjaga kesehatan keuangan secara umum.
