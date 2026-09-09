<?php

namespace App\Services;

use App\Models\Closing;
use App\Models\ClosingMaterial;
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
            fn ($row) => (float) $row['stock_akhir']
                * (float) $row['unit_cost']
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
     * Pembelian Bahan Baku.
     *
     * qty:
     *     SUM(expense.quantity)
     *
     * pembelian:
     *     SUM(expense.amount)
     *
     * harga_komponen:
     *     tidak diubah di sini karena diisi manual per closing
     *
     * stock_akhir:
     *     TIDAK diubah otomatis.
     */
    public function populateMaterialPurchases(
        Closing $closing,
        Carbon $start,
        Carbon $end
    ): void {
        $purchases = Expense::query()
            ->where('branch_id', $closing->branch_id)
            ->where('category', 'Bahan Baku')
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->whereNotNull('material_id')
            ->whereNotNull('quantity')
            ->get()
            ->groupBy('material_id');

        $closing->loadMissing(
            'materials.material.machine'
        );

        foreach ($closing->materials as $component) {
            $materialId = $component->material_id;

            $materialPurchases = $purchases->get(
                $materialId
            );

            $incomingQty = 0;
            $purchaseValue = 0;

            if ($materialPurchases) {
                $incomingQty = (float) $materialPurchases
                    ->sum('quantity');

                $purchaseValue = (float) $materialPurchases
                    ->sum('amount');
            }

            $component->update([
                'qty' => $incomingQty,
                'pembelian' => $purchaseValue,
            ]);
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

        return $componentSource->map(
            function ($component) use ($purchases) {
                $stockAwal = (float) $component->stock_awal;
                $materialPurchases = $purchases->get(
                    $component->material_id,
                    collect()
                );
                $pembelianQty = (float) $materialPurchases->sum('quantity');
                $purchaseValue = (float) $materialPurchases->sum('amount');
                $stockAkhir = (float) $component->stock_akhir;
                $harga = (float) $component->harga_komponen;

                /*
                 * RUMUS UTAMA
                 *
                 * Stok Awal
                 * + Pembelian
                 * - Stok Akhir
                 */
                $usage = $stockAwal
                    + $pembelianQty
                    - $stockAkhir;

                /*
                 * Jangan sampai nilai pemakaian negatif
                 * karena stok akhir lebih besar.
                 */
                $usage = max(0, $usage);

                /*
                 * Total harga material:
                 *
                 * Pemakaian × harga material tetap.
                 */
                $materialCost = $usage * $harga;

                return [
                    'id' => $component->id,

                    'material' => $component->material,

                    'stock_awal' => $stockAwal,

                    'incoming_quantity' => $pembelianQty,

                    'purchase_value' => $purchaseValue,

                    'stock_akhir' => $stockAkhir,

                    'usage' => $usage,

                    'unit_cost' => $harga,

                    'material_cost' => $materialCost,

                    // Legacy aliases
                    'stockAwal' => $stockAwal,

                    'purchase' => $pembelianQty,

                    'pembelian' => $purchaseValue,

                    'stockAkhir' => $stockAkhir,

                    'harga' => $harga,

                    'value' => $materialCost,

                    'stockValue' => $stockAkhir * $harga,
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
        return Expense::query()
            ->where('branch_id', $branchId)
            ->where('category', 'Bahan Baku')
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->whereNotNull('material_id')
            ->get()
            ->groupBy('material_id');
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
        $closing->loadMissing(
            'materials'
        );

        /*
         * Total biaya material.
         */
        $materialCost = $closing->materials->sum(
            function ($component) {
                $stockAwal = (float) $component->stock_awal;
                $pembelian = (float) $component->qty;
                $stockAkhir = (float) $component->stock_akhir;
                $harga = (float) $component->harga_komponen;

                $pemakaian = max(
                    0,
                    $stockAwal
                    + $pembelian
                    - $stockAkhir
                );

                return $pemakaian * $harga;
            }
        );

        /*
         * Nilai Stock Opname (SO):
         * Stock Akhir x Harga Manual.
         */
        $remainingMaterial = $closing->materials->sum(
            fn ($component) => (float) $component->stock_akhir
                * (float) $component->harga_komponen
        );

        /*
         * Total biaya tetap.
         */
        $fixedCost =
            (float) ($closing->gaji_karyawan ?? 0)
            + (float) ($closing->operasional ?? 0)
            + (float) ($closing->lain_lain ?? 0)
            + (float) ($closing->teknisi_mesin ?? 0);

        /*
         * Total HPP.
         */
        $hpp = $materialCost + $fixedCost;

        /*
         * HASIL CETAK DARI ORDER.
         */
        $hasilCetak = $this->calculateHasilCetak(
            $closing->branch_id,
            $start,
            $end
        );

        /*
         * HPP per meter.
         */
        $hppPerMeter = $hasilCetak > 0
            ? $hpp / $hasilCetak
            : 0;

        $closing->update([
            'hpp' => $hpp,
            'hpp_per_meter' => $hppPerMeter,
            'remaining_material' => $remainingMaterial,
        ]);
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
         * Total Rp/Meter.
         */
        $totalRpMeter = $hppAllRows->sum(
            'rpMeter'
        );

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
