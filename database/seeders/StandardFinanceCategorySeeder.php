<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StandardFinanceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // PENGELUARAN - HPP & Produksi
            ['name' => 'Bahan Baku & Material', 'type' => 'expense', 'description' => 'Pembelian bahan baku utama produksi'],
            ['name' => 'Biaya Overhead Pabrik', 'type' => 'expense', 'description' => 'Listrik pabrik, oli mesin, maintenance mesin'],
            ['name' => 'Upah Buruh Harian', 'type' => 'expense', 'description' => 'Gaji pekerja harian / borongan'],

            // PENGELUARAN - Operasional (Opex)
            ['name' => 'Gaji & Tunjangan Karyawan', 'type' => 'expense', 'description' => 'Gaji bulanan admin, finance, sales, dsb'],
            ['name' => 'Bensin & Transportasi', 'type' => 'expense', 'description' => 'Bensin operasional, tol, tiket perjalanan'],
            ['name' => 'Listrik, Air & Internet', 'type' => 'expense', 'description' => 'Utilitas kantor dan gudang non-pabrik'],
            ['name' => 'Alat Tulis Kantor (ATK)', 'type' => 'expense', 'description' => 'Kertas, tinta printer, lakban'],
            ['name' => 'Marketing & Promosi', 'type' => 'expense', 'description' => 'Iklan socmed, cetak brosur, entertain klien'],
            ['name' => 'Biaya Lain-lain (Expense)', 'type' => 'expense', 'description' => 'Pengeluaran kas kecil yang tidak terklasifikasi'],

            // PEMASUKAN - Pendapatan
            ['name' => 'Pendapatan Penjualan', 'type' => 'income', 'description' => 'Pemasukan dari penjualan barang jadi / pesanan pelanggan'],
            ['name' => 'Pendapatan Bunga Bank', 'type' => 'income', 'description' => 'Bunga dari saldo kas bank'],
            ['name' => 'Pendapatan Jasa / Lainnya', 'type' => 'income', 'description' => 'Pendapatan di luar penjualan produk utama'],
        ];

        foreach ($categories as $cat) {
            \Modules\Finance\Models\FinanceCategory::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'type' => $cat['type'],
                    'description' => $cat['description'],
                    'is_active' => true
                ]
            );
        }
    }
}
