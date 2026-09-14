<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ResolvesBranch;

    public function __invoke(Request $request)
    {
        $branchId = $this->branchId($request);
        $isAllBranches = $this->canAccessAllBranches() && $branchId === 0;

        $preset = $request->input('preset', 'month');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $monthInput = $request->input('month', now()->format('Y-m'));

        try {
            $selectedMonth = Carbon::createFromFormat('Y-m-d', $monthInput.'-01');
        } catch (\Exception $e) {
            try {
                $selectedMonth = Carbon::parse($monthInput)->startOfMonth();
            } catch (\Exception $e2) {
                $selectedMonth = now();
            }
        }

        $selectedMonth = $selectedMonth->startOfMonth();

        if ($startDateInput || $endDateInput) {
            try {
                $start = Carbon::parse($startDateInput ?: $selectedMonth->toDateString())->startOfDay();
                $end = Carbon::parse($endDateInput ?: $selectedMonth->copy()->endOfMonth()->toDateString())->endOfDay();
            } catch (\Exception $e) {
                $start = $selectedMonth->copy()->startOfMonth();
                $end = $selectedMonth->copy()->endOfMonth();
            }
        } else {
            switch ($preset) {
                case 'today':
                    $start = now()->startOfDay();
                    $end = now()->endOfDay();
                    break;
                case '7d':
                    $start = now()->subDays(6)->startOfDay();
                    $end = now()->endOfDay();
                    break;
                case 'last-closing':
                    $closingDate = Carbon::parse(
                        \App\Models\Closing::query()
                            ->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))
                            ->orderByDesc('year')
                            ->orderByDesc('month')
                            ->value('period_end')
                            ?? now()->toDateString()
                    );
                    $start = Carbon::parse($closingDate)->copy()->startOfMonth();
                    $end = Carbon::parse($closingDate)->copy()->endOfMonth();
                    break;
                default:
                    $start = $selectedMonth->copy()->startOfMonth();
                    $end = $selectedMonth->copy()->endOfMonth();
                    break;
            }
        }

        if ($end->lt($start)) {
            return back()->with('error', 'Tanggal akhir harus sama atau setelah tanggal awal.');
        }

        $income = $this->calculateIncome($branchId, $start, $end, $isAllBranches);
        $expense = $this->calculateExpense($branchId, $start, $end, $isAllBranches);

        $monthly = collect(range(5, 0))->map(function ($offset) use ($branchId, $isAllBranches, $selectedMonth) {
            $month = $selectedMonth->copy()->subMonths($offset);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            $monthIncome = $this->calculateIncome($branchId, $monthStart, $monthEnd, $isAllBranches);
            $monthExpense = $this->calculateExpense($branchId, $monthStart, $monthEnd, $isAllBranches);

            return [
                'label' => $month->translatedFormat('M'),
                'income' => (float) $monthIncome,
                'expense' => (float) $monthExpense,
                'profit' => (float) ($monthIncome - $monthExpense),
            ];
        })->toArray();

        $expenseByCategory = $this->getExpenseByCategory($branchId, $start, $end, $isAllBranches);
        $recentOrdersQuery = Order::productionReady()->with(['customer', 'user', 'branch']);
        $recentOrders = $isAllBranches
            ? $recentOrdersQuery->latest('date')->take(5)->get()
            : $recentOrdersQuery->where('branch_id', $branchId)->latest('date')->take(5)->get();
        $recentInvoices = $isAllBranches ? Invoice::with('customer')->latest('date')->take(5)->get() : Invoice::with('customer')->where('branch_id', $branchId)->latest('date')->take(5)->get();
        $recentExpenses = $isAllBranches ? Expense::with('material.machine')->latest('date')->take(5)->get() : Expense::with('material.machine')->where('branch_id', $branchId)->latest('date')->take(5)->get();

        $orderStatusBreakdown = $this->getOrderStatusBreakdown($branchId, $start, $end, $isAllBranches);
        $invoiceSummary = $this->getInvoiceSummary($branchId, $start, $end, $isAllBranches);
        $materialStatus = $this->getMaterialStatus($branchId, $isAllBranches);
        $branchSummary = $this->getBranchSummary($branchId, $start, $end, $isAllBranches);
        $actionItems = $this->getActionItems($branchId, $start, $end, $isAllBranches);
        $dataQuality = $this->getDataQualityChecks($branchId, $start, $end, $isAllBranches);
        $lowMaterials = $this->getLowMaterials($branchId, $isAllBranches);

        $totalMeters = (float) Order::query()
            ->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('date', [$start, $end])
            ->where(function ($q) {
                $q->where('order_type', '!=', 'customer_custom')
                    ->orWhereNull('order_type')
                    ->orWhere('payment_status', 'paid');
            })
            ->sum('qty');

        $orderCount = Order::query()
            ->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('date', [$start, $end])
            ->where(function ($q) {
                $q->where('order_type', '!=', 'customer_custom')
                    ->orWhereNull('order_type')
                    ->orWhere('payment_status', 'paid');
            })
            ->count();

        $invoiceCount = Invoice::query()
            ->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('date', [$start, $end])
            ->count();

        $customerCount = $isAllBranches ? Customer::count() : Customer::where('branch_id', $branchId)->count();
        $materialCount = $isAllBranches ? Material::count() : Material::where('branch_id', $branchId)->count();

        $dataUpdatedAt = $this->getDataUpdatedAt($branchId);

        return view('dashboard.index', compact(
            'monthly',
            'expenseByCategory',
            'recentOrders',
            'recentInvoices',
            'recentExpenses',
            'orderStatusBreakdown',
            'invoiceSummary',
            'materialStatus',
            'branchSummary',
            'actionItems',
            'dataQuality',
            'lowMaterials'
        ) + [
            'branches' => $this->branches(),
            'branchId' => $branchId,
            'selectedMonth' => $selectedMonth,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'periodLabel' => $start->translatedFormat('d M Y') . ' - ' . $end->translatedFormat('d M Y'),
            'income' => (float) $income,
            'expense' => (float) $expense,
            'profit' => (float) ($income - $expense),
            'totalSales' => (float) $income,
            'totalExpense' => (float) $expense,
            'totalMeters' => (float) $totalMeters,
            'invoiceCount' => $invoiceCount,
            'customerCount' => $customerCount,
            'orderCount' => $orderCount,
            'materialCount' => $materialCount,
            'preset' => $preset,
            'dataUpdatedAt' => $dataUpdatedAt,
        ]);
    }

    /**
     * Total Penjualan sekarang diambil langsung dari Order (bukan dari Invoice
     * yang berstatus paid lagi), supaya kelihatan begitu order dibuat/selesai,
     * nggak nunggu ditagihkan/dibuatkan invoice dulu.
     * Order berstatus 'cancelled' tidak dihitung sebagai penjualan.
     */
    private function calculateIncome($branchId, Carbon $start, ?Carbon $end, bool $isAllBranches): float
    {
        $query = Order::productionReady()->where('status', '!=', 'cancelled')->whereBetween('date', [$start, $end ?? $start]);

        return (float) ($isAllBranches ? $query->sum('total') : $query->where('branch_id', $branchId)->sum('total'));
    }

    private function calculateExpense($branchId, Carbon $start, ?Carbon $end, bool $isAllBranches): float
    {
        $query = Expense::whereBetween('date', [$start, $end ?? $start]);

        return (float) ($isAllBranches ? $query->sum('amount') : $query->where('branch_id', $branchId)->sum('amount'));
    }

    private function getExpenseByCategory($branchId, Carbon $start, Carbon $end, bool $isAllBranches)
    {
        $query = Expense::whereBetween('date', [$start, $end])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->take(5);

        $results = $isAllBranches ? $query->get() : $query->where('branch_id', $branchId)->get();

        return $results->map(fn ($item) => (object) [
            'category' => $item->category,
            'total' => (float) $item->total,
        ]);
    }

    private function getOrderStatusBreakdown(int $branchId, Carbon $start, Carbon $end, bool $isAllBranches): array
    {
        $query = Order::query()
            ->whereBetween('date', [$start, $end])
            ->where(function ($q) {
                $q->where('order_type', '!=', 'customer_custom')
                    ->orWhereNull('order_type')
                    ->orWhere('payment_status', 'paid');
            });

        if (! $isAllBranches) {
            $query->where('branch_id', $branchId);
        }

        return [
            'masuk' => (int) $query->clone()->count(),
            'diambil' => (int) $query->clone()->where('status', 'completed')->count(),
            'diproses' => (int) $query->clone()->whereIn('status', ['pending', 'confirmed', 'processing'])->count(),
            'dicetak' => (int) $query->clone()->where('status', 'printed')->count(),
            'selesai' => (int) $query->clone()->where('status', 'completed')->count(),
            'dibatalkan' => (int) $query->clone()->where('status', 'cancelled')->count(),
        ];
    }

    private function getInvoiceSummary(int $branchId, Carbon $start, Carbon $end, bool $isAllBranches): array
    {
        $query = Invoice::query()->whereBetween('date', [$start, $end]);

        if (! $isAllBranches) {
            $query->where('branch_id', $branchId);
        }

        $invoices = $query->get();

        return [
            'total' => (float) $invoices->sum('total'),
            'dibayar' => (float) $invoices->sum('paid'),
            'belum_dibayar' => (float) $invoices->sum(fn ($invoice) => max(0, (float) $invoice->total - (float) $invoice->paid)),
            'draft' => (int) $invoices->filter(fn ($invoice) => strtolower((string) $invoice->status) === 'draft')->count(),
            'partial' => (int) $invoices->filter(fn ($invoice) => strtolower((string) $invoice->status) === 'partial')->count(),
            'paid' => (int) $invoices->filter(fn ($invoice) => strtolower((string) $invoice->status) === 'paid')->count(),
        ];
    }

    private function getMaterialStatus(int $branchId, bool $isAllBranches): array
    {
        $query = Material::query()->where('is_active', true)->whereColumn('stock', '<=', 'minimum_stock');

        if (! $isAllBranches) {
            $query->where('branch_id', $branchId);
        }

        return $query->with('branch', 'machine')->orderBy('stock')->get()->map(fn ($material) => [
            'id' => $material->id,
            'name' => $material->name,
            'display_name' => $material->display_name,
            'stock' => (float) $material->stock,
            'minimum_stock' => (float) $material->minimum_stock,
            'unit' => $material->unit,
            'branch_id' => $material->branch_id,
            'branch_name' => $material->branch?->name ?? 'Cabang',
        ])->all();
    }

    private function getBranchSummary(int $branchId, Carbon $start, Carbon $end, bool $isAllBranches): array
    {
        $branches = $this->branches()->pluck('id')->all();
        if ($isAllBranches) {
            $branches = array_filter($branches, fn ($id) => (int) $id !== 0);
        } else {
            $branches = [$branchId];
        }

        $summary = [];

        foreach ($branches as $id) {
            $branch = \App\Models\Branch::find($id);
            $branchIncome = $this->calculateIncome((int) $id, $start, $end, false);
            $branchExpense = $this->calculateExpense((int) $id, $start, $end, false);
            $branchProfit = $branchIncome - $branchExpense;
            $branchMeters = (float) Order::query()
                ->where('branch_id', $id)
                ->whereBetween('date', [$start, $end])
                ->where(function ($q) {
                    $q->where('order_type', '!=', 'customer_custom')
                        ->orWhereNull('order_type')
                        ->orWhere('payment_status', 'paid');
                })
                ->sum('qty');

            $branchInvoices = Invoice::query()->where('branch_id', $id)->whereBetween('date', [$start, $end])->get();
            $branchUnpaid = (float) $branchInvoices->sum(fn ($invoice) => max(0, (float) $invoice->total - (float) $invoice->paid));
            $branchBalance = (float) ($branch?->saldo_tahanan ?? ($id === 1 ? 20000000 : 10000000));
            $branchRealtime = (float) \App\Models\LedgerPeriodBalance::query()
                ->where('branch_id', $id)
                ->whereDate('period_start', '<=', $end)
                ->whereDate('period_end', '>=', $start)
                ->sum('saldo_realtime');
            $closingStock = \App\Models\Closing::query()->where('branch_id', $id)->whereDate('period_start', '<=', $start)->whereDate('period_end', '>=', $end)->latest('id')->first();
            $remainingMaterial = $closingStock?->materials?->sum(fn ($material) => (float) ($material->stock_akhir ?? 0) * (float) ($material->harga_komponen ?? 0)) ?? 0;
            $branchSaldoSisa = $branchBalance - $remainingMaterial;
            $branchProfitValue = $branchIncome - $branchExpense;
            $branchSelisih = $branchRealtime - ($branchProfitValue + $branchSaldoSisa);

            $summary[] = [
                'id' => $id,
                'name' => $branch?->name ?? 'Cabang',
                'code' => $branch?->code ?? 'CABANG',
                'income' => (float) $branchIncome,
                'expense' => (float) $branchExpense,
                'profit' => (float) $branchProfit,
                'meters' => (float) $branchMeters,
                'orders' => (int) Order::query()->where('branch_id', $id)->whereBetween('date', [$start, $end])->where(function ($q) {
                    $q->where('order_type', '!=', 'customer_custom')
                        ->orWhereNull('order_type')
                        ->orWhere('payment_status', 'paid');
                })->count(),
                'unpaid_invoices' => (float) $branchUnpaid,
                'saldo_tahanan' => $branchBalance,
                'saldo_realtime' => $branchRealtime,
                'saldo_tahanan_sisa' => $branchSaldoSisa,
                'selisih' => $branchSelisih,
            ];
        }

        return $summary;
    }

    private function getActionItems(int $branchId, Carbon $start, Carbon $end, bool $isAllBranches): array
    {
        $items = [];

        $unpaidInvoices = Invoice::query()
            ->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'partial')
            ->count();

        if ($unpaidInvoices > 0) {
            $items[] = ['title' => 'Invoice belum lunas', 'count' => $unpaidInvoices, 'description' => 'Ada invoice yang masih memiliki sisa tagihan.', 'level' => 'warning', 'route' => route('invoices.index', ['branch_id' => $branchId, 'status' => 'partial', 'date_start' => $start->toDateString(), 'date_end' => $end->toDateString()])];
        }

        $lowMaterials = $this->getLowMaterials($branchId, $isAllBranches);
        if (! empty($lowMaterials)) {
            $items[] = ['title' => 'Material di bawah stok minimum', 'count' => count($lowMaterials), 'description' => 'Material perlu restock segera.', 'level' => 'critical', 'route' => route('materials.index', ['branch_id' => $branchId])];
        }

        $pendingExpenses = Expense::query()
            ->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('proof')
            ->count();
        if ($pendingExpenses > 0) {
            $items[] = ['title' => 'Pengeluaran tanpa bukti', 'count' => $pendingExpenses, 'description' => 'Belum ada bukti pendukung untuk pengeluaran ini.', 'level' => 'urgent', 'route' => route('expenses.index', ['branch_id' => $branchId, 'proof' => 'missing'])];
        }

        $futureOrders = Order::query()
            ->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('date', '>', now()->toDateString())
            ->count();
        if ($futureOrders > 0) {
            $items[] = ['title' => 'Data order di masa depan', 'count' => $futureOrders, 'description' => 'Ada order dengan tanggal di masa depan.', 'level' => 'warning', 'route' => route('orders.index', ['branch_id' => $branchId, 'date_start' => now()->toDateString(), 'date_end' => now()->addDays(365)->toDateString()])];
        }

        $unfinishedClosing = \App\Models\Closing::query()
            ->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))
            ->where('is_locked', false)
            ->count();
        if ($unfinishedClosing > 0) {
            $items[] = ['title' => 'Closing belum dikunci', 'count' => $unfinishedClosing, 'description' => 'Masih ada periode closing yang belum final.', 'level' => 'info', 'route' => route('closing.index', ['branch_id' => $branchId])];
        }

        return $items;
    }

    private function getDataQualityChecks(int $branchId, Carbon $start, Carbon $end, bool $isAllBranches): array
    {
        $checks = [];

        $futureOrders = Order::query()->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))->whereDate('date', '>', now()->toDateString())->count();
        $checks[] = ['title' => 'Pesanan bertanggal melewati hari ini', 'count' => $futureOrders, 'level' => 'warning', 'message' => $futureOrders > 0 ? 'Ada order yang tanggalnya lebih besar dari hari ini.' : 'Tidak ditemukan masalah data pada periode ini.'];

        $duplicateOrders = Order::query()->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))->select('customer_id', 'product_id', 'date', DB::raw('COUNT(*) as total'))->groupBy('customer_id', 'product_id', 'date')->having('total', '>', 1)->count();
        $checks[] = ['title' => 'Kandidat order duplikat', 'count' => $duplicateOrders, 'level' => 'info', 'message' => $duplicateOrders > 0 ? 'Ada kandidat duplikasi order yang perlu dicek.' : 'Tidak ditemukan masalah data pada periode ini.'];

        $missingBranch = Order::query()->whereNull('branch_id')->count() + Expense::query()->whereNull('branch_id')->count() + Invoice::query()->whereNull('branch_id')->count();
        $checks[] = ['title' => 'Transaksi tanpa cabang', 'count' => $missingBranch, 'level' => 'warning', 'message' => $missingBranch > 0 ? 'Masih ada transaksi yang belum punya cabang.' : 'Tidak ditemukan masalah data pada periode ini.'];

        $missingMaterial = Expense::query()->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))->where('category', 'Bahan Baku')->whereNull('material_id')->count();
        $checks[] = ['title' => 'Expense Bahan Baku tanpa material', 'count' => $missingMaterial, 'level' => 'critical', 'message' => $missingMaterial > 0 ? 'Perlu dipasangkan material agar stok valid.' : 'Tidak ditemukan masalah data pada periode ini.'];

        $materialWithoutUnit = Material::query()->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))->where(function ($q) {$q->whereNull('unit')->orWhereNull('price');})->count();
        $checks[] = ['title' => 'Material tanpa harga atau satuan', 'count' => $materialWithoutUnit, 'level' => 'warning', 'message' => $materialWithoutUnit > 0 ? 'Beberapa material belum terdefinisi lengkap.' : 'Tidak ditemukan masalah data pada periode ini.'];

        $unlockedPeriods = \App\Models\LedgerPeriodBalance::query()->when(! $isAllBranches, fn ($q) => $q->where('branch_id', $branchId))->whereNull('locked_at')->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)->count();
        $checks[] = ['title' => 'Saldo periode belum dikunci', 'count' => $unlockedPeriods, 'level' => 'info', 'message' => $unlockedPeriods > 0 ? 'Masih ada periode saldo yang belum final.' : 'Tidak ditemukan masalah data pada periode ini.'];

        return $checks;
    }

    private function getLowMaterials(int $branchId, bool $isAllBranches): array
    {
        $query = Material::query()->where('is_active', true)->whereColumn('stock', '<=', 'minimum_stock');

        if (! $isAllBranches) {
            $query->where('branch_id', $branchId);
        }

        return $query->with('branch', 'machine')->orderBy('stock')->get()->all();
    }

    private function getDataUpdatedAt(int $branchId): Carbon
    {
        $lastUpdated = collect([
            Order::query()->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))->max('updated_at'),
            Expense::query()->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))->max('updated_at'),
            Invoice::query()->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))->max('updated_at'),
            Material::query()->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))->max('updated_at'),
            \App\Models\Closing::query()->when($branchId !== 0, fn ($q) => $q->where('branch_id', $branchId))->max('updated_at'),
        ])->filter()->sort()->last();

        return $lastUpdated ? Carbon::parse($lastUpdated) : now();
    }
}
