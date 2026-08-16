# 🏭 Alur Produksi & Perakitan (Manufacturing)

Dokumen ini memetakan standar operasional pembuatan barang jadi (Produksi) dari bahan mentah, serta hubungannya dengan Tim Gudang dan fitur *Maklon* (subkontrak) yang ada di sistem aplikasi Anda.

## Diagram Alur Produksi

```mermaid
graph TD
    classDef prod fill:#b45309,stroke:#92400e,stroke-width:2px,color:#fff
    classDef gudang fill:#0284c7,stroke:#0369a1,stroke-width:2px,color:#fff
    classDef decision fill:#334155,stroke:#f59e0b,stroke-width:2px,color:#fff

    subgraph Fase_1 [Fase 1: Perencanaan]
        R1["Kelola Resep / BOM (Bill of Materials)"]:::prod
        O1["Buat Surat Perintah Kerja (SPK)"]:::prod
        M1{"Pilih Metode Produksi"}:::decision
    end

    subgraph Fase_2 [Fase 2: Alokasi Bahan Baku]
        G1["Gudang Menyiapkan Bahan Mentah"]:::gudang
        G2["Gudang Memotong Stok Bahan Baku"]:::gudang
    end

    subgraph Fase_3 [Fase 3: Eksekusi Lapangan]
        P1["Internal: Pabrikasi Mandiri"]:::prod
        P2["Eksternal: Kirim ke Maklon"]:::prod
    end

    subgraph Fase_4 [Fase 4: Penerimaan Hasil Produksi]
        G3["Validasi & Terima Barang Jadi (Production Receipt)"]:::gudang
        G4["Stok Barang Jadi Bertambah"]:::gudang
    end

    %% Hubungan
    R1 --> O1
    O1 --> M1
    M1 -- "Dikerjakan Sendiri" --> G1
    M1 -- "Lempar ke Subkontraktor" --> G1
    
    G1 --> G2
    
    G2 -- "Serah Terima Internal" --> P1
    G2 -- "Surat Jalan Maklon" --> P2
    
    P1 --> G3
    P2 --> G3
    
    G3 --> G4
```

---

## Penjelasan Fase

### Fase 1: Perencanaan
1. **Kelola Resep (BOM)**: Sebelum produksi dimulai, Kepala Produksi harus mendaftarkan Resep (*Bill of Materials*) ke sistem. Resep ini berisi daftar bahan mentah apa saja yang dibutuhkan untuk membuat 1 unit barang jadi.
2. **SPK & Metode**: Jika ada pesanan masuk atau stok habis, dibuatlah SPK (*Work Order*). Di sini, Kepala Produksi memutuskan apakah barang dirakit sendiri (Internal) atau diserahkan ke vendor pihak ketiga (Maklon).

### Fase 2: Alokasi Bahan Baku
3. **Persiapan & Pemotongan Stok**: Berdasarkan Resep pada SPK, sistem akan mengirimkan permintaan ke layar Gudang. Staf Gudang wajib menyiapkan fisik bahan mentah (contoh: kayu, paku, lem) dan mengklik konfirmasi. **Stok bahan mentah akan langsung berkurang di sistem.**

### Fase 3: Eksekusi
4. **Internal vs Maklon**: Bahan mentah yang sudah dikeluarkan Gudang tersebut kemudian dieksekusi. Jika internal, dirakit di ruang pabrikasi perusahaan. Jika Maklon, dikirimkan menggunakan truk ke pabrik vendor Maklon.

### Fase 4: Penerimaan Hasil Produksi (Production Receipt)
5. **Terima Barang Jadi**: Ini adalah fitur kunci di sistem Anda. Setelah proses produksi/maklon selesai, barang jadi yang sudah berwujud tidak otomatis masuk sistem. Kepala Gudang wajib masuk ke menu **Production Receipts** untuk memvalidasi kualitas (QC) dan kuantitas barang jadi yang diserahkan oleh tim pabrik/maklon.
6. **Selesai**: Setelah tombol simpan ditekan, barulah **stok barang jadi resmi bertambah** di gudang dan siap dijual ke pelanggan.
