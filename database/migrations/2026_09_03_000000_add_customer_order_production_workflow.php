<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('design_type')->nullable()->after('order_type')->index();
            $table->unsignedBigInteger('production_queue_number')->nullable()->after('status');
            $table->timestamp('estimated_completion_at')->nullable()->after('production_queue_number');
            $table->text('internal_note')->nullable()->after('customer_note');
            $table->string('fulfillment_method')->default('pickup')->after('internal_note');
            $table->string('recipient_name')->nullable()->after('fulfillment_method');
            $table->string('recipient_whatsapp', 30)->nullable()->after('recipient_name');
            $table->text('shipping_address')->nullable()->after('recipient_whatsapp');
            $table->string('shipping_city')->nullable()->after('shipping_address');
            $table->string('shipping_district')->nullable()->after('shipping_city');
            $table->string('shipping_postal_code', 10)->nullable()->after('shipping_district');
            $table->string('courier')->nullable()->after('shipping_postal_code');
            $table->decimal('shipping_cost', 15, 2)->default(0)->after('courier');
            $table->string('tracking_number')->nullable()->after('shipping_cost');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->index(['branch_id', 'order_type', 'payment_status', 'status'], 'orders_production_lookup');
            $table->index(['user_id', 'order_type', 'created_at'], 'orders_customer_lookup');
        });

        Schema::create('order_design_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'version']);
            $table->index(['order_id', 'kind']);
        });

        Schema::create('order_design_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_design_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('note')->nullable();
            $table->boolean('customer_visible')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('provider')->nullable();
            $table->string('external_transaction_id')->nullable()->unique();
            $table->string('method');
            $table->string('status')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('gateway_fee', 15, 2)->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status')->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('product_total', 15, 2);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoices');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_design_reviews');
        Schema::dropIfExists('order_design_files');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_production_lookup');
            $table->dropIndex('orders_customer_lookup');
            $table->dropColumn(['design_type','production_queue_number','estimated_completion_at','internal_note','fulfillment_method','recipient_name','recipient_whatsapp','shipping_address','shipping_city','shipping_district','shipping_postal_code','courier','shipping_cost','tracking_number','shipped_at','delivered_at']);
        });
    }
};
