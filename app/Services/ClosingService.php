<?php

namespace App\Services;

use App\Models\Closing;
use App\Models\ClosingMaterial;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClosingService
{
    /**
     * Generate / refresh closing untuk bulan tertentu.
     */
    public function generate(
        int $branchId,
        int $month,
        int $year,
        ?Carbon $start = null,
        ?Carbon $end = null
    ): Closing {
        $start ??= Carbon::create($year, $month, 1)->startOfMonth();
        $end ??= (clone $start)->endOfMonth();

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        Log::info('[ClosingService::generate] START', [
            'branch_id' => $branchId,
            'month' => $month,
            'year' => $year,
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
        ]);

        return DB::transaction(function () use (
            $branchId,
            $month,
            $year,
            $start,
            $end
        ) {
            /*
             * ================================================================
             * 1. PEMASUKAN
             * ================================================================
             */
            $income = $this->calculateIncome(
                $branchId,
                $start,
                $end
            );

            /*
             * ================================================================
             * 2. PENGELUARAN
             * ================================================================
             */
            $expense = $this->calculateExpense(
                $branchId,
                $start,
                $end
            );

            /*
             * ================================================================
             * 3. LABA
             * ================================================================
             */
            $profit = $this->calculateProfit(
                $income,
                $expense
            );

            /*
             * ================================================================
             * 4. NILAI MATERIAL TERSISA
             * ================================================================
             */
            $remainingMaterial = $this->calculateRemainingMaterialValue(
                $branchId
            );

            /*
             * ================================================================
             * 5. CREATE / UPDATE CLOSING
             * ================================================================
             *
             * Jangan masukkan hasil_cetak_manual karena kolom tersebut
             * tidak ada di database.
             */
            $closing = Closing::firstOrNew([
                'branch_id' => $branchId,
                'month' => $month,
                'year' => $year,
            ]);

            $closing->fill([
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),

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
            ]);

            /*
             * Nilai manual tetap dipertahankan.
             */
            foreach ([
                'saldo_tahanan',
                'saldo_realtime',
            ] as $field) {
                $closing->{$field} ??= 0;
            }

            $closing->save();

            /*
             * ================================================================
             * 6. MATERIAL
             * ================================================================
             */
            $this->syncMaterialComponents($closing);

            /*
             * ================================================================
             * 7. PEMBELIAN BAHAN BAKU
             * ================================================================
             */
            $this->populateMaterialPurchases(
                $closing,
                $start,
                $end
            );

            /*
             * ================================================================
             * 8. BIAYA OPERASIONAL
             * ================================================================
             */
            $this->populateFixedCosts(
                $closing,
                $start,
                $end
            );

            /*
             * ================================================================
             * 9. HITUNG HPP
             * ================================================================
             */
            $this->calculateHpp(
                $closing,
                $start,
                $end
            );

            Log::info('[ClosingService::generate] DONE', [
                'closing_id' => $closing->id,
                'income' => $income,
                'expense' => $expense,
                'profit' => $profit,
                'hasil_cetak' => $this->calculateHasilCetak(
                    $branchId,
                    $start,
                    $end
                ),
            ]);

            return $closing->fresh();
        });
    }

    /**
     * Semua data untuk halaman Closing.
     */
    public function getData(
        int $branchId,
        int $month,
        int $year,
        ?Closing $closing,
        ?Carbon $start = null,
        ?Carbon $end = null
    ): array {
        /*
         * Kalau periode dikirim dari controller,
         * gunakan periode tersebut.
         */
        $start ??= $closing?->period_start?->copy()
            ?? Carbon::create(
                $year,
                $month,
                1
            )->startOfMonth();

        $end ??= $closing?->period_end?->copy()
            ?? (clone $start)->endOfMonth();

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        /*
         * ================================================================
         * PEMASUKAN & PENGELUARAN
         * ================================================================
         */
        $invoices = $this->getInvoicesForPeriod(
            $branchId,
            $start,
            $end
        );

        $expenses = $this->getExpensesForPeriod(
            $branchId,
            $start,
            $end
        );

        $totalIncome = $this->calculateIncomeFromCollection(
            $invoices
        );

        $totalExpense = $this->calculateExpenseFromCollection(
            $expenses
        );

        $profit = $this->calculateProfit(
            $totalIncome,
            $totalExpense
        );

        /*
         * ================================================================
         * MATERIAL
         * ================================================================
         */
        $materialRows = $this->getMaterialRows(
            $branchId,
            $start,
            $end,
            $closing
        );

        $materialValue = $materialRows->sum(
            'material_cost'
        );

        $remainingMaterial = $materialRows->sum(
            fn ($row) => (float) ($row['stock_value'] ?? ((float) $row['stock_akhir'] * (float) ($row['unit_cost'] ?? 0)))
        );

        /*
         * ================================================================
         * SALDO
         * ================================================================
         */
        $saldoData = $this->calculateSaldo(
            $closing,
            $profit,
            $remainingMaterial
        );

        /*
         * ================================================================
         * HPP
         * ================================================================
         *
         * Hasil Cetak diambil dari ORDER.QTY.
         */
        $hppData = $this->buildHppRows(
            $branchId,
            $start,
            $end,
            $closing,
            $materialRows
        );

        return [
            'invoices' => $invoices,

            'invoicesByCustomer' => $invoices->groupBy(
                fn ($invoice) => $invoice->customer->name ?? '-'
            ),

            'expenses' => $expenses,

            'expensesByCategory' => $expenses->groupBy(
                'category'
            ),

            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'profit' => $profit,

            'materialRows' => $materialRows,
            'materialValue' => $materialValue,
            'remainingMaterial' => $remainingMaterial,

            'saldoTahanan' => $saldoData['saldoTahanan'],
            'saldoRealtime' => $saldoData['saldoRealtime'],
            'saldoTahananSisa' => $saldoData['saldoTahananSisa'],
            'sisaSaldo' => $saldoData['sisaSaldo'],
            'selisih' => $saldoData['selisih'],

            'hpp' => $hppData,
        ];
    }

    // ========================================================================
    // INCOME
    // ========================================================================

    /**
     * Total pemasukan dari invoice yang PERIODE-nya beririsan
     * dengan rentang closing ($start - $end).
     *
     * PENTING:
     * Closing pakai rentang tanggal custom (period_start/period_end),
     * BUKAN kolom Invoice.date (yang cuma tanggal invoice dibuat/dicetak).
     *
     * Invoice dianggap masuk closing kalau period invoice tsb
     * overlap dengan period closing:
     *
     *   invoice.period_start <= closing.end
     *   AND invoice.period_end >= closing.start
     */
    public function calculateIncome(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): float {
        return (float) Invoice::query()
            ->where('branch_id', $branchId)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->sum('total');
    }

    public function calculateIncomeFromCollection(
        Collection $invoices
    ): float {
        return (float) $invoices->sum('total');
    }

    // ========================================================================
    // EXPENSE
    // ========================================================================

    public function calculateExpense(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): float {
        return (float) Expense::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->sum('amount');
    }

    public function calculateExpenseFromCollection(
        Collection $expenses
    ): float {
        return (float) $expenses->sum('amount');
    }

    // ========================================================================
    // PROFIT
    // ========================================================================

    public function calculateProfit(
        float $income,
        float $expense
    ): float {
        return $income - $expense;
    }

    // ========================================================================
    // MATERIAL
    // ========================================================================

    public function calculateRemainingMaterialValue(
        int $branchId
    ): float {
        return (float) Material::query()
            ->where('branch_id', $branchId)
            ->get()
            ->sum(
                fn ($material) => (float) $material->stock
                    * (float) $material->price
            );
    }

    /**
     * Sinkronisasi stock awal bulan berjalan dari stock akhir
     * closing bulan sebelumnya.
     *
     * Contoh:
     * Closing Juli:
     *   PET = 2,30
     *
     * Closing Agustus:
     *   Stock Awal PET = 2,30
     *
     * HANYA stock_awal yang disinkronkan untuk material
     * yang sudah ada di closing.
     *
     * qty, pembelian, harga_komponen, dan stock_akhir
     * TIDAK disentuh.
     */
    public function syncMaterialComponents(
        Closing $closing
    ): void {
        /*
         * Ambil closing TERAKHIR sebelum periode closing sekarang
         * dari branch yang sama.
         *
         * Jangan lagi bergantung pada month/year - ini aman untuk
         * closing dengan rentang tanggal custom.
         *
         * Contoh:
         * Closing sebelumnya : 01/07 - 31/07
         * Closing sekarang   : 10/08 - 05/09
         *
         * Maka stock_awal = stock_akhir closing 01/07 - 31/07.
         */
        $currentStart = $closing->period_start
            ? Carbon::parse($closing->period_start)->startOfDay()
            : Carbon::create($closing->year, $closing->month, 1)->startOfDay();

        $prevClosing = Closing::query()
            ->where('branch_id', $closing->branch_id)
            ->whereNotNull('period_end')
            ->where('period_end', '<', $currentStart->toDateString())
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first();

        /*
         * Kalau belum ada closing bulan sebelumnya,
         * pertahankan perilaku lama:
         * hanya buat material yang belum ada.
         */
        if (! $prevClosing) {
            $existingMaterialIds = $closing
                ->materials()
                ->pluck('material_id')
                ->all();

            $materials = Material::query()
                ->with('machine')
                ->where('branch_id', $closing->branch_id)
                ->where('is_active', true)
                ->whereNotIn('id', $existingMaterialIds)
                ->get();

            foreach ($materials as $material) {
                /*
                 * Closing pertama tidak punya saldo closing sebelumnya.
                 * Untuk kondisi ini gunakan stock master saat closing
                 * dibuat sebagai baseline awal.
                 *
                 * Setelah closing pertama tersimpan, closing berikutnya
                 * TIDAK akan mengambil stock master lagi; ia mengambil
                 * stock_akhir dari closing sebelumnya.
                 */
                $baselineStock = (float) $material->stock;

                $closing->materials()->create([
                    'material_id' => $material->id,
                    'stock_awal' => $baselineStock,
                    'harga_komponen' => 0,
                    'qty' => 0,
                    'pembelian' => 0,
                    'stock_akhir' => $baselineStock,
                ]);
            }

            return;
        }

        /*
         * ================================================================
         * MATERIAL YANG SUDAH ADA DI CLOSING SEKARANG
         * ================================================================
         *
         * Ini bagian utama perbaikannya:
         *
         * stock_awal bulan sekarang
         * =
         * stock_akhir bulan sebelumnya
         *
         * Hanya field stock_awal yang di-update.
         */
        $currentComponents = $closing
            ->materials()
            ->get();

        foreach ($currentComponents as $component) {
            $prevComponent = $prevClosing
                ->materials()
                ->where('material_id', $component->material_id)
                ->first();

            if ($prevComponent) {
                $component->update([
                    'stock_awal' => (float) $prevComponent->stock_akhir,
                ]);
            }
        }

        /*
         * ================================================================
         * MATERIAL BARU
         * ================================================================
         *
         * Kalau ada material baru yang belum ada di closing sekarang,
         * buat seperti perilaku lama.
         */
        $existingMaterialIds = $currentComponents
            ->pluck('material_id')
            ->all();

        $materials = Material::query()
            ->with('machine')
            ->where('branch_id', $closing->branch_id)
            ->where('is_active', true)
            ->whereNotIn('id', $existingMaterialIds)
            ->get();

        foreach ($materials as $material) {
            $prevComponent = $prevClosing
                ->materials()
                ->where('material_id', $material->id)
                ->first();

            /*
             * Material baru:
             * - kalau sudah ada di closing sebelumnya -> carry forward stock_akhir.
             * - kalau benar-benar baru -> mulai dari 0.
             *
             * Jangan memakai Material.stock untuk material baru di sini,
             * karena Material.stock bisa sudah termasuk pembelian periode
             * berjalan dan akan membuat stok awal terlihat dobel.
             */
            $stockAwal = $prevComponent
                ? (float) $prevComponent->stock_akhir
                : 0.0;

            $closing->materials()->create([
                'material_id' => $material->id,
                'stock_awal' => $stockAwal,
                'harga_komponen' => 0,
                'qty' => 0,
                'pembelian' => 0,
                'stock_akhir' => $stockAwal,
            ]);
        }
    }

    /**
     * Terapkan stock_akhir closing ini ke stock_awal closing BERIKUTNYA
     * (cabang yang sama), lalu hitung ulang HPP closing berikutnya.
     *
     * Dipakai setelah stock akhir diedit, supaya tidak perlu generate ulang.
     * Closing berikutnya yang sudah terkunci TIDAK diubah.
     *
     * @return string|null pesan peringatan bila closing berikutnya terkunci
     */
    public function propagateStockAwalToNext(Closing $closing): ?string
    {
        if (! $closing->period_end) {
            return null;
        }

        $next = Closing::query()
            ->where('branch_id', $closing->branch_id)
            ->whereNotNull('period_start')
            ->where('period_start', '>', Carbon::parse($closing->period_end)->toDateString())
            ->orderBy('period_start')
            ->orderBy('id')
            ->first();

        if (! $next) {
            return null;
        }

        if ($next->is_locked) {
            return 'Closing berikutnya sudah terkunci, jadi Stock Awal-nya tidak ikut berubah. Buka kuncinya dulu bila perlu.';
        }

        foreach ($closing->materials()->get() as $component) {
            $next->materials()
                ->where('material_id', $component->material_id)
                ->update(['stock_awal' => (float) $component->stock_akhir]);
        }

        $this->calculateHpp(
            $next,
            Carbon::parse($next->period_start)->startOfDay(),
            Carbon::parse($next->period_end)->endOfDay()
        );

        return null;
    }

    /**
     * Pembelian Bahan Baku.
     *
     * qty:
     *     SUM(expense.quantity)
     *
     * pembelian:
     *     SUM(expense.amount)
     *
     * Harga RAPLY tetap mengikuti harga transaksi; lapisan stok ditetapkan
     * ketika HPP dihitung.
     *
     * stock_akhir:
     *     TIDAK diubah otomatis.
     */
    public function populateMaterialPurchases(
        Closing $closing,
        Carbon $start,
        Carbon $end
    ): void {
        $purchases = $this->materialPurchasesForPeriod(
            (int) $closing->branch_id,
            $start,
            $end
        );
        $closing->loadMissing(
            'materials.material.machine'
        );

        foreach ($closing->materials as $component) {
            $materialId = $component->material_id;

            $materialPurchases = $purchases->get($materialId, collect());

            $incomingQty = 0;
            $purchaseValue = 0;

            $incomingQty = (float) $materialPurchases->sum('quantity');
            $purchaseValue = (float) $materialPurchases->sum('amount');

            $updates = [
                'qty' => $incomingQty,
                'pembelian' => $purchaseValue,
            ];

            $component->update($updates);
        }
    }

    /**
     * Bangun baris material.
     *
     * Rumus:
     *
     * Pemakaian =
     *     Stock Awal + Pembelian Qty - Stock Akhir
     *
     * Total Harga =
     *     Pemakaian × Harga Material
     */
    public function getMaterialRows(
        int $branchId,
        Carbon $start,
        Carbon $end,
        ?Closing $closing
    ): Collection {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $materials = Material::query()
            ->with(['machine', 'movements'])
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN machine_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('machine_id')
            ->orderBy('name')
            ->get();
        $purchases = $this->materialPurchasesForPeriod(
            $branchId,
            $start,
            $end
        );

        if ($closing && $this->closingMatchesPeriod($closing, $start, $end)) {
            $closing->loadMissing(
                'materials.material.machine'
            );

            $componentSource = $closing->materials;
        } else {
            $componentSource = $this->buildPreviewMaterialRows(
                $start,
                $end,
                $materials,
                $purchases
            );
        }

        $isRaply = $this->isRaplyBranch($branchId);
        $priceContext = $closing ?? new Closing([
            'branch_id' => $branchId,
            'month' => $start->month,
            'year' => $start->year,
            'period_start' => $start,
            'period_end' => $end,
        ]);

        return $componentSource->map(
            function ($component) use ($purchases, $isRaply, $priceContext) {
                $stockAwal = (float) $component->stock_awal;
                $materialPurchases = $purchases->get(
                    $component->material_id,
                    collect()
                );
                $pembelianQty = (float) $materialPurchases->sum('quantity');
                $purchaseValue = (float) $materialPurchases->sum('amount');
                $stockAkhir = (float) $component->stock_akhir;
                $usage = max(0, $stockAwal + $pembelianQty - $stockAkhir);
                $material = $component->material;
                $priceWarning = false;
                $purchaseDetails = collect();
                $inventoryLayers = [];
                $inventoryUnitCost = (float) $component->harga_komponen;
                $materialCost = 0.0;
                $soUnitCost = (float) $component->harga_komponen;
                $stockValue = $stockAkhir * $soUnitCost;
                if ($isRaply && $material) {
                    $purchaseDetails = $this->groupPurchasesByPrice($materialPurchases);
                    $openingLayers = $this->previousClosingMaterialLayers($priceContext, $material, $stockAwal);
                    $fifo = $this->calculateFifoCost($openingLayers, $materialPurchases, $usage, $stockAwal);
                    $inventoryLayers = $fifo['ending_layers'];
                    $materialCost = $fifo['consumed_value'];
                    $stockValue = $stockAkhir * $soUnitCost;
                    $valuationWarning = $fifo['unpriced_opening'];
                    $priceWarning = $pembelianQty <= 0
                        && ! collect($openingLayers)->contains(fn ($layer) => (float) ($layer['unit_price'] ?? 0) > 0);
                    $harga = $purchaseDetails->count() === 1
                        ? (float) $purchaseDetails->first()['unit_price']
                        : null;
                    $inventoryUnitCost = null;
                } else {
                    $harga = (float) $component->harga_komponen;
                    $inventoryUnitCost = $harga;
                    $materialCost = $usage * $inventoryUnitCost;
                    $stockValue = $stockAkhir * $inventoryUnitCost;
                    $valuationWarning = false;
                }

                /*
                 * RUMUS UTAMA
                 *
                 * Stok Awal
                 * + Pembelian
                 * - Stok Akhir
                 */
                return [
                    'id' => $component->id,

                    'material' => $component->material,

                    'stock_awal' => $stockAwal,

                    'incoming_quantity' => $pembelianQty,

                    'purchase_value' => $purchaseValue,

                    'stock_akhir' => $stockAkhir,

                    'usage' => $usage,

                    'unit_cost' => $harga,

                    'so_unit_cost' => $soUnitCost,

                    'inventory_unit_cost' => $inventoryUnitCost,

                    'purchase_details' => $purchaseDetails,

                    'opening_layers' => $isRaply ? $openingLayers : [],

                    'inventory_layers' => $inventoryLayers,

                    'stock_value' => $stockValue,

                    'price_warning' => $priceWarning,

                    'valuation_warning' => $valuationWarning,

                    'material_cost' => $materialCost,

                    // Legacy aliases
                    'stockAwal' => $stockAwal,

                    'purchase' => $pembelianQty,

                    'pembelian' => $purchaseValue,

                    'stockAkhir' => $stockAkhir,

                    'harga' => $harga,

                    'value' => $materialCost,

                    'stockValue' => $stockValue,
                ];
            }
        )->sortBy(function ($row) {
            $material = $row['material'];
            $machineOrder = $material->machine_id === null
                ? '1'
                : '0'
                    .str_pad((string) ($material->machine?->head_count ?? PHP_INT_MAX), 6, '0', STR_PAD_LEFT)
                    .'|'.mb_strtolower($material->machine?->name ?? '')
                    .'|'.str_pad((string) $material->machine_id, 12, '0', STR_PAD_LEFT);

            return $machineOrder.'|'.mb_strtolower($material->name);
        })->values();
    }

    /**
     * Kompatibilitas untuk pemanggil lama berbasis bulan/tahun.
     */
    public function buildMaterialRows(
        int $branchId,
        int $month,
        int $year,
        ?Closing $closing
    ): Collection {
        $start = $closing?->period_start?->copy()
            ?? Carbon::create($year, $month, 1)->startOfMonth();
        $end = $closing?->period_end?->copy()
            ?? (clone $start)->endOfMonth();

        return $this->getMaterialRows(
            $branchId,
            $start,
            $end,
            $closing
        );
    }

    /**
     * Preview sebelum closing dibuat.
     */
    private function buildPreviewMaterialRows(
        Carbon $start,
        Carbon $end,
        Collection $materials,
        Collection $purchases
    ): Collection {
        return $materials->map(
            function ($material) use ($purchases, $start, $end) {
                $materialPurchases = $purchases->get($material->id, collect());
                $incomingQuantity = (float) $materialPurchases->sum('quantity');
                $purchaseValue = (float) $materialPurchases->sum('amount');

                /*
                 * materials.stock adalah saldo saat ini. Untuk mendapatkan
                 * saldo pada batas periode tanpa menulis database, balikkan
                 * semua movement yang terjadi setelah batas tersebut.
                 */
                $stockAwal = (float) $material->stock
                    - (float) $material->movements
                        ->filter(fn ($movement) => $movement->date->gte($start))
                        ->sum('quantity');
                $stockAkhir = (float) $material->stock
                    - (float) $material->movements
                        ->filter(fn ($movement) => $movement->date->gt($end))
                        ->sum('quantity');

                return (new ClosingMaterial([
                    'material_id' => $material->id,

                    'stock_awal' => (float) $stockAwal,

                    // Harga komponen hanya tersedia setelah closing dibuat.
                    'harga_komponen' => 0,

                    'qty' => $incomingQuantity,

                    'pembelian' => $purchaseValue,

                    'stock_akhir' => $stockAkhir,
                ]))->setRelation(
                    'material',
                    $material
                );
            }
        );
    }

    private function materialPurchasesForPeriod(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): Collection {
        $query = Expense::query()
            ->where('branch_id', $branchId)
            ->where('category', 'Bahan Baku')
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->orderBy('date')
            ->orderBy('id');
        $query->whereNotNull('quantity');

        if (! $this->isRaplyBranch($branchId)) {
            $query->whereNotNull('material_id');
            return $query->get()->groupBy('material_id');
        }

        // Legacy RAPLY purchases can still reference separate 2/4 Head rows.
        // Match normalized names to the single active shared material without
        // changing any historical Expense or its original material_id.
        $canonicalMaterials = Material::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with('machine')
            ->get()
            ->sortBy(fn ($material) => $material->machine_id === null ? 0 : 1)
            ->groupBy(fn ($material) => $this->normalizeMaterialName($material->name))
            ->map(fn ($group) => $group->first());

        $grouped = collect();
        foreach ($query->with('material')->get() as $expense) {
            $normalizedName = $this->normalizeMaterialName($expense->material?->name ?? $expense->description);
            $canonical = $canonicalMaterials->get($normalizedName);
            if ($canonical) {
                $grouped->put(
                    $canonical->id,
                    $grouped->get($canonical->id, collect())->push($expense)
                );
            }
        }

        return $grouped;
    }

    private function isRaplyBranch(int $branchId): bool
    {
        return Branch::query()->whereKey($branchId)->where('code', 'RAPLY')->exists();
    }

    private function normalizeMaterialName(?string $name): string
    {
        $name = preg_replace('/\s*\(\s*[24]\s*head\s*\)\s*$/iu', '', trim((string) $name));
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim($name)));
    }

    private function groupPurchasesByPrice(Collection $purchases): Collection
    {
        return $purchases
            ->filter(fn ($expense) => (float) $expense->quantity > 0)
            ->groupBy(fn ($expense) => number_format((float) $expense->amount / (float) $expense->quantity, 2, '.', ''))
            ->map(fn ($group, $price) => [
                'unit_price' => (float) $price,
                'quantity' => (float) $group->sum('quantity'),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortBy('unit_price')
            ->values();
    }

    private function previousClosingMaterialRecord(Closing $closing, Material $material): ?ClosingMaterial
    {
        $periodStart = $closing->period_start
            ? Carbon::parse($closing->period_start)->toDateString()
            : Carbon::create((int) $closing->year, (int) $closing->month, 1)->startOfMonth()->toDateString();

        $previousClosings = Closing::query()
            ->where('branch_id', $closing->branch_id)
            ->when($closing->exists, fn ($query) => $query->where('id', '!=', $closing->id))
            ->where(function ($query) use ($periodStart, $closing) {
                $query->where('period_end', '<', $periodStart)
                    ->orWhere(function ($legacy) use ($closing) {
                        $legacy->whereNull('period_end')
                            ->where(function ($date) use ($closing) {
                                $date->where('year', '<', $closing->year)
                                    ->orWhere(fn ($sameYear) => $sameYear->where('year', $closing->year)->where('month', '<', $closing->month));
                            });
                    });
            })
            ->with('materials.material')
            ->orderByDesc('period_end')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->get();

        $normalized = $this->normalizeMaterialName($material->name);
        foreach ($previousClosings as $previousClosing) {
            $match = $previousClosing->materials->first(function ($component) use ($material, $normalized) {
                return (int) $component->material_id === (int) $material->id
                    || $this->normalizeMaterialName($component->material?->name) === $normalized;
            });
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function previousClosingMaterialLayers(Closing $closing, Material $material, float $openingQty): array
    {
        if ($openingQty <= 0) {
            return [];
        }

        $record = $this->previousClosingMaterialRecord($closing, $material);
        $layers = collect($record?->persediaan_lapisan ?? [])
            ->map(fn ($layer) => [
                'quantity' => max(0, (float) ($layer['quantity'] ?? 0)),
                'unit_price' => max(0, (float) ($layer['unit_price'] ?? 0)),
            ])
            ->filter(fn ($layer) => $layer['quantity'] > 0)
            ->values()
            ->all();

        if ($layers === []) {
            $legacyPrice = (float) ($record?->harga_persediaan ?? $record?->harga_komponen ?? 0);
            return $legacyPrice > 0 ? [['quantity' => $openingQty, 'unit_price' => $legacyPrice]] : [];
        }

        $layerQty = array_sum(array_column($layers, 'quantity'));
        if ($layerQty > $openingQty) {
            $excess = $layerQty - $openingQty;
            for ($index = count($layers) - 1; $index >= 0 && $excess > 0; $index--) {
                $removed = min($excess, $layers[$index]['quantity']);
                $layers[$index]['quantity'] -= $removed;
                $excess -= $removed;
            }
        } elseif ($layerQty < $openingQty) {
            $lastLayer = $layers[array_key_last($layers)];
            $layers[] = ['quantity' => $openingQty - $layerQty, 'unit_price' => $lastLayer['unit_price']];
        }

        return array_values(array_filter($layers, fn ($layer) => $layer['quantity'] > 0));
    }

    private function calculateFifoCost(array $openingLayers, Collection $purchases, float $usage, float $openingQty): array
    {
        $layers = $openingLayers;
        $openingLayerQty = array_sum(array_column($layers, 'quantity'));
        $pricedOpeningQty = collect($layers)
            ->filter(fn ($layer) => (float) ($layer['unit_price'] ?? 0) > 0)
            ->sum('quantity');
        $unpricedOpening = $openingQty > $pricedOpeningQty;
        if ($openingLayerQty < $openingQty && $openingLayerQty === 0) {
            // Unknown legacy opening stock remains visible as unvalued stock.
            $layers[] = ['quantity' => $openingQty, 'unit_price' => 0.0];
        }

        foreach ($purchases as $expense) {
            $quantity = (float) $expense->quantity;
            if ($quantity <= 0) {
                continue;
            }
            $layers[] = [
                'quantity' => $quantity,
                'unit_price' => (float) $expense->amount / $quantity,
                'expense_id' => (int) $expense->id,
                'date' => Carbon::parse($expense->date)->toDateString(),
            ];
        }

        $remainingToConsume = max(0, $usage);
        $consumedValue = 0.0;
        foreach ($layers as $index => $layer) {
            if ($remainingToConsume <= 0) {
                break;
            }
            $consumed = min($remainingToConsume, (float) $layer['quantity']);
            $consumedValue += $consumed * (float) $layer['unit_price'];
            $layers[$index]['quantity'] -= $consumed;
            $remainingToConsume -= $consumed;
        }

        $endingLayers = [];
        foreach ($layers as $layer) {
            if ((float) $layer['quantity'] <= 0) {
                continue;
            }
            $lastIndex = array_key_last($endingLayers);
            if ($lastIndex !== null && round((float) $endingLayers[$lastIndex]['unit_price'], 2) === round((float) $layer['unit_price'], 2)) {
                $endingLayers[$lastIndex]['quantity'] += (float) $layer['quantity'];
            } else {
                $endingLayers[] = [
                    'quantity' => (float) $layer['quantity'],
                    'unit_price' => (float) $layer['unit_price'],
                ];
            }
        }
        $endingValue = collect($endingLayers)->sum(fn ($layer) => (float) $layer['quantity'] * (float) $layer['unit_price']);

        return [
            'consumed_value' => $consumedValue,
            'ending_layers' => $endingLayers,
            'ending_value' => (float) $endingValue,
            'unpriced_opening' => $unpricedOpening,
        ];
    }

    private function closingMatchesPeriod(
        Closing $closing,
        Carbon $start,
        Carbon $end
    ): bool {
        if ($closing->period_start && $closing->period_end) {
            return $closing->period_start->isSameDay($start)
                && $closing->period_end->isSameDay($end);
        }

        return (int) $closing->month === $start->month
            && (int) $closing->year === $start->year
            && $start->isStartOfMonth()
            && $end->isSameDay($start->copy()->endOfMonth());
    }

    // ========================================================================
    // FIXED COSTS
    // ========================================================================

    public function populateFixedCosts(
        Closing $closing,
        Carbon $start,
        Carbon $end
    ): void {
        $expenses = Expense::query()
            ->where('branch_id', $closing->branch_id)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->get()
            ->groupBy('category');

        $mapping = [
            'gaji_karyawan' => [
                'Gaji',
            ],

            'operasional' => [
                'Operasional',
                'Transport',
                'ATK',
                'Listrik',
                'Internet',
            ],

            'lain_lain' => [
                'Lainnya',
            ],

            'teknisi_mesin' => [
                'Teknisi',
            ],
        ];

        $fixedCosts = [];

        foreach ($mapping as $field => $categories) {
            $total = 0;

            foreach ($categories as $category) {
                $group = $expenses->get($category);

                if ($group) {
                    $total += (float) $group->sum(
                        'amount'
                    );
                }
            }

            $fixedCosts[$field] = $total;
        }

        $closing->update(
            $fixedCosts
        );
    }

    public function calculateTotalFixedCost(
        Closing $closing
    ): float {
        return
            (float) ($closing->gaji_karyawan ?? 0)
            + (float) ($closing->operasional ?? 0)
            + (float) ($closing->lain_lain ?? 0)
            + (float) ($closing->teknisi_mesin ?? 0);
    }

    // ========================================================================
    // HPP
    // ========================================================================

    /**
     * Hitung HPP.
     *
     * Hasil Cetak diambil dari:
     *
     * orders.qty
     *
     * BUKAN invoice_items.qty.
     */
    public function calculateHpp(
        Closing $closing,
        Carbon $start,
        Carbon $end
    ): void {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        /*
         * SINGLE SOURCE OF TRUTH:
         * materialRows yang dipakai untuk menyimpan snapshot HPP harus sama
         * dengan materialRows yang dipakai pada tabel Rincian HPP / Meter.
         * Dengan begitu qty pembelian selalu berasal dari Expense Bahan Baku
         * pada periode closing, bukan dari rumus lain yang terpisah.
         */
        $materialRows = $this->getMaterialRows(
            (int) $closing->branch_id,
            $start,
            $end,
            $closing
        );

        if ($this->isRaplyBranch((int) $closing->branch_id)) {
            foreach ($materialRows as $row) {
                if (! empty($row['id'])) {
                    ClosingMaterial::query()->whereKey($row['id'])->update([
                        'qty' => $row['incoming_quantity'],
                        'pembelian' => $row['purchase_value'],
                        'persediaan_lapisan' => $row['inventory_layers'],
                        'hpp_pemakaian' => $row['material_cost'],
                    ]);
                }
            }
        }

        $hppData = $this->buildHppRows(
            (int) $closing->branch_id,
            $start,
            $end,
            $closing,
            $materialRows
        );

        /*
         * Nilai Stock Opname mengikuti nilai lapisan persediaan yang tersisa.
         */
        $remainingMaterial = (float) $materialRows->sum(
            fn ($row) => (float) ($row['stock_value'] ?? 0)
        );

        $closing->update([
            'hpp' => (float) ($hppData['totalHpp'] ?? 0),
            'hpp_per_meter' => (float) ($hppData['totalRpMeter'] ?? 0),
            'remaining_material' => $remainingMaterial,
        ]);
    }

    /**
     * Ambil HPP sebuah closing memakai periode dan branch milik closing itu
     * sendiri. Method ini dipakai oleh index/show/export agar EPUL dan RAFLI
     * selalu memakai rumus yang identik.
     */
    public function getHppForClosing(Closing $closing): array
    {
        $start = $closing->period_start
            ? Carbon::parse($closing->period_start)->startOfDay()
            : Carbon::create(
                (int) $closing->year,
                (int) $closing->month,
                1
            )->startOfMonth()->startOfDay();

        $end = $closing->period_end
            ? Carbon::parse($closing->period_end)->endOfDay()
            : (clone $start)->endOfMonth()->endOfDay();

        $materialRows = $this->getMaterialRows(
            (int) $closing->branch_id,
            $start,
            $end,
            $closing
        );

        return $this->buildHppRows(
            (int) $closing->branch_id,
            $start,
            $end,
            $closing,
            $materialRows
        );
    }

    // ========================================================================
    // HASIL CETAK
    // ========================================================================

    /**
     * Total hasil cetak.
     *
     * SUM orders.qty
     *
     * Ini sengaja dibuat sama dengan Dashboard:
     *
     * Order::where('branch_id', $branchId)
     *      ->whereBetween('date', [$start, $end])
     *      ->sum('qty');
     *
     * Jadi angka Hasil Cetak di Dashboard dan Closing
     * berasal dari sumber data yang sama.
     */
    public function calculateHasilCetak(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): float {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        return (float) Order::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $start,
                $end,
            ])
            ->sum('qty');
    }

    /**
     * Data HPP / Meter.
     */
    public function buildHppRows(
        int $branchId,
        Carbon $start,
        Carbon $end,
        ?Closing $closing,
        Collection $materialRows
    ): array {
        /*
         * Hasil Cetak dari ORDER.
         */
        $hasilCetak = $this->calculateHasilCetak(
            $branchId,
            $start,
            $end
        );

        /*
         * ================================================================
         * MATERIAL
         * ================================================================
         */
        $hppMaterialRows = $materialRows
            ->map(
                function ($row) use ($hasilCetak) {
                    $totalHarga = (float) (
                        $row['material_cost'] ?? 0
                    );

                    return [
                        'nama' => $row['material']->display_name,

                        'basis' => number_format(
                            (float) $row['usage'],
                            2,
                            ',',
                            '.'
                        )
                            .' '
                            .$row['material']->unit,

                        'totalHarga' => $totalHarga,

                        'rpMeter' => $hasilCetak > 0
                                ? $totalHarga / $hasilCetak
                                : 0,
                    ];
                }
            )
            ->values();

        /*
         * ================================================================
         * BIAYA TETAP
         * ================================================================
         */
        $hppFixedDefs = [
            [
                'key' => 'gaji_karyawan',
                'nama' => 'Gaji Karyawan',
                'totalHarga' => (float) (
                    $closing?->gaji_karyawan ?? 0
                ),
            ],

            [
                'key' => 'operasional',
                'nama' => 'Operasional',
                'totalHarga' => (float) (
                    $closing?->operasional ?? 0
                ),
            ],

            [
                'key' => 'lain_lain',
                'nama' => 'Lain-lain',
                'totalHarga' => (float) (
                    $closing?->lain_lain ?? 0
                ),
            ],

            [
                'key' => 'teknisi_mesin',
                'nama' => 'Teknisi Mesin',
                'totalHarga' => (float) (
                    $closing?->teknisi_mesin ?? 0
                ),
            ],
        ];

        $hppFixedRows = collect(
            $hppFixedDefs
        )->map(
            function ($row) use ($hasilCetak) {
                return $row + [
                    'basis' => 'Otomatis',

                    'rpMeter' => $hasilCetak > 0
                            ? $row['totalHarga']
                                / $hasilCetak
                            : 0,
                ];
            }
        );

        /*
         * ================================================================
         * GABUNG MATERIAL + BIAYA TETAP
         * ================================================================
         */
        $hppAllRows = $hppMaterialRows
            ->concat($hppFixedRows)
            ->values();

        /*
         * Total HPP dan HPP/Meter berasal dari kumpulan baris yang sama
         * dengan tabel Rincian HPP / Meter. Jangan menjumlahkan hasil yang
         * sudah dibagi per baris sebagai sumber utama; hitung sekali dari
         * total HPP agar tidak ada selisih pembulatan/floating point.
         */
        $totalHpp = (float) $hppAllRows->sum(
            'totalHarga'
        );

        $totalRpMeter = $hasilCetak > 0
            ? $totalHpp / $hasilCetak
            : 0;

        return [
            /*
             * INI YANG DITAMPILKAN SEBAGAI HASIL CETAK.
             *
             * Sumber:
             * orders.qty
             */
            'hasilCetak' => $hasilCetak,

            'materialRows' => $hppMaterialRows,

            'fixedRows' => $hppFixedRows,

            'allRows' => $hppAllRows,

            'totalHpp' => $totalHpp,

            'totalRpMeter' => $totalRpMeter,
        ];
    }

    // ========================================================================
    // SALDO
    // ========================================================================

    public function calculateSaldo(
        ?Closing $closing,
        float $profit,
        float $remainingMaterial
    ): array {
        $saldoTahanan = (float) (
            $closing?->saldo_tahanan ?? 0
        );

        $saldoRealtime = (float) (
            $closing?->saldo_realtime ?? 0
        );

        $saldoTahananSisa =
            $saldoTahanan - $remainingMaterial;

        $sisaSaldo =
            $profit + $saldoTahananSisa;

        $selisih =
            $saldoRealtime - $sisaSaldo;

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

    /**
     * Invoice untuk periode closing ini.
     *
     * Sama seperti calculateIncome(), pakai overlap period_start/period_end
     * milik invoice terhadap rentang closing — BUKAN Invoice.date.
     */
    public function getInvoicesForPeriod(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): Collection {
        return Invoice::with(
            'customer',
            'items.product'
        )
            ->where('branch_id', $branchId)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->get();
    }

    public function getExpensesForPeriod(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): Collection {
        return Expense::with('material.machine')
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->get();
    }

    public function getIncomeByCustomer(
        int $branchId,
        Carbon $start,
        Carbon $end,
        int $limit = 10
    ): Collection {
        return Invoice::query()
            ->where('branch_id', $branchId)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->with('customer')
            ->get()
            ->groupBy(
                fn ($invoice) => $invoice->customer->name ?? '-'
            )
            ->map(
                fn ($invoices) => $invoices->sum('total')
            )
            ->sortByDesc(
                fn ($total) => $total
            )
            ->take($limit);
    }

    public function getExpenseByCategory(
        int $branchId,
        Carbon $start,
        Carbon $end,
        int $limit = 10
    ): Collection {
        return Expense::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->get()
            ->groupBy('category')
            ->map(
                fn ($expenses) => $expenses->sum('amount')
            )
            ->sortByDesc(
                fn ($total) => $total
            )
            ->take($limit);
    }
}
