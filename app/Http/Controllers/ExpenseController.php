<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\Material;
use App\Services\MaterialPurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    use ResolvesBranch;

    public const CATEGORIES = [
        'Bahan Baku',
        'Operasional',
        'Transport',
        'ATK',
        'Gaji',
        'Listrik',
        'Internet',
        'Teknisi',
        'Lainnya',
    ];

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);

        $search = trim((string) $request->input('search', ''));

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

        $materialSearch = $searchedHeadCount !== null
            ? $baseSearch
            : $search;

        $expenses = Expense::with('material.machine')
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->when(
                $search,
                fn ($query) => $query->where(function ($q) use (
                    $search,
                    $materialSearch,
                    $searchedHeadCount
                ) {
                    $q->where(
                        'description',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'category',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'payment_method',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'amount',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'material',
                        fn ($materialQuery) => $materialQuery
                            ->where(
                                'name',
                                'like',
                                "%{$materialSearch}%"
                            )
                            ->when(
                                $searchedHeadCount !== null,
                                fn ($machineMaterialQuery) =>
                                    $machineMaterialQuery->whereHas(
                                        'machine',
                                        fn ($machineQuery) =>
                                            $machineQuery->where(
                                                'head_count',
                                                $searchedHeadCount
                                            )
                                    )
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
                            )
                    );
                })
            )
            ->when(
                $request->category,
                fn ($query, $category) =>
                    $query->where('category', $category)
            )
            ->when(
                $request->date_start,
                fn ($query, $date) =>
                    $query->whereDate('date', '>=', $date)
            )
            ->when(
                $request->date_end,
                fn ($query, $date) =>
                    $query->whereDate('date', '<=', $date)
            )
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view(
            'expenses.index',
            compact(
                'expenses',
                'branchId'
            ) + [
                'branches' => $this->branches(),
                'categories' => self::CATEGORIES,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(Request $request)
    {
        $expense = new Expense([
            'date' => now()->toDateString(),
            'branch_id' => $this->branchId($request),
            'payment_method' => 'Tunai',
        ]);

        return view(
            'expenses.form',
            $this->formData($expense)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        MaterialPurchaseService $purchases
    ) {
        $data = $this->data($request);

        $data['branch_id'] = $this->branchId($request);

        /*
        |--------------------------------------------------------------------------
        | Upload bukti transaksi
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('proof')) {
            $data['proof'] = $request
                ->file('proof')
                ->store('proofs', 'public');
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan transaksi
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $data,
            $purchases,
            &$expense
        ) {
            $expense = Expense::create($data);

            $purchases->sync(
                $expense,
                'Pembelian'
            );
        });

        return redirect()
            ->route('expenses.index', [
                'branch_id' => $expense->branch_id,
            ])
            ->with(
                'message',
                'Pengeluaran berhasil disimpan.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Expense $expense)
    {
        $this->guard($expense);

        return view(
            'expenses.form',
            $this->formData($expense)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    |
    | PENTING:
    | Update menggunakan POST, bukan PUT/PATCH.
    |
    | Hal ini untuk menghindari 405 Not Allowed dari Nginx
    | ketika mengirim multipart/form-data dengan method PUT.
    |
    */

    public function update(
        Request $request,
        Expense $expense,
        MaterialPurchaseService $purchases
    ) {
        $this->guard($expense);

        $data = $this->data($request);

        /*
        |--------------------------------------------------------------------------
        | Upload bukti baru
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('proof')) {

            /*
            | Hapus bukti lama jika ada
            */
            if ($expense->proof) {
                Storage::disk('public')
                    ->delete($expense->proof);
            }

            /*
            | Simpan bukti baru
            */
            $data['proof'] = $request
                ->file('proof')
                ->store('proofs', 'public');
        }

        /*
        |--------------------------------------------------------------------------
        | Update transaksi + sinkronisasi stok
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $data,
            $expense,
            $purchases
        ) {

            /*
            | Kembalikan efek stok dari transaksi lama
            */
            $purchases->remove(
                $expense->load('material')
            );

            /*
            | Update data expense
            */
            $expense->update($data);

            /*
            | Terapkan kembali efek stok berdasarkan
            | data transaksi yang baru
            */
            $purchases->sync(
                $expense,
                'Edit pembelian'
            );
        });

        return redirect()
            ->route('expenses.index', [
                'branch_id' => $expense->branch_id,
            ])
            ->with(
                'message',
                'Pengeluaran berhasil diperbarui.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Expense $expense,
        MaterialPurchaseService $purchases
    ) {
        $this->guard($expense);

        DB::transaction(function () use (
            $expense,
            $purchases
        ) {

            /*
            | Kembalikan stok dari transaksi
            */
            $purchases->remove(
                $expense->load('material')
            );

            /*
            | Hapus bukti transaksi
            */
            if ($expense->proof) {
                Storage::disk('public')
                    ->delete($expense->proof);
            }

            /*
            | Hapus expense
            */
            $expense->delete();
        });

        return back()->with(
            'message',
            'Pengeluaran berhasil dihapus.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | BULK DESTROY
    |--------------------------------------------------------------------------
    */

    public function bulkDestroy(
        Request $request,
        MaterialPurchaseService $purchases
    ) {
        $branchId = $this->branchId($request);

        $ids = $request->input('ids', []);

        $expenses = Expense::whereIn('id', $ids)
            ->when(
                $branchId !== 0,
                fn ($q) =>
                    $q->where('branch_id', $branchId)
            )
            ->get();

        DB::transaction(function () use (
            $expenses,
            $purchases
        ) {

            foreach ($expenses as $expense) {

                $purchases->remove(
                    $expense->load('material')
                );

                if ($expense->proof) {
                    Storage::disk('public')
                        ->delete($expense->proof);
                }

                $expense->delete();
            }
        });

        return back()->with(
            'message',
            count($expenses) .
            ' pengeluaran berhasil dihapus.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    private function data(Request $request): array
    {
        $data = $request->validate([
            'date' => [
                'required',
                'date',
            ],

            'category' => [
                'required',
                Rule::in(self::CATEGORIES),
            ],

            'description' => [
                'required',
                'string',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'required',
                'string',
                'max:100',
            ],

            'material_id' => [
                'nullable',
                'integer',
                'exists:materials,id',
            ],

            'quantity' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'proof' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],

            'machine_id' => [
                'nullable',
                'integer',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validasi khusus Bahan Baku
        |--------------------------------------------------------------------------
        */

        if ($data['category'] === 'Bahan Baku') {

            $branchId = $this->branchId($request);

            $hasMachines = Machine::where(
                'branch_id',
                $branchId
            )
                ->where('is_active', true)
                ->exists();

            $request->validate([

                'machine_id' => [
                    $hasMachines
                        ? 'required'
                        : 'nullable',

                    'integer',

                    Rule::exists(
                        'machines',
                        'id'
                    )->where(
                        fn ($q) => $q
                            ->where(
                                'branch_id',
                                $branchId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                    ),
                ],

                'material_id' => [
                    'required',
                    'integer',

                    Rule::exists(
                        'materials',
                        'id'
                    )->where(
                        fn ($q) => $q
                            ->where(
                                'branch_id',
                                $branchId
                            )
                            ->where(
                                'is_active',
                                true
                            )
                            ->when(
                                $hasMachines,
                                fn ($machineQuery) =>
                                    $machineQuery->where(
                                        'machine_id',
                                        $request->integer(
                                            'machine_id'
                                        )
                                    )
                            )
                            ->when(
                                ! $hasMachines,
                                fn ($machineQuery) =>
                                    $machineQuery->whereNull(
                                        'machine_id'
                                    )
                            )
                    ),
                ],

                'quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],
            ]);

        } else {

            /*
            | Pengeluaran selain Bahan Baku
            | tidak boleh mempunyai material/quantity.
            */
            $data['material_id'] = null;
            $data['quantity'] = null;
        }

        /*
        | machine_id hanya dipakai untuk validasi.
        | Tidak disimpan langsung ke expenses.
        */
        unset($data['machine_id']);

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | FORM DATA
    |--------------------------------------------------------------------------
    */

    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,

            'branches' => $this->branches(),

            'categories' => self::CATEGORIES,

            'materials' => Material::with('machine')
                ->where(
                    'branch_id',
                    $expense->branch_id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('name')
                ->get(),

            'machines' => Branch::findOrFail(
                $expense->branch_id
            )
                ->machines()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('head_count')
                ->get(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESS GUARD
    |--------------------------------------------------------------------------
    */

    private function guard(Expense $expense): void
    {
        abort_unless(
            $this->canAccessAllBranches()
                || $expense->branch_id === auth()->user()->branch_id,
            403
        );
    }
}