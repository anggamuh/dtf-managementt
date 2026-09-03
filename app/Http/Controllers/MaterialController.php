<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Expense;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    use ResolvesBranch;

    public function index(Request $r)
    {
        $branchId = $this->branchId($r);

        /*
         * Daftar material.
         *
         * Harga material = Harga Standar/Master.
         * Total Nilai = total pembelian aktual dari Expense
         * kategori "Bahan Baku".
         */
        $materials = Material::with('movements')
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->when(
                $r->search,
                fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        /*
         * Ambil total pembelian aktual untuk setiap material
         * pada halaman yang sedang ditampilkan.
         *
         * Contoh:
         * POWDER
         * Expense Bahan Baku = Rp16.250.000
         *
         * Maka:
         * $material->purchase_value = 16.250.000
         */
        $materialIds = $materials->getCollection()->pluck('id');

        $purchaseValues = Expense::query()
            ->whereIn('material_id', $materialIds)
            ->where('category', 'Bahan Baku')
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->selectRaw('material_id, SUM(amount) as total_purchase')
            ->groupBy('material_id')
            ->pluck('total_purchase', 'material_id');

        /*
         * Tambahkan purchase_value ke masing-masing material.
         */
        $materials->getCollection()->transform(function ($material) use ($purchaseValues) {
            $material->purchase_value = (float) (
                $purchaseValues[$material->id] ?? 0
            );

            return $material;
        });

        /*
         * Total seluruh pembelian Bahan Baku.
         *
         * Ini yang digunakan untuk:
         * - kartu "Total Nilai Stok"
         * - footer "Total Nilai Stok Bahan Baku"
         *
         * Nilainya mengikuti total pembelian aktual,
         * bukan stock × harga standar.
         */
        $totalValue = Expense::query()
            ->where('category', 'Bahan Baku')
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->sum('amount');

        /*
         * Hitung material yang stoknya berada di bawah/sama
         * dengan stok minimum.
         */
        $lowStockCount = Material::query()
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        return view(
            'materials.index',
            compact(
                'materials',
                'branchId',
                'totalValue',
                'lowStockCount'
            ) + [
                'branches' => $this->branches(),
            ]
        );
    }

    public function create(Request $r)
    {
        return view('materials.form', [
            'material' => new Material([
                'branch_id' => $this->branchId($r),
                'stock' => 0,
                'price' => 0,
            ]),
            'branches' => $this->branches(),
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
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
        ]);

        $d['branch_id'] = $this->branchId($r);
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

    public function edit(Material $material)
    {
        $this->guard($material);

        return view('materials.form', [
            'material' => $material,
            'branches' => $this->branches(),
        ]);
    }

    /**
     * Perbarui data material.
     *
     * Stock tidak diedit dari form ini.
     * Stock diubah melalui:
     * - pembelian Bahan Baku
     * - Stok Opname
     *
     * Price adalah Harga Standar/Master dan dapat diubah
     * secara manual dari form Material.
     */
    public function update(Request $r, Material $material)
    {
        $this->guard($material);

        $d = $r->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
        ]);

        $material->update($d);

        return redirect()
            ->route('materials.index', [
                'branch_id' => $material->branch_id,
            ])
            ->with('message', 'Material berhasil diperbarui.');
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
            $diff = (float) $d['stock'] - (float) $material->stock;

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

        /*
         * Material tidak boleh dihapus jika sudah digunakan
         * pada stock movement atau historical closing.
         */
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
}