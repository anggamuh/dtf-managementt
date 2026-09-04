<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Machine;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialSplitController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $this->ensureAllowed($branchId);

        return view('materials.split', [
            'branchId' => $branchId,
            'branches' => $this->branches(),
            'machines' => Machine::where('branch_id', $branchId)->where('is_active', true)->orderBy('head_count')->get(),
            'materials' => Material::with('machine')
                ->where('branch_id', $branchId)
                ->whereNull('machine_id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, Material $material)
    {
        $branchId = $this->branchId($request);
        $this->ensureAllowed($branchId);
        abort_unless($material->branch_id === $branchId && $material->machine_id === null && $material->is_active, 404);

        $machines = Machine::where('branch_id', $branchId)->where('is_active', true)->orderBy('head_count')->get();
        $rules = [];

        foreach ($machines as $machine) {
            $rules["allocations.{$machine->id}.stock"] = ['required', 'numeric', 'min:0'];
            $rules["allocations.{$machine->id}.price"] = ['required', 'numeric', 'min:0'];
            $rules["allocations.{$machine->id}.minimum_stock"] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules);
        $allocatedStock = collect($data['allocations'])->sum(fn ($allocation) => (float) $allocation['stock']);

        if (abs($allocatedStock - (float) $material->stock) > 0.005) {
            throw ValidationException::withMessages([
                'allocations' => 'Total stok seluruh mesin harus sama dengan stok material lama ('.number_format((float) $material->stock, 2, ',', '.').').',
            ]);
        }

        DB::transaction(function () use ($material, $machines, $data) {
            $source = Material::query()->lockForUpdate()->findOrFail($material->id);
            abort_unless($source->machine_id === null && $source->is_active, 409);
            $originalStock = (float) $source->stock;

            foreach ($machines as $machine) {
                $allocation = $data['allocations'][$machine->id];
                $target = Material::query()
                    ->where('branch_id', $source->branch_id)
                    ->where('machine_id', $machine->id)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($source->name))])
                    ->lockForUpdate()
                    ->first();

                if (! $target) {
                    $target = Material::create([
                        'branch_id' => $source->branch_id,
                        'machine_id' => $machine->id,
                        'name' => $source->name,
                        'unit' => $source->unit,
                        'stock' => 0,
                        'minimum_stock' => $allocation['minimum_stock'],
                        'price' => $allocation['price'],
                        'supplier' => $source->supplier,
                        'is_active' => true,
                    ]);
                }

                $quantity = (float) $allocation['stock'];
                $target->update([
                    'stock' => (float) $target->stock + $quantity,
                    'minimum_stock' => $allocation['minimum_stock'],
                    'price' => $allocation['price'],
                    'is_active' => true,
                ]);

                if ($quantity > 0) {
                    $target->movements()->create([
                        'date' => now()->toDateString(),
                        'type' => 'opname',
                        'quantity' => $quantity,
                        'note' => "Pemisahan stok dari {$source->name} ke {$target->display_name}",
                    ]);
                }
            }

            if ($originalStock > 0) {
                $source->movements()->create([
                    'date' => now()->toDateString(),
                    'type' => 'opname',
                    'quantity' => -$originalStock,
                    'note' => 'Pemisahan stok lama ke material per mesin',
                ]);
            }

            $source->update(['stock' => 0, 'is_active' => false]);
        });

        return redirect()->route('materials.split.index', ['branch_id' => $branchId])
            ->with('message', 'Material lama berhasil dipisahkan tanpa mengubah total stok. Histori lama tetap tersimpan.');
    }

    private function ensureAllowed(int $branchId): void
    {
        abort_if($branchId === 0, 422, 'Pilih satu cabang terlebih dahulu.');
        abort_unless(
            auth()->user()->hasAnyRole(['Super Admin', 'Owner', 'Admin EPUL', 'Admin RAPLY'])
                && ($this->canAccessAllBranches() || (int) auth()->user()->branch_id === $branchId),
            403
        );
        abort_unless(Machine::where('branch_id', $branchId)->where('is_active', true)->exists(), 404);
    }
}
