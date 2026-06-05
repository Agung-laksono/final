<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
    {
        Schema::create('item_labels', function (Blueprint $table) {
            $table->id();
            
            // 1. Barang ini jenisnya apa? (Tersambung ke tabel items)
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            
            // 2. Kode unik/Barcode khusus untuk 1 fisik barang (contoh: LMR-001-XYZ)
            $table->string('label_code')->unique();
            
            // 3. Status fisik barang ini sekarang
            // (Contoh: 'in_stock', 'sold', 'broken', 'in_transit')
            $table->string('status')->default('in_stock');
            
            // 4. (Sangat Penting) Fisik barang ini sedang ada di gudang mana?
            // Jika dipindah (transfer), field ini yang akan diupdate
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            
            // 5. (Opsional) Catatan khusus untuk fisik barang ini (misal: "Ada lecet di ujung")
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_labels');
    }
};
