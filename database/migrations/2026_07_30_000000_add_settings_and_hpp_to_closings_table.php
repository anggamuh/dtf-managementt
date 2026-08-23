<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These columns hold the *manual* inputs that used to live only in the
     * spreadsheet / HTML prototype (sheet CLOSING & HPP per Meter):
     *  - saldo_tahanan / saldo_realtime  -> tab "Closing"
     *  - gaji_karyawan / operasional / lain_lain / teknisi_mesin -> tab "HPP / Meter"
     *  - hasil_cetak_manual -> override manual untuk total meter dicetak
     *  - hpp_per_meter -> snapshot Total HPP/Meter saat closing digenerate/dikunci
     */
    public function up(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->decimal('saldo_tahanan', 15, 2)->default(0)->after('remaining_material');
            $table->decimal('saldo_realtime', 15, 2)->default(0)->after('saldo_tahanan');
            $table->decimal('gaji_karyawan', 15, 2)->default(0)->after('saldo_realtime');
            $table->decimal('operasional', 15, 2)->default(0)->after('gaji_karyawan');
            $table->decimal('lain_lain', 15, 2)->default(0)->after('operasional');
            $table->decimal('teknisi_mesin', 15, 2)->default(0)->after('lain_lain');
            $table->decimal('hasil_cetak_manual', 15, 2)->nullable()->after('teknisi_mesin');
            $table->decimal('hpp_per_meter', 15, 2)->default(0)->after('hasil_cetak_manual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) {
            $table->dropColumn([
                'saldo_tahanan',
                'saldo_realtime',
                'gaji_karyawan',
                'operasional',
                'lain_lain',
                'teknisi_mesin',
                'hasil_cetak_manual',
                'hpp_per_meter',
            ]);
        });
    }
};
