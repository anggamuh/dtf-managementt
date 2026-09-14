<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_period_balances', function (Blueprint $table) {
            $table->decimal('saldo_tahanan', 18, 2)
                ->default(0)
                ->after('period_end');
        });

        /*
         * Pertahankan nilai historis dari logic lama satu kali saja.
         * Setelah migration ini, nilai berikutnya diatur manual per periode.
         */
        DB::table('ledger_period_balances')
            ->where('branch_id', 1)
            ->update(['saldo_tahanan' => 20_000_000]);

        DB::table('ledger_period_balances')
            ->where('branch_id', 2)
            ->update(['saldo_tahanan' => 10_000_000]);
    }

    public function down(): void
    {
        Schema::table('ledger_period_balances', function (Blueprint $table) {
            $table->dropColumn('saldo_tahanan');
        });
    }
};
