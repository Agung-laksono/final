# 🔙 Alur Retur & Komplain (Reverse Logistics)

Dokumen ini menjelaskan Standar Operasional Prosedur (SOP) ketika pelanggan mengembalikan barang (Retur) akibat cacat produksi, kesalahan pengiriman, atau alasan lainnya. 

Proses ini sangat penting untuk memastikan bahwa barang rusak tidak tercampur dengan stok bagus, dan pelanggan mendapatkan kompensasi yang tepat.

## Diagram Alur Retur

```mermaid
graph TD
    classDef sales fill:#ea580c,stroke:#c2410c,stroke-width:2px,color:#fff
    classDef finance fill:#9333ea,stroke:#7e22ce,stroke-width:2px,color:#fff
    classDef gudang fill:#0284c7,stroke:#0369a1,stroke-width:2px,color:#fff
    classDef customer fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff
    classDef decision fill:#334155,stroke:#f59e0b,stroke-width:2px,color:#fff
    classDef karantina fill:#7f1d1d,stroke:#ef4444,stroke-width:2px,color:#fff

    C1(["🧑‍💼 Pelanggan Mengajukan Komplain"]):::customer
    
    subgraph Fase_1 [Fase 1: Penerimaan Komplain]
        S1["Input Tiket Retur (RMA)"]:::sales
    end

    subgraph Fase_2 [Fase 2: Inspeksi Gudang]
        G1["Terima Fisik Barang Retur"]:::gudang
        G2{"Inspeksi Kondisi (QC)"}:::decision
        G3["Pindahkan ke Stok Afkir/Karantina"]:::karantina
        G4["Kembalikan ke Stok Bagus"]:::gudang
    end

    subgraph Fase_3 [Fase 3: Penyelesaian & Resolusi]
        R1{"Pilih Jenis Solusi"}:::decision
        S2["Kirim Barang Pengganti (SO Rp 0)"]:::sales
        F1["Proses Refund Uang (Credit Note)"]:::finance
    end

    %% Alur Hubungan
    C1 --> S1
    S1 --> G1
    G1 --> G2
    
    G2 -- "Barang Cacat/Rusak" --> G3
    G2 -- "Salah Kirim (Fisik Bagus)" --> G4
    
    G3 --> R1
    G4 --> R1
    
    R1 -- "Tukar Barang Baru" --> S2
    R1 -- "Kembalikan Uang" --> F1
```

---

## Penjelasan Fase

### Fase 1: Penerimaan Komplain
1. **Komplain Masuk**: Pelanggan menghubungi perusahaan (melalui Tim Sales atau Customer Service) untuk mengeluhkan barang yang bermasalah.
2. **Input Tiket Retur**: Tim Sales memasukkan dokumen retur ke dalam sistem agar tercatat secara resmi. Dokumen ini menjadi sinyal bagi tim Gudang bahwa akan ada paket retur yang datang dari pelanggan.

### Fase 2: Penerimaan & Inspeksi Gudang
3. **Terima Fisik**: Ketika kurir membawa paket retur kembali ke gudang, Kepala Gudang mencatat penerimaan barang tersebut di sistem.
4. **Inspeksi (QC)**: Gudang melakukan pengecekan kualitas terhadap barang yang dikembalikan:
   * **Stok Afkir / Karantina**: Jika barang memang rusak, cacat produksi, atau pecah, barang tersebut akan dimasukkan ke "Gudang Karantina". Barang di karantina tidak akan pernah bisa ditarik oleh sistem untuk dikirimkan ke pelanggan lain (mencegah kesalahan berulang).
   * **Stok Bagus**: Jika alasan retur hanyalah karena pelanggan salah pesan atau Gudang salah kirim varian (namun fisik barang masih tersegel dan bagus), maka barang dikembalikan ke rak "Stok Bagus" agar bisa dijual kembali.

### Fase 3: Penyelesaian (Resolusi)
Setelah inspeksi fisik selesai, sistem mengharuskan perusahaan untuk memberikan kompensasi kepada pelanggan sesuai kesepakatan:
5. **Kirim Barang Pengganti**: Jika pelanggan ingin menukar dengan barang baru, Tim Sales membuat *Sales Order* khusus penggantian dengan nilai tagihan Rp 0. Pesanan ini akan masuk ke Kanban Gudang untuk diproses (dikemas dan dikirim) seperti pesanan biasa.
6. **Refund Uang**: Jika pelanggan meminta uangnya kembali, sistem akan mengirimkan instruksi otomatis kepada Tim **Finance** untuk mencetak *Credit Note* dan melakukan transfer dana (Refund) kepada pelanggan.
