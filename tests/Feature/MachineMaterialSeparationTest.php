<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Closing;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\Material;
use App\Models\User;
use App\Services\ClosingService;
use App\Services\SpreadsheetPasteImporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MachineMaterialSeparationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $raply;

    private Branch $epul;

    private Machine $twoHead;

    private Machine $fourHead;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->epul = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
        $this->raply = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $this->twoHead = Machine::create(['branch_id' => $this->raply->id, 'name' => 'Mesin 2 Head', 'code' => '2H', 'head_count' => 2, 'is_active' => true]);
        $this->fourHead = Machine::create(['branch_id' => $this->raply->id, 'name' => 'Mesin 4 Head', 'code' => '4H', 'head_count' => 4, 'is_active' => true]);

        $this->admin = User::factory()->create(['branch_id' => $this->raply->id]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_machine_fields_are_dynamic_per_branch(): void
    {
        $this->actingAs($this->admin)
            ->get(route('materials.create', ['branch_id' => $this->raply->id]))
            ->assertOk()->assertSee('Mesin 2 Head')->assertSee('Mesin 4 Head')->assertSee('name="machine_id"', false);

        $this->actingAs($this->admin)
            ->get(route('materials.create', ['branch_id' => $this->epul->id]))
            ->assertOk()->assertDontSee('name="machine_id"', false);
    }

    public function test_raply_requires_own_machine_and_epul_remains_nullable(): void
    {
        $base = ['name' => 'Powder', 'unit' => 'kg', 'minimum_stock' => 1, 'price' => 87000];

        $this->actingAs($this->admin)->post(route('materials.store'), $base + ['branch_id' => $this->raply->id])
            ->assertSessionHasErrors('machine_id');

        $foreign = Machine::create(['branch_id' => $this->epul->id, 'name' => 'Mesin Lain', 'code' => 'X', 'head_count' => 1, 'is_active' => true]);
        $this->actingAs($this->admin)->post(route('materials.store'), $base + ['branch_id' => $this->raply->id, 'machine_id' => $foreign->id])
            ->assertSessionHasErrors('machine_id');

        $foreign->delete();
        $this->actingAs($this->admin)->post(route('materials.store'), $base + ['branch_id' => $this->epul->id])
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('materials', ['branch_id' => $this->epul->id, 'name' => 'Powder', 'machine_id' => null]);
    }

    public function test_same_name_is_allowed_across_machines_but_not_on_same_machine(): void
    {
        $base = ['branch_id' => $this->raply->id, 'name' => 'Powder', 'unit' => 'kg', 'minimum_stock' => 0, 'price' => 1];
        $this->actingAs($this->admin)->post(route('materials.store'), $base + ['machine_id' => $this->twoHead->id])->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post(route('materials.store'), $base + ['machine_id' => $this->fourHead->id])->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post(route('materials.store'), array_merge($base, ['name' => '  POWDER  ', 'machine_id' => $this->fourHead->id]))->assertSessionHasErrors('name');
        $this->assertSame(2, Material::where('branch_id', $this->raply->id)->count());
    }

    public function test_import_targets_only_the_selected_machine_and_epul_needs_no_machine(): void
    {
        $powder2 = $this->material($this->raply, 'Powder', $this->twoHead);
        $powder4 = $this->material($this->raply, 'Powder', $this->fourHead);
        $importer = app(SpreadsheetPasteImporter::class);

        $importer->importExpenses($this->paste('POWDER (2 Head)', 10, 870000), $this->raply->id, $this->fourHead->id);
        $this->assertSame(0.0, (float) $powder2->fresh()->stock);
        $this->assertSame(10.0, (float) $powder4->fresh()->stock);

        $importer->importExpenses($this->paste(' powder ', 3, 261000), $this->raply->id, $this->twoHead->id);
        $this->assertSame(3.0, (float) $powder2->fresh()->stock);
        $this->assertSame(10.0, (float) $powder4->fresh()->stock);

        $epulPowder = $this->material($this->epul, 'Powder');
        $importer->importExpenses($this->paste('Powder', 2, 100), $this->epul->id);
        $this->assertSame(2.0, (float) $epulPowder->fresh()->stock);
    }

    public function test_import_rejects_a_machine_from_another_branch(): void
    {
        $other = Machine::create(['branch_id' => $this->epul->id, 'name' => 'Other', 'code' => 'OH', 'head_count' => 8, 'is_active' => true]);
        $this->expectException(ValidationException::class);
        app(SpreadsheetPasteImporter::class)->importExpenses($this->paste('Powder', 1, 1), $this->raply->id, $other->id);
    }

    public function test_branch_user_cannot_submit_raply_machine_for_another_branch(): void
    {
        $epulAdmin = User::factory()->create(['branch_id' => $this->epul->id]);
        $epulAdmin->assignRole('Admin EPUL');

        $this->actingAs($epulAdmin)->post(route('materials.store'), [
            'branch_id' => $this->epul->id,
            'machine_id' => $this->fourHead->id,
            'name' => 'Powder',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'price' => 1,
        ])->assertSessionHasErrors('machine_id');
    }

    public function test_imported_new_material_uses_selected_machine_and_reports_it(): void
    {
        $importer = app(SpreadsheetPasteImporter::class);
        $importer->importExpenses($this->paste('Tinta Putih', 2, 100), $this->raply->id, $this->fourHead->id);

        $this->assertDatabaseHas('materials', ['branch_id' => $this->raply->id, 'machine_id' => $this->fourHead->id, 'name' => 'Tinta Putih']);
        $this->assertSame(['Tinta Putih (4 Head)'], $importer->createdMaterialNames());
    }

    public function test_display_name_search_can_find_material_by_name_and_head_count(): void
    {
        $this->material($this->raply, 'Powder', $this->twoHead);
        $this->material($this->raply, 'Powder', $this->fourHead);

        $this->actingAs($this->admin)
            ->get(route('materials.index', ['branch_id' => $this->raply->id, 'search' => 'Powder 4 Head']))
            ->assertOk()
            ->assertSee('Powder (4 Head)')
            ->assertDontSee('Powder (2 Head)');
    }

    public function test_manual_expense_edit_and_delete_reverse_the_exact_material(): void
    {
        $powder2 = $this->material($this->raply, 'Powder', $this->twoHead);
        $powder4 = $this->material($this->raply, 'Powder', $this->fourHead);
        $payload = ['branch_id' => $this->raply->id, 'date' => '2026-09-04', 'category' => 'Bahan Baku', 'description' => 'Powder', 'amount' => 870000, 'payment_method' => 'Tunai', 'quantity' => 10];

        $this->actingAs($this->admin)->post(route('expenses.store'), $payload + ['machine_id' => $this->fourHead->id, 'material_id' => $powder4->id])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('expenses.index', ['branch_id' => $this->raply->id]));
        $expense = Expense::latest('id')->firstOrFail();
        $this->assertSame(10.0, (float) $powder4->fresh()->stock);
        $this->assertSame(0.0, (float) $powder2->fresh()->stock);

        $this->actingAs($this->admin)->put(route('expenses.update', $expense), array_merge($payload, ['quantity' => 4, 'machine_id' => $this->twoHead->id, 'material_id' => $powder2->id]))->assertSessionDoesntHaveErrors();
        $this->assertSame(0.0, (float) $powder4->fresh()->stock);
        $this->assertSame(4.0, (float) $powder2->fresh()->stock);

        $this->actingAs($this->admin)->delete(route('expenses.destroy', $expense))->assertRedirect();
        $this->assertSame(0.0, (float) $powder2->fresh()->stock);
    }

    public function test_closing_keeps_machine_materials_separate_and_sums_hpp(): void
    {
        $powder2 = $this->material($this->raply, 'Powder', $this->twoHead);
        $powder4 = $this->material($this->raply, 'Powder', $this->fourHead);
        $closing = Closing::create(['branch_id' => $this->raply->id, 'month' => 9, 'year' => 2026, 'income' => 0, 'expense' => 0, 'hpp' => 0, 'profit' => 0, 'remaining_material' => 0]);
        $closing->materials()->create(['material_id' => $powder2->id, 'stock_awal' => 0, 'qty' => 10, 'stock_akhir' => 2, 'harga_komponen' => 5, 'pembelian' => 50]);
        $closing->materials()->create(['material_id' => $powder4->id, 'stock_awal' => 0, 'qty' => 5, 'stock_akhir' => 1, 'harga_komponen' => 10, 'pembelian' => 50]);

        $service = app(ClosingService::class);
        $rows = $service->buildMaterialRows($this->raply->id, 9, 2026, $closing);
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['Powder (2 Head)', 'Powder (4 Head)'], $rows->pluck('material.display_name')->all());
        $service->calculateHpp($closing, now()->startOfMonth(), now()->endOfMonth());
        $this->assertSame(80.0, (float) $closing->fresh()->hpp);
    }

    public function test_legacy_stock_stays_unassigned_until_split_and_split_preserves_total(): void
    {
        $legacy = $this->material($this->raply, 'Tinta Putih', null, 10);
        $this->assertNull($legacy->machine_id);
        $this->assertSame(10.0, (float) $legacy->stock);

        $response = $this->actingAs($this->admin)->post(route('materials.split.store', ['material' => $legacy, 'branch_id' => $this->raply->id]), [
            'allocations' => [
                $this->twoHead->id => ['stock' => 4, 'price' => 100, 'minimum_stock' => 1],
                $this->fourHead->id => ['stock' => 6, 'price' => 200, 'minimum_stock' => 2],
            ],
        ]);
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame(10.0, (float) Material::where('branch_id', $this->raply->id)->sum('stock'));
        $this->assertSame(0.0, (float) $legacy->fresh()->stock);
        $this->assertFalse($legacy->fresh()->is_active);
        $this->assertSame(4.0, (float) Material::where('machine_id', $this->twoHead->id)->value('stock'));
        $this->assertSame(6.0, (float) Material::where('machine_id', $this->fourHead->id)->value('stock'));
    }

    private function material(Branch $branch, string $name, ?Machine $machine = null, float $stock = 0): Material
    {
        return Material::create(['branch_id' => $branch->id, 'machine_id' => $machine?->id, 'name' => $name, 'unit' => 'kg', 'stock' => $stock, 'minimum_stock' => 0, 'price' => 0, 'is_active' => true]);
    }

    private function paste(string $name, float $quantity, float $amount): string
    {
        return "04/09/2026\tBON-1\tBahan Baku\t{$name}\t{$quantity}\t0\t{$amount}\tTunai";
    }
}
