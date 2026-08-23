<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {

            $table->id();

            $table->foreignId('branch_id')->constrained();

            $table->foreignId('customer_id')->constrained();

            $table->string('invoice_number')->unique();

            $table->date('date');

            $table->decimal('subtotal', 15, 2);

            $table->decimal('discount', 15, 2)->default(0);

            $table->decimal('shipping', 15, 2)->default(0);

            $table->decimal('total', 15, 2);

            $table->decimal('paid', 15, 2)->default(0);

            $table->decimal('remaining', 15, 2)->default(0);

            $table->enum('status', [
                'draft',
                'partial',
                'paid',
            ]);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
