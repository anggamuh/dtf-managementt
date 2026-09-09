<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Closing;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ClosingExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_export_follows_the_closing_detail_sections(): void
    {
        $this->seed(RoleSeeder::class);

        $branch = Branch::create([
            'name' => 'Cabang Test',
            'code' => 'TEST',
            'active' => true,
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('Super Admin');

        Closing::create([
            'branch_id' => $branch->id,
            'month' => 9,
            'year' => 2026,
            'income' => 1000000,
            'expense' => 250000,
            'profit' => 750000,
            'hpp' => 300000,
            'remaining_material' => 0,
            'hpp_per_meter' => 30000,
            'saldo_tahanan' => 100000,
            'saldo_realtime' => 800000,
            'gaji_karyawan' => 100000,
            'operasional' => 50000,
            'lain_lain' => 25000,
            'teknisi_mesin' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('closing.export-excel', [
            'branch_id' => $branch->id,
            'month' => 9,
            'year' => 2026,
        ]));

        $response->assertOk()->assertDownload('closing-Cabang-Test-2026-9.xlsx');

        $temporaryFile = tempnam(sys_get_temp_dir(), 'closing-export-');
        file_put_contents($temporaryFile, $response->streamedContent());

        try {
            $cells = collect(IOFactory::load($temporaryFile)->getActiveSheet()->toArray())
                ->flatten()
                ->filter()
                ->map(fn ($value) => (string) $value);

            foreach ([
                'RINGKASAN CLOSING',
                'SALDO & SELISIH',
                'STOCK OPNAME BAHAN BAKU',
                'RINCIAN MATERIAL',
                'RINCIAN HPP / METER',
                'DATA MANUAL CLOSING',
                'RINGKASAN LABA/RUGI',
            ] as $section) {
                $this->assertTrue(
                    $cells->contains(fn (string $cell) => str_contains($cell, $section)),
                    "Bagian {$section} tidak ditemukan pada export Excel."
                );
            }
        } finally {
            @unlink($temporaryFile);
        }
    }
}
