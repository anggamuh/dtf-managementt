<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerSaldoTahananTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_saldo_tahanan_can_be_updated_and_used_in_ledger(): void
    {
        $this->seed(RoleSeeder::class);

        $branch = Branch::create([
            'name' => 'RAPLY',
            'code' => 'RAPLY',
            'active' => true,
            'saldo_tahanan' => 10000000,
        ]);

        $user = User::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user)->post(route('ledger.saldo-tahanan.update'), [
            'branch_id' => $branch->id,
            'saldo_tahanan' => 12500000,
        ]);

        $response->assertRedirect();
        $branch->refresh();
        $this->assertSame('12500000.00', (string) $branch->saldo_tahanan);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $ledgerResponse = $this->actingAs($user)->get(route('ledger.index', [
            'branch_id' => $branch->id,
            'start_date' => $monthStart,
            'end_date' => $monthEnd,
        ]));

        $ledgerResponse->assertOk();
        $ledgerResponse->assertSee('Rp 12.500.000');
    }
}
