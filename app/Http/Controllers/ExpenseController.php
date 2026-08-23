<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Expense;
use App\Models\Material;
use App\Services\MaterialPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    use ResolvesBranch;

    public const CATEGORIES = ['Bahan Baku', 'Operasional', 'Transport', 'ATK', 'Gaji', 'Listrik', 'Internet', 'Teknisi', 'Lainnya'];

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $expenses = Expense::with('material')
            ->when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->search, fn ($query, $search) => $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%");
            }))
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->date_start, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($request->date_end, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->latest('date')->paginate(15)->withQueryString();

        return view('expenses.index', compact('expenses', 'branchId') + ['branches' => $this->branches(), 'categories' => self::CATEGORIES]);
    }

    public function create(Request $request)
    {
        return view('expenses.form', $this->formData(new Expense([
            'date' => now()->toDateString(),
            'branch_id' => $this->branchId($request),
            'payment_method' => 'Tunai',
        ])));
    }

    public function store(Request $request, MaterialPurchaseService $purchases)
    {
        $data = $this->data($request);
        $data['branch_id'] = $this->branchId($request);

        if ($request->hasFile('proof')) {
            $data['proof'] = $request->file('proof')->store('proofs', 'public');
        }

        DB::transaction(function () use ($data, $purchases, &$expense) {
            $expense = Expense::create($data);
            $purchases->sync($expense);
        });

        return redirect()->route('expenses.index', ['branch_id' => $expense->branch_id])->with('message', 'Pengeluaran berhasil disimpan.');
    }

    public function edit(Expense $expense)
    {
        $this->guard($expense);

        return view('expenses.form', $this->formData($expense));
    }

    public function update(Request $request, Expense $expense, MaterialPurchaseService $purchases)
    {
        $this->guard($expense);
        $data = $this->data($request);

        if ($request->hasFile('proof')) {
            // Delete old proof
            if ($expense->proof) {
                \Storage::disk('public')->delete($expense->proof);
            }
            $data['proof'] = $request->file('proof')->store('proofs', 'public');
        }

        DB::transaction(function () use ($data, $expense, $purchases) {
            // Reverse the old stock effect before calculating the replacement purchase.
            $purchases->remove($expense->load('material'));
            $expense->update($data);
            $purchases->sync($expense);
        });

        return redirect()->route('expenses.index', ['branch_id' => $expense->branch_id])->with('message', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense, MaterialPurchaseService $purchases)
    {
        $this->guard($expense);

        DB::transaction(function () use ($expense, $purchases) {
            $purchases->remove($expense->load('material'));
            $expense->delete();
        });

        return back()->with('message', 'Pengeluaran berhasil dihapus.');
    }

    public function bulkDestroy(Request $request, MaterialPurchaseService $purchases)
    {
        $branchId = $this->branchId($request);
        $ids = $request->input('ids', []);
        
        $expenses = Expense::whereIn('id', $ids)->when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))->get();

        DB::transaction(function () use ($expenses, $purchases) {
            foreach ($expenses as $expense) {
                $purchases->remove($expense->load('material'));
                $expense->delete();
            }
        });

        return back()->with('message', count($expenses).' pengeluaran berhasil dihapus.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:100'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($data['category'] === 'Bahan Baku') {
            $request->validate([
                'material_id' => ['required', 'integer', 'exists:materials,id'],
                'quantity' => ['required', 'numeric', 'gt:0'],
            ]);
            abort_unless(Material::whereKey($data['material_id'])->where('branch_id', $this->branchId($request))->exists(), 422, 'Material harus berasal dari cabang yang aktif.');
        } else {
            $data['material_id'] = null;
            $data['quantity'] = null;
        }

        return $data;
    }

    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'branches' => $this->branches(),
            'categories' => self::CATEGORIES,
            'materials' => Material::where('branch_id', $expense->branch_id)->orderBy('name')->get(),
        ];
    }

    private function guard(Expense $expense): void
    {
        abort_unless($this->canAccessAllBranches() || $expense->branch_id === auth()->user()->branch_id, 403);
    }
}
