<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->decimal('harga_persediaan', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->dropColumn('harga_persediaan');
        });
    }
};
