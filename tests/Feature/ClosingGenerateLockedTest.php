<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Closing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosingGenerateLockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_closing_generate_redirects_with_error_alert(): void
    {
        $this->seed(RoleSeeder::class);

        $branch = Branch::create([
            'name' => 'Cabang Test',
            'code' => 'TEST',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $user->assignRole('Super Admin');

        Closing::create([
            'branch_id' => $branch->id,
            'month' => 8,
            'year' => 2026,
            'income' => 1000000,
            'expense' => 500000,
            'profit' => 500000,
            'hpp' => 200000,
            'remaining_material' => 100000,
            'saldo_tahanan' => 1000000,
            'saldo_realtime' => 900000,
            'is_locked' => true,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ]);

        $response = $this->actingAs($user)
            ->from(route('closing.index', ['branch_id' => $branch->id]))
            ->post(route('closing.generate'), [
                'branch_id' => $branch->id,
                'month' => 8,
                'year' => 2026,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-31',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Closing yang sudah dikunci tidak dapat digenerate ulang.');
    }

    public function test_locked_closing_cannot_be_deleted_even_by_super_admin(): void
    {
        $this->seed(RoleSeeder::class);

        $branch = Branch::create([
            'name' => 'Cabang Test',
            'code' => 'TEST',
            'active' => true,
        ]);

        $user = User::factory()->create([
            'branch_id' => $branch->id,
        ]);
        $user->assignRole('Super Admin');

        $closing = Closing::create([
            'branch_id' => $branch->id,
            'month' => 9,
            'year' => 2026,
            'income' => 1000000,
            'expense' => 500000,
            'profit' => 500000,
            'hpp' => 200000,
            'remaining_material' => 100000,
            'saldo_tahanan' => 1000000,
            'saldo_realtime' => 900000,
            'is_locked' => true,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
        ]);

        $response = $this->actingAs($user)
            ->from(route('closing.index', ['branch_id' => $branch->id]))
            ->delete(route('closing.destroy', $closing), [
                'branch_id' => $branch->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Closing yang terkunci tidak dapat dihapus.');
        $this->assertDatabaseHas('closings', ['id' => $closing->id, 'is_locked' => true]);
    }
}
