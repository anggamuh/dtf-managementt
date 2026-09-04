<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->unsignedSmallInteger('head_count');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'code']);
            $table->index(['branch_id', 'is_active']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('machines')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('supplier');
            $table->index(['branch_id', 'machine_id', 'is_active']);
            $table->unique(['branch_id', 'machine_id', 'name'], 'materials_branch_machine_name_unique');
        });

        // Kode cabang ini berasal dari master branches yang sudah digunakan project.
        // Tidak ada stok atau material lama yang diubah oleh migration ini.
        $raplyBranchId = DB::table('branches')->where('code', 'RAPLY')->value('id');

        if ($raplyBranchId) {
            $now = now();

            DB::table('machines')->upsert([
                [
                    'branch_id' => $raplyBranchId,
                    'name' => 'Mesin 2 Head',
                    'code' => '2H',
                    'head_count' => 2,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'branch_id' => $raplyBranchId,
                    'name' => 'Mesin 4 Head',
                    'code' => '4H',
                    'head_count' => 4,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['branch_id', 'code'], ['name', 'head_count', 'is_active', 'updated_at']);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropUnique('materials_branch_machine_name_unique');
            $table->dropIndex(['branch_id', 'machine_id', 'is_active']);
            $table->dropConstrainedForeignId('machine_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('machines');
    }
};
