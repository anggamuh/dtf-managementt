<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->decimal('harga_pet_1', 14, 2)->nullable();
            $table->decimal('harga_pet_2', 14, 2)->nullable();
            $table->decimal('harga_pet_3', 14, 2)->nullable();
            $table->decimal('harga_pet_4', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->dropColumn(['harga_pet_1', 'harga_pet_2', 'harga_pet_3', 'harga_pet_4']);
        });
    }
};
