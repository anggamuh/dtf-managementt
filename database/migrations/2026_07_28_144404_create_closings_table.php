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
        Schema::create('closings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('branch_id')->constrained();

            $table->integer('month');

            $table->integer('year');

            $table->decimal('income', 15, 2);

            $table->decimal('expense', 15, 2);

            $table->decimal('hpp', 15, 2);

            $table->decimal('profit', 15, 2);

            $table->decimal('remaining_material', 15, 2);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closings');
    }
};
