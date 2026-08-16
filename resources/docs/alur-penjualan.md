# 📈 Alur Penjualan (Sales Flow)

Dokumen ini menjelaskan Standar Operasional Prosedur (SOP) dari sisi siklus pendapatan (*Order-to-Cash*), mulai dari penerimaan pesanan pelanggan hingga uang masuk ke perusahaan.

## Diagram Alur Penjualan

```mermaid
graph TD
    classDef sales fill:#ea580c,stroke:#c2410c,stroke-width:2px,color:#fff
    classDef finance fill:#9333ea,stroke:#7e22ce,stroke-width:2px,color:#fff
    classDef gudang fill:#0284c7,stroke:#0369a1,stroke-width:2px,color:#fff
    classDef customer fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff
    classDef decision fill:#334155,stroke:#f59e0b,stroke-width:2px,color:#fff

    C1(["🧑‍💼 Pelanggan (Kirim PO / Inquiry)"]):::customer
    
    subgraph Fase_1 [Fase 1: Penerimaan Pesanan]
        S1["Tawarkan Harga (Quotation)"]:::sales
        S2["Input Draft Sales Order (SO)"]:::sales
    end

    subgraph Fase_2 [Fase 2: Validasi Keuangan]
        F1{"Cek Piutang / Uang Muka"}:::decision
        F2["Pesanan Disetujui (ACC)"]:::finance
    end

    subgraph Fase_3 [Fase 3: Pemenuhan Gudang]
        G1["SO Muncul di Kanban Gudang"]:::gudang
        G2["Tarik Fisik Barang & Packing"]:::gudang
    end

    subgraph Fase_4 [Fase 4: Pengiriman]
        G3["Cetak Surat Jalan (DO)"]:::gudang
        G4["Barang Diserahkan ke Ekspedisi"]:::gudang
    end

    subgraph Fase_5 [Fase 5: Penagihan & Pelunasan]
        F3["Terbitkan Invoice (Faktur)"]:::finance
        C2(["Pelanggan Melakukan Transfer Uang"]):::customer
        F4["Validasi Kas Masuk (Pesanan Lunas)"]:::finance
    end

    %% Hubungan antar langkah
    C1 --> S1
    S1 --> S2
    S2 --> F1
    
    F1 -- "Limit Habis / Belum DP" --> S2
    F1 -- "Keuangan Aman" --> F2
    
    F2 --> G1
    G1 --> G2
    G2 --> G3
    G3 --> G4
    
    G4 --> F3
    F3 --> C2
    C2 --> F4
```

---

## Penjelasan Fase

### 1. Penerimaan Pesanan (*Order Intake*)
Perjalanan dimulai ketika prospek/pelanggan melakukan inkuiri barang. Tim **Sales** dapat menerbitkan *Quotation* (Penawaran) terlebih dahulu jika diminta. Saat pelanggan setuju (biasanya dengan menerbitkan *Purchase Order* / PO Pelanggan), tim Sales wajib menginput data pesanan tersebut ke dalam sistem sebagai **Draft Sales Order (SO)**.

### 2. Validasi & Persetujuan (*Approval*)
Sebelum pesanan bisa dilayani oleh Gudang, pesanan wajib melewati pos penjagaan **Finance**. Jika pesanan bersifat *Tempo/Kredit*, Finance mengecek apakah *Limit Kredit* pelanggan tersebut masih tersedia dan apakah ia memiliki rekam jejak pembayaran yang menunggak. Jika pesanan bersifat *Tunai*, Finance mengecek apakah uang DP sudah masuk ke rekening bank. Jika ditolak, status SO dikembalikan ke tim Sales. Jika lolos, status SO di-ACC.

### 3. Pemenuhan Gudang (*Fulfillment*)
Hanya SO yang sudah berstatus ACC yang akan otomatis muncul di layar Kanban **Kepala Gudang**. Kepala Gudang kemudian menginstruksikan tim lapangan untuk menarik barang fisik dari rak (*picking*) dan membungkusnya (*packing*).

### 4. Pengiriman (*Delivery*)
Setelah kardus siap, sistem akan digunakan untuk mencetak **Surat Jalan (*Delivery Order / DO*)**. Barang beserta surat jalan ini diserahkan kepada kurir atau armada ekspedisi.

### 5. Penagihan & Pelunasan (*Invoicing & Cash Collection*)
Berdasarkan Surat Jalan, tim **Finance** mencetak **Faktur (*Invoice*)** untuk ditagihkan kepada pelanggan. Pelanggan melakukan pembayaran. Terakhir, tim Finance mencatat penerimaan uang (*Incoming Payment*) di dalam sistem, yang akan mengubah status Sales Order tersebut menjadi **Selesai / Lunas**, menutup siklus pendapatan ini.
