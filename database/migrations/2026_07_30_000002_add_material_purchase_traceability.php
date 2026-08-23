<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 2)->nullable()->after('amount');
            $table->index(['branch_id', 'category', 'date']);
        });

        Schema::table('material_movements', function (Blueprint $table) {
            $table->foreignId('expense_id')->nullable()->after('material_id')->constrained()->cascadeOnDelete();
            $table->unique('expense_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_movements', function (Blueprint $table) {
            $table->dropUnique(['expense_id']);
            $table->dropConstrainedForeignId('expense_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'category', 'date']);
            $table->dropConstrainedForeignId('material_id');
            $table->dropColumn('quantity');
        });
    }
};
