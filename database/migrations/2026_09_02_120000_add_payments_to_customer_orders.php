<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('orders',function(Blueprint $t){
   $t->string('payment_method')->nullable()->after('order_type');$t->string('payment_status')->default('unpaid')->after('payment_method')->index();
   $t->string('payment_reference')->nullable()->unique()->after('payment_status');$t->string('payment_gateway')->nullable()->after('payment_reference');
   $t->string('gateway_transaction_id')->nullable()->unique()->after('payment_gateway');$t->string('snap_token')->nullable()->after('gateway_transaction_id');
   $t->json('gateway_payload')->nullable()->after('snap_token');$t->timestamp('paid_at')->nullable();$t->timestamp('payment_expired_at')->nullable();$t->text('payment_rejection_reason')->nullable();
  });
  Schema::create('payment_accounts',function(Blueprint $t){$t->id();$t->foreignId('branch_id')->constrained()->cascadeOnDelete();$t->string('bank_name');$t->string('account_number');$t->string('account_holder');$t->text('instructions')->nullable();$t->boolean('is_active')->default(true);$t->timestamps();$t->index(['branch_id','is_active']);});
  Schema::create('payment_confirmations',function(Blueprint $t){$t->id();$t->foreignId('order_id')->constrained()->cascadeOnDelete();$t->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();$t->string('sender_name');$t->string('sender_bank');$t->date('transferred_at');$t->decimal('amount',15,2);$t->string('proof_path');$t->string('proof_original_name');$t->text('note')->nullable();$t->string('status')->default('pending')->index();$t->text('rejection_reason')->nullable();$t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('verified_at')->nullable();$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('payment_confirmations');Schema::dropIfExists('payment_accounts');Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn(['payment_method','payment_status','payment_reference','payment_gateway','gateway_transaction_id','snap_token','gateway_payload','paid_at','payment_expired_at','payment_rejection_reason']));}
};
