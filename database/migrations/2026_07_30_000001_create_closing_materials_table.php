<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();

            // Diambil otomatis dari stock_akhir closing bulan sebelumnya
            // (atau dari stok Material saat ini kalau belum pernah closing).
            $table->decimal('stock_awal', 14, 2)->default(0);

            // Harga per satuan komponen, default dari Material->price, bisa diedit
            // kalau ada perubahan harga bulan berjalan.
            $table->decimal('harga_komponen', 14, 2)->default(0);

            // Qty barang masuk (pembelian) dalam satuan stok, dipakai di rumus pemakaian.
            $table->decimal('qty', 14, 2)->default(0);

            // Total rupiah pembelian periode ini (input manual, bisa beda dari qty*harga
            // karena harga beli aktual/diskon/dll — sesuai pola di sheet Closing kamu).
            $table->decimal('pembelian', 14, 2)->default(0);

            // Stok fisik akhir bulan (hasil stok opname saat closing).
            $table->decimal('stock_akhir', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['closing_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_materials');
    }
};
