<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate([
            'code' => 'EPUL',
        ], [
            'name' => 'EPUL',
        ]);

        $raply = Branch::updateOrCreate([
            'code' => 'RAPLY',
        ], [
            'name' => 'RAPLY',
        ]);

        $raply->machines()->updateOrCreate(
            ['code' => '2H'],
            ['name' => 'Mesin 2 Head', 'head_count' => 2, 'is_active' => true]
        );
        $raply->machines()->updateOrCreate(
            ['code' => '4H'],
            ['name' => 'Mesin 4 Head', 'head_count' => 4, 'is_active' => true]
        );
    }
}
