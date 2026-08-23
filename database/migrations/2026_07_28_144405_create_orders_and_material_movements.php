<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('order_number')->unique();
            $table->date('date');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('size')->nullable();
            $table->decimal('qty', 12, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->enum('status', ['waiting', 'processing', 'printed', 'completed', 'cancelled'])->default('waiting');
            $table->text('note')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['purchase', 'usage', 'opname']);
            $table->decimal('quantity', 12, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
        Schema::table('materials', function (Blueprint $table) { $table->string('supplier')->nullable()->after('price'); });
        Schema::table('invoice_items', function (Blueprint $table) { $table->string('description')->nullable()->after('product_id'); $table->string('size')->nullable()->after('description'); });
        Schema::table('closings', function (Blueprint $table) { $table->boolean('is_locked')->default(false)->after('remaining_material'); $table->timestamp('locked_at')->nullable()->after('is_locked'); $table->unique(['branch_id', 'month', 'year']); });
    }
    public function down(): void
    {
        Schema::table('closings', function (Blueprint $table) { $table->dropUnique(['branch_id', 'month', 'year']); $table->dropColumn(['is_locked', 'locked_at']); });
        Schema::table('invoice_items', function (Blueprint $table) { $table->dropColumn(['description', 'size']); });
        Schema::table('materials', function (Blueprint $table) { $table->dropColumn('supplier'); });
        Schema::dropIfExists('material_movements'); Schema::dropIfExists('orders');
    }
};
