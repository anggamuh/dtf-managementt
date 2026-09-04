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
        $materialRows = $this->buildMaterialRows(
            $branchId,
            $month,
            $year,
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

    public function calculateIncome(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): float {
        return (float) Invoice::query()
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
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
     * Membuat komponen material yang belum ada di closing.
     *
     * Stok awal bulan:
     * - ambil stock_akhir dari closing bulan sebelumnya
     * - jika belum ada closing sebelumnya, ambil stock material saat ini
     */
    public function syncMaterialComponents(
        Closing $closing
    ): void {
        $prevMonth = $closing->month === 1
            ? 12
            : $closing->month - 1;

        $prevYear = $closing->month === 1
            ? $closing->year - 1
            : $closing->year;

        $prevClosing = Closing::query()
            ->where('branch_id', $closing->branch_id)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();

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
            $prevComponent = $prevClosing?->materials()
                ->where('material_id', $material->id)
                ->first();

            $stockAwal = $prevComponent?->stock_akhir
                ?? $material->stock;

            $closing->materials()->create([
                'material_id' => $material->id,

                'stock_awal' => (float) $stockAwal,

                /*
                 * Harga diisi manual per closing.
                 */
                'harga_komponen' => 0,

                'qty' => 0,

                'pembelian' => 0,

                /*
                 * Default stock akhir sama dengan stock awal.
                 * User bisa mengubah melalui Stock Akhir.
                 */
                'stock_akhir' => (float) $stockAwal,
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
    public function buildMaterialRows(
        int $branchId,
        int $month,
        int $year,
        ?Closing $closing
    ): Collection {
        $materials = Material::query()
            ->with('machine')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN machine_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('machine_id')
            ->orderBy('name')
            ->get();

        if ($closing) {
            $closing->loadMissing(
                'materials.material.machine'
            );

            $componentSource = $closing->materials;
        } else {
            $componentSource = $this->buildPreviewMaterialRows(
                $branchId,
                $month,
                $year,
                $materials
            );
        }

        return $componentSource->map(
            function ($component) {
                $stockAwal = (float) $component->stock_awal;
                $pembelianQty = (float) $component->qty;
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

                    'purchase_value' => (float) $component->pembelian,

                    'stock_akhir' => $stockAkhir,

                    'usage' => $usage,

                    'unit_cost' => $harga,

                    'material_cost' => $materialCost,

                    // Legacy aliases
                    'stockAwal' => $stockAwal,

                    'purchase' => $pembelianQty,

                    'pembelian' => (float) $component->pembelian,

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
                : '0'.str_pad((string) $material->machine_id, 12, '0', STR_PAD_LEFT);

            return $machineOrder.'|'.mb_strtolower($material->name);
        })->values();
    }

    /**
     * Preview sebelum closing dibuat.
     */
    private function buildPreviewMaterialRows(
        int $branchId,
        int $month,
        int $year,
        Collection $materials
    ): Collection {
        $prevMonth = $month === 1
            ? 12
            : $month - 1;

        $prevYear = $month === 1
            ? $year - 1
            : $year;

        $prevClosing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();

        return $materials->map(
            function ($material) use ($prevClosing) {
                $prevComponent = $prevClosing?->materials()
                    ->where('material_id', $material->id)
                    ->first();

                $stockAwal = $prevComponent?->stock_akhir
                    ?? $material->stock;

                return (new ClosingMaterial([
                    'material_id' => $material->id,

                    'stock_awal' => (float) $stockAwal,

                    'harga_komponen' => 0,

                    'qty' => 0,

                    'pembelian' => 0,

                    'stock_akhir' => (float) $stockAwal,
                ]))->setRelation(
                    'material',
                    $material
                );
            }
        );
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
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
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
            ->whereBetween('date', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
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
