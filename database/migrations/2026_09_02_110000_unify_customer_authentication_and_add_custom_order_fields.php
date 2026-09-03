<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('whatsapp', 30)->nullable()->after('name'));
        Schema::table('branches', fn (Blueprint $table) => $table->boolean('active')->default(true)->after('code'));
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('order_type')->default('staff')->after('user_id')->index();
            $table->text('request_text')->nullable()->after('order_type');
            $table->string('design_file_path')->nullable()->after('request_text');
            $table->string('design_file_original_name')->nullable()->after('design_file_path');
            $table->text('customer_note')->nullable()->after('note');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
            $table->string('status')->default('waiting')->change();
        });

        // Migration korektif: autentikasi customer kini hanya melalui users.
        if (Schema::hasColumn('customers', 'email')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique(['email']);
                $table->dropColumn(['email', 'password', 'remember_token']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['order_type', 'request_text', 'design_file_path', 'design_file_original_name', 'customer_note']);
        });
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('active'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp'));
    }
};
