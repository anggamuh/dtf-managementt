<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::updateOrCreate([
            'code'=>'EPUL'
        ],[
            'name'=>'EPUL'
        ]);

        Branch::updateOrCreate([
            'code'=>'RAPLY'
        ],[
            'name'=>'RAPLY'
        ]);
    }
}