<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('price_pet_1', 15, 2)->nullable();
            $table->decimal('price_pet_2', 15, 2)->nullable();
            $table->decimal('price_pet_3', 15, 2)->nullable();
            $table->decimal('price_pet_4', 15, 2)->nullable();
        });

        DB::table('materials')->whereRaw("UPPER(name) LIKE '%PET%'")->update(['price_pet_1' => DB::raw('price')]);

        $branchId = DB::table('branches')->where('code', 'RAPLY')->value('id');
        if (! $branchId || ! Schema::hasTable('machines')) return;

        $machines = DB::table('machines')->where('branch_id', $branchId)->whereIn('head_count', [2, 4])->pluck('id');
        foreach (DB::table('materials')->where('branch_id', $branchId)->whereIn('machine_id', $machines)->orderBy('id')->get() as $source) {
            $target = DB::table('materials')->where('branch_id', $branchId)->whereNull('machine_id')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($source->name))])->first();
            if (! $target) {
                DB::table('materials')->where('id', $source->id)->update(['machine_id' => null]);
                continue;
            }

            // Keep transaction/closing foreign keys attached to their original records.
            // The visible master receives the physical stock and the old row is archived.
            DB::table('materials')->where('id', $target->id)->update([
                'stock' => DB::raw('stock + '.(float) $source->stock),
                'price' => (float) $target->price ?: (float) $source->price,
                'price_pet_1' => $target->price_pet_1 ?? $source->price_pet_1,
                'price_pet_2' => $target->price_pet_2 ?? $source->price_pet_2,
                'price_pet_3' => $target->price_pet_3 ?? $source->price_pet_3,
                'price_pet_4' => $target->price_pet_4 ?? $source->price_pet_4,
            ]);
            DB::table('materials')->where('id', $source->id)->update(['stock' => 0, 'is_active' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['price_pet_1', 'price_pet_2', 'price_pet_3', 'price_pet_4']);
        });
    }
};
