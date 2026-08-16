# 📤 Alur Retur Pembelian (Purchase Returns)

Dokumen ini menjelaskan Standar Operasional Prosedur (SOP) saat perusahaan menemukan bahan baku/barang yang cacat dari *Vendor/Supplier*, dan harus mengembalikan barang tersebut kepada mereka.

Siklus ini merupakan kebalikan dari proses Penerimaan Barang (Inbound) dan melibatkan pembatalan komitmen Hutang (Accounts Payable).

## Diagram Alur Retur ke Vendor

```mermaid
graph TD
    classDef purchase fill:#059669,stroke:#047857,stroke-width:2px,color:#fff
    classDef finance fill:#9333ea,stroke:#7e22ce,stroke-width:2px,color:#fff
    classDef gudang fill:#0284c7,stroke:#0369a1,stroke-width:2px,color:#fff
    classDef vendor fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff
    classDef decision fill:#334155,stroke:#f59e0b,stroke-width:2px,color:#fff

    subgraph Fase_1 [Fase 1: Penemuan & Klaim]
        G1["Gudang Menemukan Barang Cacat/Expired"]:::gudang
        P1["Buat Dokumen Retur Pembelian (PRMA)"]:::purchase
    end

    subgraph Fase_2 [Fase 2: Pengeluaran Barang]
        G2["Gudang Menyiapkan & Mengemas Barang"]:::gudang
        G3["Sistem Memotong Stok Gudang"]:::gudang
        V1(["Barang Dikirim Balik ke Vendor / Supplier"]):::vendor
    end

    subgraph Fase_3 [Fase 3: Resolusi & Akuntansi]
        D1{"Pilih Resolusi dari Vendor"}:::decision
        P2["Tukar Barang Baru (Kirim Ulang)"]:::purchase
        F1["Potong Tagihan Hutang (Nota Retur / Credit Note)"]:::finance
    end

    %% Hubungan
    G1 --> P1
    P1 --> G2
    G2 --> G3
    G3 --> V1
    
    V1 --> D1
    
    D1 -- "Opsi A: Minta Ganti Fisik" --> P2
    D1 -- "Opsi B: Minta Potong Harga" --> F1
```

---

## Penjelasan Fase

### Fase 1: Penemuan Masalah
1. **Identifikasi**: Staf Gudang menemukan barang rusak, entah itu saat barang baru saja tiba, atau saat sedang melakukan *Stock Opname* di dalam rak.
2. **Klaim Retur**: Tim *Purchasing* membuat dokumen Retur Pembelian resmi di sistem yang dikirimkan ke pihak Vendor sebagai bukti komplain.

### Fase 2: Pengeluaran Fisik
3. **Pengurangan Stok**: Kepala Gudang memproses dokumen retur tersebut. Saat barang dinaikkan ke truk untuk dikirim balik, sistem secara otomatis akan **memotong persediaan stok barang** di gudang.
4. **Surat Jalan Retur**: Gudang membekali supir dengan Surat Jalan Keluar (Retur) untuk ditandatangani oleh Vendor penerima.

### Fase 3: Resolusi
Setelah barang sampai di tangan vendor, perusahaan berhak menuntut salah satu dari dua kompensasi:
5. **Tukar Barang Baru**: Jika perusahaan meminta ganti fisik, Vendor akan mengirimkan barang pengganti. Prosesnya akan mengulang *Alur Penerimaan Barang Inbound* dari awal.
6. **Potong Tagihan (Credit Note)**: Jika perusahaan tidak mau barang pengganti, maka Tim **Finance** akan menerima bukti Nota Retur (*Credit Note*). Saat jatuh tempo tagihan tiba, Finance akan membayar tagihan vendor **dikurangi nilai barang cacat** yang dikembalikan, sehingga arus kas perusahaan tetap terlindungi.
