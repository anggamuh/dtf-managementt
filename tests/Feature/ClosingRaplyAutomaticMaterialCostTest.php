<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Closing;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\Material;
use App\Models\User;
use App\Services\ClosingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosingRaplyAutomaticMaterialCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_raply_purchase_details_group_equal_prices_and_fifo_carries_opening_layers(): void
    {
        $raply = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $epul = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
        $machine2 = Machine::create(['branch_id' => $raply->id, 'name' => 'Mesin 2 Head', 'code' => '2H', 'head_count' => 2, 'is_active' => true]);
        $machine4 = Machine::create(['branch_id' => $raply->id, 'name' => 'Mesin 4 Head', 'code' => '4H', 'head_count' => 4, 'is_active' => true]);

        $pet = Material::create(['branch_id' => $raply->id, 'name' => 'PET FILM', 'unit' => 'kg', 'stock' => 90, 'minimum_stock' => 0, 'price' => 0, 'is_active' => true]);
        $old2 = Material::create(['branch_id' => $raply->id, 'machine_id' => $machine2->id, 'name' => 'PET FILM (2 Head)', 'unit' => 'kg', 'stock' => 0, 'minimum_stock' => 0, 'price' => 0, 'is_active' => false]);
        $old4 = Material::create(['branch_id' => $raply->id, 'machine_id' => $machine4->id, 'name' => 'PET FILM (4 Head)', 'unit' => 'kg', 'stock' => 0, 'minimum_stock' => 0, 'price' => 0, 'is_active' => false]);

        $previous = $this->closing($raply, 7, 2026, '2026-07-01', '2026-07-31');
        $previous->materials()->create(['material_id' => $pet->id, 'stock_awal' => 0, 'stock_akhir' => 0, 'harga_komponen' => 0]);
        $august = $this->closing($raply, 8, 2026, '2026-08-01', '2026-08-31');
        $augustComponent = $august->materials()->create(['material_id' => $pet->id, 'stock_awal' => 0, 'stock_akhir' => 80]);

        $this->expense($raply, $old2, '2026-08-03', 'Bahan Baku', 60, 28800000);
        $this->expense($raply, $old4, '2026-08-04', 'Bahan Baku', 25, 12000000);
        $this->expense($raply, $old4, '2026-08-20', 'Bahan Baku', 15, 7800000);
        $this->expense($raply, $old2, '2026-09-02', 'Bahan Baku', 10, 6000000);
        $this->expense($raply, $old4, '2026-09-18', 'Bahan Baku', 10, 6000000);
        $this->expense($raply, $old2, '2026-08-15', 'Operasional', 500, 99000000);
        $this->expense($epul, null, '2026-08-10', 'Bahan Baku', 900, 99900000);

        $service = app(ClosingService::class);
        $service->populateMaterialPurchases($august, now()->parse('2026-08-01'), now()->parse('2026-08-31'));
        $service->calculateHpp($august, now()->parse('2026-08-01'), now()->parse('2026-08-31'));
        $augustRows = $service->getMaterialRows($raply->id, now()->parse('2026-08-01'), now()->parse('2026-08-31'), $august);
        $this->assertCount(1, $augustRows);
        $this->assertSame(100.0, (float) $augustRows[0]['incoming_quantity']);
        $this->assertSame(48600000.0, (float) $augustRows[0]['purchase_value']);
        $this->assertCount(2, $augustRows[0]['purchase_details']);
        $this->assertEquals(480000.0, $augustRows[0]['purchase_details'][0]['unit_price']);
        $this->assertEquals(85.0, $augustRows[0]['purchase_details'][0]['quantity']);
        $this->assertEquals(520000.0, $augustRows[0]['purchase_details'][1]['unit_price']);
        $this->assertEquals(15.0, $augustRows[0]['purchase_details'][1]['quantity']);
        $this->assertEquals(9600000.0, (float) $august->fresh()->hpp);
        $this->assertCount(2, $augustComponent->fresh()->persediaan_lapisan);
        $this->assertEquals(39000000.0, (float) $augustRows[0]['stock_value']);

        $september = $this->closing($raply, 9, 2026, '2026-09-01', '2026-09-30');
        $septemberComponent = $september->materials()->create(['material_id' => $pet->id, 'stock_awal' => 80, 'stock_akhir' => 90]);
        $service->populateMaterialPurchases($september, now()->parse('2026-09-01'), now()->parse('2026-09-30'));
        $service->calculateHpp($september, now()->parse('2026-09-01'), now()->parse('2026-09-30'));
        $septemberRows = $service->getMaterialRows($raply->id, now()->parse('2026-09-01'), now()->parse('2026-09-30'), $september);
        $this->assertSame(20.0, (float) $septemberRows[0]['incoming_quantity']);
        $this->assertSame(12000000.0, (float) $septemberRows[0]['purchase_value']);
        $this->assertCount(1, $septemberRows[0]['purchase_details']);
        $this->assertEquals(600000.0, $septemberRows[0]['purchase_details'][0]['unit_price']);
        $this->assertEquals(4800000.0, (float) $september->fresh()->hpp);
        $this->assertCount(3, $septemberComponent->fresh()->persediaan_lapisan);
        $this->assertEquals(46200000.0, (float) $septemberRows[0]['stock_value']);

        $october = $this->closing($raply, 10, 2026, '2026-10-01', '2026-10-31');
        $october->materials()->create(['material_id' => $pet->id, 'stock_awal' => 90, 'stock_akhir' => 90]);
        $octoberRows = $service->getMaterialRows($raply->id, now()->parse('2026-10-01'), now()->parse('2026-10-31'), $october);
        $this->assertCount(3, $octoberRows[0]['opening_layers']);
        $this->assertFalse($octoberRows[0]['price_warning']);
    }

    public function test_raply_closing_ui_shows_read_only_auto_price_and_no_pet_manual_form(): void
    {
        $this->seed(RoleSeeder::class);
        $raply = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $user = User::factory()->create(['branch_id' => $raply->id]);
        $user->assignRole('Super Admin');
        $material = Material::create(['branch_id' => $raply->id, 'name' => 'PET FILM', 'unit' => 'kg', 'stock' => 1, 'minimum_stock' => 0, 'price' => 0, 'is_active' => true]);
        $closing = $this->closing($raply, 8, 2026, '2026-08-01', '2026-08-31');
        $component = $closing->materials()->create(['material_id' => $material->id, 'stock_awal' => 0, 'stock_akhir' => 1]);
        $this->expense($raply, $material, '2026-08-10', 'Bahan Baku', 2, 1000);

        $this->actingAs($user)->get(route('closing.show', $closing->id))
            ->assertOk()
            ->assertSee('1 harga satuan')
            ->assertSee('Rp500')
            ->assertDontSee('Atur 4 Harga')
            ->assertDontSee('Rata-rata tertimbang otomatis')
            ->assertSee('name="harga_komponen['.$component->id.']"', false);

        $this->actingAs($user)->post(route('closing.update-stock-akhir'), [
            'branch_id' => $raply->id,
            'month' => 8,
            'year' => 2026,
            'stock_akhir' => [$component->id => 1],
            'harga_komponen' => [$component->id => 700],
        ])->assertSessionHasNoErrors();

        $this->assertSame(700.0, (float) $component->fresh()->harga_komponen);
        $this->assertSame(500.0, (float) $closing->fresh()->hpp);
    }

    public function test_epul_keeps_its_manual_component_price(): void
    {
        $epul = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
        $material = Material::create(['branch_id' => $epul->id, 'name' => 'PET Film', 'unit' => 'kg', 'stock' => 10, 'minimum_stock' => 0, 'price' => 123, 'is_active' => true]);
        $closing = $this->closing($epul, 8, 2026, '2026-08-01', '2026-08-31');
        $component = $closing->materials()->create(['material_id' => $material->id, 'stock_awal' => 10, 'stock_akhir' => 10, 'harga_komponen' => 123]);
        $this->expense($epul, $material, '2026-08-05', 'Bahan Baku', 2, 2000);

        $service = app(ClosingService::class);
        $service->populateMaterialPurchases($closing, now()->parse('2026-08-01'), now()->parse('2026-08-31'));
        $rows = $service->getMaterialRows($epul->id, now()->parse('2026-08-01'), now()->parse('2026-08-31'), $closing);

        $this->assertSame(2.0, (float) $rows[0]['incoming_quantity']);
        $this->assertSame(2000.0, (float) $rows[0]['purchase_value']);
        $this->assertSame(123.0, (float) $rows[0]['unit_cost']);
        $this->assertSame(123.0, (float) $component->fresh()->harga_komponen);
    }

    public function test_raply_warns_when_no_purchase_or_valid_fallback_price_exists(): void
    {
        $raply = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $material = Material::create(['branch_id' => $raply->id, 'name' => 'No Price', 'unit' => 'kg', 'stock' => 1, 'minimum_stock' => 0, 'price' => 999, 'is_active' => true]);
        $closing = $this->closing($raply, 8, 2026, '2026-08-01', '2026-08-31');
        $closing->materials()->create(['material_id' => $material->id, 'stock_awal' => 1, 'stock_akhir' => 1]);

        $rows = app(ClosingService::class)->getMaterialRows($raply->id, now()->parse('2026-08-01'), now()->parse('2026-08-31'), $closing);

        $this->assertTrue($rows[0]['price_warning']);
        $this->assertSame(0.0, (float) $rows[0]['unit_cost']);
    }

    public function test_raply_does_not_assign_a_known_fifo_price_to_untraced_opening_quantity(): void
    {
        $raply = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $material = Material::create(['branch_id' => $raply->id, 'name' => 'PET FILM', 'unit' => 'kg', 'stock' => 10, 'minimum_stock' => 0, 'price' => 0, 'is_active' => true]);
        $previous = $this->closing($raply, 7, 2026, '2026-07-01', '2026-07-31');
        $previous->materials()->create([
            'material_id' => $material->id,
            'stock_awal' => 5,
            'stock_akhir' => 10,
            'harga_komponen' => 0,
            'persediaan_lapisan' => [['quantity' => 5, 'unit_price' => 100]],
        ]);
        $current = $this->closing($raply, 8, 2026, '2026-08-01', '2026-08-31');
        $current->materials()->create(['material_id' => $material->id, 'stock_awal' => 10, 'stock_akhir' => 10]);

        $rows = app(ClosingService::class)->getMaterialRows($raply->id, now()->parse('2026-08-01'), now()->parse('2026-08-31'), $current);

        $this->assertTrue($rows[0]['valuation_warning']);
        $this->assertEquals([
            ['quantity' => 5.0, 'unit_price' => 100.0],
            ['quantity' => 5.0, 'unit_price' => 0.0],
        ], $rows[0]['opening_layers']);
        $this->assertSame(500.0, (float) $rows[0]['stock_value']);
    }

    private function closing(Branch $branch, int $month, int $year, string $start, string $end): Closing
    {
        return Closing::create([
            'branch_id' => $branch->id,
            'month' => $month,
            'year' => $year,
            'income' => 0,
            'expense' => 0,
            'profit' => 0,
            'hpp' => 0,
            'remaining_material' => 0,
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }

    private function expense(Branch $branch, ?Material $material, string $date, string $category, float $quantity, float $amount): void
    {
        Expense::create([
            'branch_id' => $branch->id,
            'material_id' => $material?->id,
            'date' => $date,
            'category' => $category,
            'description' => $material?->name ?? 'Material cabang lain',
            'amount' => $amount,
            'quantity' => $quantity,
            'payment_method' => 'Tunai',
        ]);
    }
}
