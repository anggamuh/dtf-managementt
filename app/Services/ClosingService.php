<?php

namespace App\Services;

use App\Models\Closing;
use App\Models\ClosingMaterial;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Material;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClosingService
{
    /**
     * Generate atau refresh closing untuk bulan tertentu.
     * Semua kalkulasi dilakukan otomatis, tanpa input manual.
     */
    public function generate(int $branchId, int $month, int $year): Closing
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        Log::info('[ClosingService::generate] START', [
            'branch_id' => $branchId,
            'month' => $month,
            'year' => $year,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

        return DB::transaction(function () use ($branchId, $month, $year, $start, $end) {
            // 1. Income = SUM(invoice.total)
            $income = $this->calculateIncome($branchId, $start, $end);

            // 2. Expense = SUM(expense.amount) EXCLUDING "Bahan Baku"
            $expense = $this->calculateExpense($branchId, $start, $end);

            // 3. Profit = Income - Expense
            $profit = $this->calculateProfit($income, $expense);

            // 4. Remaining material value = SUM(stock × price)
            $remainingMaterial = $this->calculateRemainingMaterialValue($branchId);

            // 5. Create atau update closing record
            $closing = Closing::firstOrNew([
                'branch_id' => $branchId,
                'month' => $month,
                'year' => $year,
            ]);

            $closing->fill([
                'income' => $income,
                'expense' => $expense,
                'profit' => $profit,
                'remaining_material' => $remainingMaterial,
                'hpp' => 0,
                'hpp_per_meter' => 0,
                'gaji_karyawan' => 0,
                'operasional' => 0,
                'lain_lain' => 0,
                'teknisi_mesin' => 0,
                'hasil_cetak_manual' => 0,
            ]);

            // Pertahankan nilai manual yang sudah diisi user, default ke 0
            foreach (['saldo_tahanan', 'saldo_realtime'] as $field) {
                $closing->{$field} ??= 0;
            }

            $closing->save();

            Log::info('[ClosingService::generate] Closing saved, before syncMaterialComponents', [
                'closing_id' => $closing->id,
            ]);

            // 6. Sync material components: seed baris yang belum ada
            $this->syncMaterialComponents($closing);

            Log::info('[ClosingService::generate] After syncMaterialComponents', [
                'closing_materials_count' => $closing->materials()->count(),
            ]);

            // 7. Populate material purchases from "Bahan Baku" expenses
            $this->populateMaterialPurchases($closing, $start, $end);

            Log::info('[ClosingService::generate] After populateMaterialPurchases');

            // 8. Populate HPP fixed costs from non-Bahan-Baku expenses by category
            $this->populateFixedCosts($closing, $start, $end);

            Log::info('[ClosingService::generate] After populateFixedCosts');

            // 9. Calculate HPP & HPP per meter
            $this->calculateHpp($closing, $start, $end);

            Log::info('[ClosingService::generate] DONE', [
                'income' => $income,
                'expense' => $expense,
                'profit' => $profit,
                'hpp' => $closing->fresh()->hpp,
                'hpp_per_meter' => $closing->fresh()->hpp_per_meter,
            ]);

            return $closing->fresh();
        });
    }

    /**
     * Ambil semua data yang dibutuhkan untuk view Closing (index/show).
     */
    public function getData(int $branchId, int $month, int $year, ?Closing $closing): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        // === PEMASUKAN & PENGELUARAN ===
        $invoices = $this->getInvoicesForPeriod($branchId, $start, $end);
        $expenses = $this->getExpensesForPeriod($branchId, $start, $end);

        $totalIncome = $this->calculateIncomeFromCollection($invoices);
        $totalExpense = $this->calculateExpenseFromCollection($expenses);
        $profit = $this->calculateProfit($totalIncome, $totalExpense);

        // === MATERIAL ROWS ===
        $materialRows = $this->buildMaterialRows($branchId, $month, $year, $closing);
        $materialValue = $materialRows->sum('material_cost');
        $remainingMaterial = $materialRows->sum(fn ($r) => (float) $r['stock_akhir'] * (float) $r['unit_cost']);

        // === SALDO & SELISIH ===
        $saldoData = $this->calculateSaldo($closing, $profit, $remainingMaterial);

        // === HPP PER METER ===
        $hppData = $this->buildHppRows($branchId, $start, $end, $closing, $materialRows);

        return [
            // Pemasukan & Pengeluaran
            'invoices' => $invoices,
            'invoicesByCustomer' => $invoices->groupBy(fn ($i) => $i->customer->name ?? '-'),
            'expenses' => $expenses,
            'expensesByCategory' => $expenses->groupBy('category'),
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'profit' => $profit,

            // Material
            'materialRows' => $materialRows,
            'materialValue' => $materialValue,
            'remainingMaterial' => $remainingMaterial,

            // Saldo
            'saldoTahanan' => $saldoData['saldoTahanan'],
            'saldoRealtime' => $saldoData['saldoRealtime'],
            'saldoTahananSisa' => $saldoData['saldoTahananSisa'],
            'sisaSaldo' => $saldoData['sisaSaldo'],
            'selisih' => $saldoData['selisih'],

            // HPP
            'hpp' => $hppData,
        ];
    }

    // ========================================================================
    // INCOME
    // ========================================================================

    public function calculateIncome(int $branchId, Carbon $start, Carbon $end): float
    {
        return (float) Invoice::where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->sum('total');
    }

    public function calculateIncomeFromCollection(Collection $invoices): float
    {
        return (float) $invoices->sum('total');
    }

    // ========================================================================
    // EXPENSE
    // ========================================================================

    public function calculateExpense(int $branchId, Carbon $start, Carbon $end): float
    {
        return (float) Expense::where('branch_id', $branchId)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    public function calculateExpenseFromCollection(Collection $expenses): float
    {
        return (float) $expenses->reject(fn ($e) => $e->category === 'Bahan Baku')->sum('amount');
    }

    // ========================================================================
    // PROFIT
    // ========================================================================

    public function calculateProfit(float $income, float $expense): float
    {
        return $income - $expense;
    }

    // ========================================================================
    // MATERIALS
    // ========================================================================

    public function calculateRemainingMaterialValue(int $branchId): float
    {
        return (float) Material::where('branch_id', $branchId)
            ->get()
            ->sum(fn ($m) => $m->stock * $m->price);
    }

    public function syncMaterialComponents(Closing $closing): void
    {
        $prevMonth = $closing->month === 1 ? 12 : $closing->month - 1;
        $prevYear = $closing->month === 1 ? $closing->year - 1 : $closing->year;

        $prevClosing = Closing::where('branch_id', $closing->branch_id)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();

        $existingMaterialIds = $closing->materials()->pluck('material_id')->all();

        $materials = Material::where('branch_id', $closing->branch_id)
            ->whereNotIn('id', $existingMaterialIds)
            ->get();

        foreach ($materials as $material) {
            $prevComponent = $prevClosing?->materials()
                ->where('material_id', $material->id)
                ->first();

            $stockAwal = $prevComponent->stock_akhir ?? $material->stock;

            $closing->materials()->create([
                'material_id' => $material->id,
                'stock_awal' => $stockAwal,
                'harga_komponen' => $material->price,
                'qty' => 0,
                'pembelian' => 0,
                'stock_akhir' => $stockAwal,
            ]);
        }
    }

    /**
     * Populate material purchases dari "Bahan Baku" expenses.
     *
     * - qty = SUM(expense.quantity) — nilai mentah, misal beli powder 5 → qty = 5
     * - pembelian = SUM(expense.amount)
     * - harga_komponen = weighted average price dari material saat ini
     * - stock_akhir = TIDAK diubah (biarkan user isi manual)
     */
    public function populateMaterialPurchases(Closing $closing, Carbon $start, Carbon $end): void
    {
        $purchases = Expense::where('branch_id', $closing->branch_id)
            ->where('category', 'Bahan Baku')
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('material_id')
            ->whereNotNull('quantity')
            ->get()
            ->groupBy('material_id');

        $closing->loadMissing('materials.material');

        foreach ($closing->materials as $component) {
            $materialId = $component->material_id;
            $materialPurchases = $purchases->get($materialId);

            $incomingQty = 0;
            $purchaseValue = 0;

            if ($materialPurchases) {
                $incomingQty = (float) $materialPurchases->sum('quantity');
                $purchaseValue = (float) $materialPurchases->sum('amount');
            }

            $material = $component->material;
            $currentPrice = (float) $material->price;

            // Hanya update qty, pembelian, harga_komponen
            // stock_akhir TIDAK diubah — biarkan user isi manual
            $component->update([
                'qty' => $incomingQty,
                'pembelian' => $purchaseValue,
                'harga_komponen' => $currentPrice,
            ]);
        }
    }

    /**
     * Bangun baris-baris material untuk ditampilkan di view.
     */
    public function buildMaterialRows(int $branchId, int $month, int $year, ?Closing $closing): Collection
    {
        $materials = Material::where('branch_id', $branchId)->orderBy('name')->get();

        if ($closing) {
            $closing->loadMissing('materials.material');
            $componentSource = $closing->materials;
        } else {
            $componentSource = $this->buildPreviewMaterialRows($branchId, $month, $year, $materials);
        }

        return $componentSource->map(fn ($c) => [
            'id' => $c->id,
            'material' => $c->material,
            'stock_awal' => (float) $c->stock_awal,
            'incoming_quantity' => (float) $c->qty,
            'purchase_value' => (float) $c->pembelian,
            'stock_akhir' => (float) $c->stock_akhir,
            'usage' => $c->pemakaian,
            'unit_cost' => (float) $c->harga_komponen,
            'material_cost' => $c->total_harga,

            // Legacy aliases (used in show.blade.php)
            'stockAwal' => (float) $c->stock_awal,
            'purchase' => (float) $c->qty,
            'pembelian' => (float) $c->pembelian,
            'stockAkhir' => (float) $c->stock_akhir,
            'harga' => (float) $c->harga_komponen,
            'value' => $c->total_harga,
            'stockValue' => (float) $c->stock_akhir * (float) $c->harga_komponen,
        ]);
    }

    private function buildPreviewMaterialRows(int $branchId, int $month, int $year, Collection $materials): Collection
    {
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;

        $prevClosing = Closing::where('branch_id', $branchId)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();

        return $materials->map(function ($m) use ($prevClosing) {
            $prevComponent = $prevClosing?->materials()
                ->where('material_id', $m->id)
                ->first();

            $stockAwal = $prevComponent->stock_akhir ?? $m->stock;

            return (new ClosingMaterial([
                'material_id' => $m->id,
                'stock_awal' => $stockAwal,
                'harga_komponen' => $m->price,
                'qty' => 0,
                'pembelian' => 0,
                'stock_akhir' => $m->stock,
            ]))->setRelation('material', $m);
        });
    }

    // ========================================================================
    // FIXED COSTS
    // ========================================================================

    public function populateFixedCosts(Closing $closing, Carbon $start, Carbon $end): void
    {
        $expenses = Expense::where('branch_id', $closing->branch_id)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy('category');

        $mapping = [
            'gaji_karyawan' => ['Gaji'],
            'operasional'   => ['Operasional', 'Transport', 'ATK', 'Listrik', 'Internet'],
            'lain_lain'     => ['Lainnya'],
            'teknisi_mesin' => ['Teknisi'],
        ];

        $fixedCosts = [];
        foreach ($mapping as $field => $categories) {
            $total = 0;
            foreach ($categories as $cat) {
                $group = $expenses->get($cat);
                if ($group) {
                    $total += (float) $group->sum('amount');
                }
            }
            $fixedCosts[$field] = $total;
        }

        $closing->update($fixedCosts);
    }

    public function calculateTotalFixedCost(Closing $closing): float
    {
        return (float) ($closing->gaji_karyawan ?? 0)
            + (float) ($closing->operasional ?? 0)
            + (float) ($closing->lain_lain ?? 0)
            + (float) ($closing->teknisi_mesin ?? 0);
    }

    // ========================================================================
    // HPP
    // ========================================================================

    public function calculateHpp(Closing $closing, Carbon $start, Carbon $end): void
    {
        $closing->loadMissing('materials');

        $materialCost = $closing->materials->sum(fn ($c) => $c->total_harga);

        $fixedCost = (float) ($closing->gaji_karyawan ?? 0)
            + (float) ($closing->operasional ?? 0)
            + (float) ($closing->lain_lain ?? 0)
            + (float) ($closing->teknisi_mesin ?? 0);

        $hpp = $materialCost + $fixedCost;

        $hasilCetak = $this->calculateHasilCetak($closing->branch_id, $start, $end);

        $hppPerMeter = $hasilCetak > 0 ? $hpp / $hasilCetak : 0;

        $closing->update([
            'hpp' => $hpp,
            'hpp_per_meter' => $hppPerMeter,
        ]);
    }

    public function calculateHasilCetak(int $branchId, Carbon $start, Carbon $end): float
    {
        return (float) InvoiceItem::whereHas('invoice', function ($q) use ($branchId, $start, $end) {
            $q->where('branch_id', $branchId)
              ->whereBetween('date', [$start, $end]);
        })->sum('qty');
    }

    public function buildHppRows(
        int $branchId,
        Carbon $start,
        Carbon $end,
        ?Closing $closing,
        Collection $materialRows
    ): array {
        $hasilCetak = $this->calculateHasilCetak($branchId, $start, $end);

        $hppMaterialRows = $materialRows->map(fn ($row) => [
            'nama' => $row['material']->name,
            'basis' => number_format($row['usage'], 2, ',', '.') . ' ' . $row['material']->unit,
            'totalHarga' => $row['material_cost'],
            'rpMeter' => $hasilCetak > 0 ? $row['material_cost'] / $hasilCetak : 0,
        ])->values();

        $hppFixedDefs = [
            ['key' => 'gaji_karyawan', 'nama' => 'Gaji Karyawan', 'totalHarga' => (float) ($closing?->gaji_karyawan ?? 0)],
            ['key' => 'operasional', 'nama' => 'Operasional', 'totalHarga' => (float) ($closing?->operasional ?? 0)],
            ['key' => 'lain_lain', 'nama' => 'Lain-lain', 'totalHarga' => (float) ($closing?->lain_lain ?? 0)],
            ['key' => 'teknisi_mesin', 'nama' => 'Teknisi Mesin', 'totalHarga' => (float) ($closing?->teknisi_mesin ?? 0)],
        ];

        $hppFixedRows = collect($hppFixedDefs)->map(fn ($row) => $row + [
            'basis' => 'Otomatis',
            'rpMeter' => $hasilCetak > 0 ? $row['totalHarga'] / $hasilCetak : 0,
        ]);

        $hppAllRows = $hppMaterialRows->concat($hppFixedRows)->values();
        $totalRpMeter = $hppAllRows->sum('rpMeter');

        return [
            'hasilCetak' => $hasilCetak,
            'materialRows' => $hppMaterialRows,
            'fixedRows' => $hppFixedRows,
            'allRows' => $hppAllRows,
            'totalRpMeter' => $totalRpMeter,
        ];
    }

    // ========================================================================
    // SALDO
    // ========================================================================

    public function calculateSaldo(?Closing $closing, float $profit, float $remainingMaterial): array
    {
        $saldoTahanan = (float) ($closing?->saldo_tahanan ?? 0);
        $saldoRealtime = (float) ($closing?->saldo_realtime ?? 0);
        $saldoTahananSisa = $saldoTahanan - $remainingMaterial;
        $sisaSaldo = $profit + $saldoTahananSisa;
        $selisih = $saldoRealtime - $sisaSaldo;

        return [
            'saldoTahanan' => $saldoTahanan,
            'saldoRealtime' => $saldoRealtime,
            'saldoTahananSisa' => $saldoTahananSisa,
            'sisaSaldo' => $sisaSaldo,
            'selisih' => $selisih,
        ];
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    public function getInvoicesForPeriod(int $branchId, Carbon $start, Carbon $end): Collection
    {
        return Invoice::with('customer', 'items.product')
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->get();
    }

    public function getExpensesForPeriod(int $branchId, Carbon $start, Carbon $end): Collection
    {
        return Expense::where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->get();
    }

    public function getIncomeByCustomer(int $branchId, Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return Invoice::where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->with('customer')
            ->get()
            ->groupBy(fn ($i) => $i->customer->name ?? '-')
            ->map(fn ($invoices) => $invoices->sum('total'))
            ->sortByDesc(fn ($total) => $total)
            ->take($limit);
    }

    public function getExpenseByCategory(int $branchId, Carbon $start, Carbon $end, int $limit = 10): Collection
    {
        return Expense::where('branch_id', $branchId)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy('category')
            ->map(fn ($expenses) => $expenses->sum('amount'))
            ->sortByDesc(fn ($total) => $total)
            ->take($limit);
    }
}
