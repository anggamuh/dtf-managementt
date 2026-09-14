<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyClosingValidationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $epul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->epul = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
    }

    public function test_weekly_closing_accepts_valid_range(): void
    {
        $user = User::factory()->create(['branch_id' => $this->epul->id]);
        $user->assignRole('Admin EPUL');

        $this->actingAs($user)
            ->withSession(['selected_branch_id' => $this->epul->id])
            ->get(route('orders.weekly-closing', [
                'branch_id' => $this->epul->id,
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-07',
            ]))
            ->assertOk();
    }

    public function test_weekly_closing_rejects_reverse_range_and_keeps_input(): void
    {
        $user = User::factory()->create(['branch_id' => $this->epul->id]);
        $user->assignRole('Admin EPUL');

        $this->actingAs($user)
            ->withSession(['selected_branch_id' => $this->epul->id])
            ->from(route('orders.weekly-closing', ['branch_id' => $this->epul->id]))
            ->get(route('orders.weekly-closing', [
                'branch_id' => $this->epul->id,
                'start_date' => '2026-09-07',
                'end_date' => '2026-09-01',
            ]))
            ->assertRedirect(route('orders.weekly-closing', ['branch_id' => $this->epul->id]))
            ->assertSessionHasErrors(['end_date']);
    }

    public function test_weekly_closing_rejects_invalid_or_empty_dates(): void
    {
        $user = User::factory()->create(['branch_id' => $this->epul->id]);
        $user->assignRole('Admin EPUL');

        $this->actingAs($user)
            ->withSession(['selected_branch_id' => $this->epul->id])
            ->from(route('orders.weekly-closing', ['branch_id' => $this->epul->id]))
            ->get(route('orders.weekly-closing', [
                'branch_id' => $this->epul->id,
                'start_date' => 'not-a-date',
                'end_date' => '',
            ]))
            ->assertRedirect(route('orders.weekly-closing', ['branch_id' => $this->epul->id]))
            ->assertSessionHasErrors(['start_date', 'end_date']);
    }

    public function test_weekly_closing_requires_authentication(): void
    {
        $this->get(route('orders.weekly-closing', [
            'branch_id' => $this->epul->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
        ]))->assertRedirect(route('login'));
    }
}
