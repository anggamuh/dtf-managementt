<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_period_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('saldo_realtime', 18, 2)->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'period_start', 'period_end'],
                'ledger_period_balance_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_period_balances');
    }
};
