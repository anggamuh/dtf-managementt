<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->json('persediaan_lapisan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('closing_materials', function (Blueprint $table) {
            $table->dropColumn('persediaan_lapisan');
        });
    }
};
