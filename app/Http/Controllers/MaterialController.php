<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    use ResolvesBranch;

    public function index(Request $r)
    {
        $branchId = $this->branchId($r);
        $materials = Material::with('movements')
            ->when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->when($r->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $totalValue = Material::where('branch_id', $branchId)
            ->get()
            ->sum(fn ($m) => $m->stock * $m->price);

        $lowStockCount = Material::where('branch_id', $branchId)
            ->whereColumn('stock', '<=', 'minimum_stock')
            ->count();

        return view('materials.index', compact('materials', 'branchId', 'totalValue', 'lowStockCount') + ['branches' => $this->branches()]);
    }

    public function create(Request $r)
    {
        return view('materials.form', [
            'material' => new Material(['branch_id' => $this->branchId($r)]),
            'branches' => $this->branches(),
        ]);
    }

    /**
     * Simpan material baru.
     * Stock dan price diatur otomatis melalui pembelian (Expense "Bahan Baku")
     * dan stok opname. Tidak ada input manual untuk stock/price.
     */
    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
        ]);

        $d['branch_id'] = $this->branchId($r);
        $d['stock'] = 0;
        $d['price'] = 0;

        Material::create($d);

        return redirect()
            ->route('materials.index', ['branch_id' => $d['branch_id']])
            ->with('message', 'Material berhasil disimpan. Stok dan harga akan terisi otomatis dari pembelian (Expense kategori Bahan Baku).');
    }

    public function edit(Material $material)
    {
        $this->guard($material);
        return view('materials.form', ['material' => $material, 'branches' => $this->branches()]);
    }

    /**
     * Perbarui data material.
     * Stock dan price tidak bisa diedit di sini:
     * - Stock: gunakan Stok Opname (halaman index)
     * - Price: terisi otomatis dari pembelian (weighted average)
     */
    public function update(Request $r, Material $material)
    {
        $this->guard($material);

        $d = $r->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'minimum_stock' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
        ]);

        $material->update($d);

        return redirect()
            ->route('materials.index', ['branch_id' => $material->branch_id])
            ->with('message', 'Material berhasil diperbarui.');
    }

    /**
     * Stok Opname: satu-satunya cara mengubah stock material secara manual.
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

            $material->update(['stock' => $d['stock']]);
        });

        return back()->with('message', 'Stok opname berhasil disimpan.');
    }

    public function destroy(Material $material)
    {
        $this->guard($material);

        // A material can be referenced by stock movements and historical closing
        // components. Do not rely on the database constraint for this user-facing
        // validation: it would expose a SQL exception instead of a clear message.
        if ($material->movements()->exists() || $material->closingMaterials()->exists()) {
            return back()->with('error', 'Data tidak dapat dihapus karena sudah digunakan pada transaksi atau closing.');
        }

        $material->delete();
        return back()->with('message', 'Material berhasil dihapus.');
    }

    private function guard(Material $m): void
    {
        abort_unless($this->canAccessAllBranches() || $m->branch_id === auth()->user()->branch_id, 403);
    }
}
