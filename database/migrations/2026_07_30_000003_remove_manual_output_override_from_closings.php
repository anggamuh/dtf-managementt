<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn('hasil_cetak_manual');
        });
    }

    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->decimal('hasil_cetak_manual', 15, 2)->nullable()->after('teknisi_mesin');
        });
    }
};
