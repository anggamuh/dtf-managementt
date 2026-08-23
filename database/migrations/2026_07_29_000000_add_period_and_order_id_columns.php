<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('date');
            $table->date('period_end')->nullable()->after('period_start');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Traces each invoice item back to the order it came from, so an
            // order can be released (invoice_id set back to null) if the item
            // is removed while editing an already-created invoice.
            $table->foreignId('order_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
