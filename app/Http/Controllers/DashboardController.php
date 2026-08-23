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

        $monthInput = $request->input('month', now()->format('Y-m'));
        try {
            $selectedMonth = Carbon::createFromFormat('Y-m', $monthInput);
        } catch (\Exception $e) {
            try {
                $selectedMonth = Carbon::parse($monthInput);
            } catch (\Exception $e2) {
                $selectedMonth = now();
            }
        }
        $selectedMonth = $selectedMonth->startOfMonth();

        $start = $selectedMonth->copy()->startOfMonth();
        $end = $selectedMonth->copy()->endOfMonth();
        
        $income = $this->calculateIncome($branchId, $start, $end, $isAllBranches);
        $expense = $this->calculateExpense($branchId, $start, $end, $isAllBranches);
        
        // Monthly trend for last 6 months ending in selected month
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
                'profit' => (float) ($monthIncome - $monthExpense)
            ];
        })->toArray();

        // Expense by category for donut chart
        $expenseByCategory = $this->getExpenseByCategory($branchId, $start, $end, $isAllBranches);

        // Current-month counts
        $invoiceCount = $isAllBranches ? Invoice::whereBetween('date', [$start, $end])->count() : Invoice::where('branch_id', $branchId)->whereBetween('date', [$start, $end])->count();
        $customerCount = $isAllBranches ? Customer::count() : Customer::where('branch_id', $branchId)->count();
        $orderCount = $isAllBranches ? Order::whereBetween('date', [$start, $end])->count() : Order::where('branch_id', $branchId)->whereBetween('date', [$start, $end])->count();
        $materialCount = $isAllBranches ? Material::count() : Material::where('branch_id', $branchId)->count();

        // Recent data
        $recentOrders = $isAllBranches ? Order::with('customer')->latest('date')->take(5)->get() : Order::with('customer')->where('branch_id', $branchId)->latest('date')->take(5)->get();
        $recentInvoices = $isAllBranches ? Invoice::with('customer')->latest('date')->take(5)->get() : Invoice::with('customer')->where('branch_id', $branchId)->latest('date')->take(5)->get();
        $recentExpenses = $isAllBranches ? Expense::latest('date')->take(5)->get() : Expense::where('branch_id', $branchId)->latest('date')->take(5)->get();

        return view('dashboard.index', compact('monthly', 'expenseByCategory', 'recentOrders', 'recentInvoices', 'recentExpenses') + [
            'branches' => $this->branches(),
            'branchId' => $branchId,
            'selectedMonth' => $selectedMonth,
            'periodLabel' => $selectedMonth->translatedFormat('F Y'),
            'income' => (float) $income,
            'expense' => (float) $expense,
            'profit' => (float) ($income - $expense),
            'totalSales' => (float) $income,
            'totalExpense' => (float) $expense,
            'invoiceCount' => $invoiceCount,
            'customerCount' => $customerCount,
            'orderCount' => $orderCount,
            'materialCount' => $materialCount,
            'lowMaterials' => $isAllBranches ? Material::whereColumn('stock', '<=', 'minimum_stock')->orderBy('stock')->get() : Material::where('branch_id', $branchId)->whereColumn('stock', '<=', 'minimum_stock')->orderBy('stock')->get(),
        ]);
    }

    private function calculateIncome($branchId, Carbon $start, ?Carbon $end, bool $isAllBranches): float
    {
        $query = Invoice::where('status', 'paid')->whereBetween('date', [$start, $end ?? $start]);
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
}
