<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Branch;
use App\Models\Closing;
use App\Models\Material;
use App\Services\ClosingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MaterialController extends Controller
{
    use ResolvesBranch;

    public function __construct(
        private readonly ClosingService $closingService
    ) {}

    public function index(Request $r)
    {
        $branchId = $this->branchId($r);

        /*
         * ============================================================
         * RENTANG WAKTU PEMBELIAN
         * ============================================================
         *
         * Default = bulan berjalan.
         *
         * Rentang ini HANYA memengaruhi nilai pembelian:
         * - Total Nilai per material
         * - Total Nilai Stok Bahan Baku
         *
         * Stock material tetap stock aktual.
         */
        $dateFrom = $r->input(
            'date_from',
            now()->startOfMonth()->toDateString()
        );

        $dateTo = $r->input(
            'date_to',
            now()->endOfMonth()->toDateString()
        );

        try {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $to = Carbon::parse($dateTo)->endOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfMonth()->endOfDay();

            $dateFrom = $from->toDateString();
            $dateTo = $to->toDateString();
        }

        /*
         * Kalau tanggal terbalik, otomatis ditukar.
         */
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];

            $dateFrom = $from->toDateString();
            $dateTo = $to->toDateString();
        }

        $search = trim((string) $r->input('search', ''));

        preg_match('/(\d+)\s*head/i', $search, $headMatch);

        $searchedHeadCount = isset($headMatch[1])
            ? (int) $headMatch[1]
            : null;

        $baseSearch = trim(
            preg_replace(
                '/\(?\s*\d+\s*head\s*\)?/i',
                '',
                $search
            )
        );

        /*
         * Filter hanya menentukan material yang terlihat. Seluruh nilai
         * periode tetap dibangun oleh ClosingService agar tidak ada rumus
         * material kedua di controller ini.
         */
        $visibleMaterialIds = Material::query()
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->when(
                $search,
                fn ($q) => $q->where(function ($searchQuery) use (
                    $search,
                    $baseSearch,
                    $searchedHeadCount
                ) {
                    if ($searchedHeadCount !== null) {
                        $searchQuery
                            ->where(
                                'name',
                                'like',
                                "%{$baseSearch}%"
                            )
                            ->whereHas(
                                'machine',
                                fn ($machineQuery) =>
                                    $machineQuery->where(
                                        'head_count',
                                        $searchedHeadCount
                                    )
                            );

                        return;
                    }

                    $searchQuery
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'machine',
                            fn ($machineQuery) =>
                                $machineQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'code',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereRaw(
                                        'CAST(head_count AS CHAR) LIKE ?',
                                        ["%{$search}%"]
                                    )
                        );
                })
            )
            ->when(
                $r->machine_id === 'unassigned',
                fn ($q) => $q->whereNull('machine_id')
            )
            ->when(
                is_numeric($r->machine_id),
                fn ($q) => $q->where(
                    'machine_id',
                    (int) $r->machine_id
                )
            )
            ->where('is_active', true)
            ->pluck('id');

        $closing = $branchId === 0
            ? null
            : $this->matchingClosing($branchId, $from, $to);

        $allMaterialRows = $branchId === 0
            ? collect()
            : $this->closingService->getMaterialRows(
                $branchId,
                $from,
                $to,
                $closing
            );

        $totalValue = (float) $allMaterialRows->sum('purchase_value');
        $materialRows = $allMaterialRows
            ->filter(
                fn ($row) => $visibleMaterialIds->contains(
                    $row['material']->id
                )
            )
            ->values();
        $materialGroups = $materialRows->groupBy(
            fn ($row) => $row['material']->machine_id === null
                ? 'unassigned'
                : 'machine-'.$row['material']->machine_id
        );

        /*
         * ============================================================
         * LOW STOCK
         * ============================================================
         *
         * Tetap berdasarkan stock aktual.
         */
        $lowStockCount = Material::query()
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        $machines = $branchId === 0
            ? collect()
            : Branch::findOrFail($branchId)
                ->machines()
                ->where('is_active', true)
                ->orderBy('head_count')
                ->get();

        return view(
            'materials.index',
            compact(
                'materialRows',
                'materialGroups',
                'closing',
                'branchId',
                'totalValue',
                'lowStockCount',
                'dateFrom',
                'dateTo'
            ) + [
                'branches' => $this->branches(),
                'machines' => $machines,
            ]
        );
    }

    private function matchingClosing(
        int $branchId,
        Carbon $from,
        Carbon $to
    ): ?Closing {
        return Closing::query()
            ->where('branch_id', $branchId)
            ->where(function ($query) use ($from, $to) {
                $query->where(function ($periodQuery) use ($from, $to) {
                    $periodQuery
                        ->whereDate('period_start', $from->toDateString())
                        ->whereDate('period_end', $to->toDateString());
                });

                if (
                    $from->isStartOfMonth()
                    && $to->isSameDay($from->copy()->endOfMonth())
                ) {
                    $query->orWhere(function ($legacyQuery) use ($from) {
                        $legacyQuery
                            ->whereNull('period_start')
                            ->whereNull('period_end')
                            ->where('month', $from->month)
                            ->where('year', $from->year);
                    });
                }
            })
            ->first();
    }

    public function create(Request $r)
    {
        $branchId = $this->branchId($r);

        return view('materials.form', [
            'material' => new Material([
                'branch_id' => $branchId,
                'stock' => 0,
                'price' => 0,
            ]),
            'branches' => $this->branches(),
            'machines' => $this->activeMachines($branchId),
            'machineLocked' => false,
        ]);
    }

    /**
     * Simpan material baru.
     *
     * Stock selalu mulai dari 0.
     * Price adalah Harga Standar/Master yang ditentukan user.
     *
     * Pembelian Bahan Baku nantinya hanya menambah stock
     * dan tidak mengubah price.
     */
    public function store(Request $r)
    {
        $branchId = $this->branchId($r);
        $d = $this->validatedData($r, $branchId);
        $d['branch_id'] = $branchId;
        $d['stock'] = 0;

        Material::create($d);

        return redirect()
            ->route('materials.index', [
                'branch_id' => $d['branch_id'],
            ])
            ->with(
                'message',
                'Material berhasil disimpan. Harga Standar digunakan untuk master material dan tidak berubah otomatis saat pembelian.'
            );
    }

    private function validatedData(
        Request $r,
        int $branchId,
        ?Material $material = null
    ): array {
        $machines = $this->activeMachines($branchId);
        $machineLocked = $material && $this->machineLocked($material);

        $d = $r->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'machine_id' => [
                $machines->isNotEmpty() && ! $machineLocked
                    ? 'required'
                    : 'nullable',
                'integer',
                Rule::exists('machines', 'id')->where(
                    fn ($q) => $q
                        ->where('branch_id', $branchId)
                        ->where('is_active', true)
                ),
            ],
        ]);

        if ($machineLocked) {
            $d['machine_id'] = $material->machine_id;
        } elseif ($machines->isEmpty()) {
            $d['machine_id'] = null;
        }

        $normalizedName = mb_strtolower(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $d['name']
                )
            )
        );

        $duplicate = Material::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when(
                isset($d['machine_id']),
                fn ($q) => $q->where(
                    'machine_id',
                    $d['machine_id']
                ),
                fn ($q) => $q->whereNull('machine_id')
            )
            ->when(
                $material,
                fn ($q) => $q->whereKeyNot($material->id)
            )
            ->whereRaw(
                'LOWER(TRIM(name)) = ?',
                [$normalizedName]
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' =>
                    'Material dengan nama dan mesin tersebut sudah tersedia di cabang ini.',
            ]);
        }

        $d['name'] = trim(
            preg_replace(
                '/\s+/',
                ' ',
                $d['name']
            )
        );

        return $d;
    }

    public function edit(Material $material)
    {
        $this->guard($material);

        return view('materials.form', [
            'material' => $material->load('machine'),
            'branches' => $this->branches(),
            'machines' => $this->activeMachines($material->branch_id),
            'machineLocked' => $this->machineLocked($material),
        ]);
    }

    /**
     * Perbarui data material.
     *
     * Stock tidak diedit dari form ini.
     */
    public function update(Request $r, Material $material)
    {
        $this->guard($material);

        $d = $this->validatedData(
            $r,
            $material->branch_id,
            $material
        );

        $material->update($d);

        return redirect()
            ->route('materials.index', [
                'branch_id' => $material->branch_id,
            ])
            ->with(
                'message',
                'Material berhasil diperbarui.'
            );
    }

    /**
     * Stok Opname:
     * satu-satunya cara mengubah stock material secara manual.
     */
    public function opname(Request $r, Material $material)
    {
        $this->guard($material);

        $d = $r->validate([
            'stock' => 'required|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($material, $d) {
            $diff =
                (float) $d['stock']
                - (float) $material->stock;

            if ($diff !== 0.0) {
                $material->movements()->create([
                    'date' => now()->toDateString(),
                    'type' => 'opname',
                    'quantity' => $diff,
                    'note' => $d['note'] ?? 'Stock opname',
                ]);
            }

            $material->update([
                'stock' => $d['stock'],
            ]);
        });

        return back()->with(
            'message',
            'Stok opname berhasil disimpan.'
        );
    }

    public function destroy(Material $material)
    {
        $this->guard($material);

        if (
            $material->movements()->exists()
            || $material->closingMaterials()->exists()
        ) {
            return back()->with(
                'error',
                'Data tidak dapat dihapus karena sudah digunakan pada transaksi atau closing.'
            );
        }

        $material->delete();

        return back()->with(
            'message',
            'Material berhasil dihapus.'
        );
    }

    private function guard(Material $m): void
    {
        abort_unless(
            $this->canAccessAllBranches()
                || $m->branch_id === auth()->user()->branch_id,
            403
        );
    }

    private function activeMachines(int $branchId)
    {
        return Branch::findOrFail($branchId)
            ->machines()
            ->where('is_active', true)
            ->orderBy('head_count')
            ->get();
    }

    private function machineLocked(Material $material): bool
    {
        return (float) $material->stock !== 0.0
            || $material->expenses()->exists()
            || $material->movements()->exists()
            || $material->closingMaterials()->exists();
    }
}
