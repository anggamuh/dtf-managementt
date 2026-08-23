<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $epul = Branch::where('code', 'EPUL')->first();
        $raply = Branch::where('code', 'RAPLY')->first();

        // Super Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@dtf.com'],
            [
                'branch_id' => $epul->id,
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ]
        );
        $admin->assignRole('Super Admin');

        // Owner
        $owner = User::updateOrCreate(
            ['email' => 'owner@dtf.com'],
            [
                'branch_id' => $epul->id,
                'name' => 'Owner',
                'password' => Hash::make('password'),
            ]
        );
        $owner->assignRole('Owner');

        // Admin EPUL
        $epulAdmin = User::updateOrCreate(
            ['email' => 'epul@dtf.com'],
            [
                'branch_id' => $epul->id,
                'name' => 'Admin EPUL',
                'password' => Hash::make('password'),
            ]
        );
        $epulAdmin->assignRole('Admin EPUL');

        // Admin RAPLY
        $raplyAdmin = User::updateOrCreate(
            ['email' => 'raply@dtf.com'],
            [
                'branch_id' => $raply->id,
                'name' => 'Admin RAPLY',
                'password' => Hash::make('password'),
            ]
        );
        $raplyAdmin->assignRole('Admin RAPLY');
    }
}