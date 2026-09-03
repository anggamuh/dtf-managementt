<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->timestamp('saldo_realtime_locked_at')
                ->nullable()
                ->after('saldo_realtime');
        });
    }

    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn('saldo_realtime_locked_at');
        });
    }
};
