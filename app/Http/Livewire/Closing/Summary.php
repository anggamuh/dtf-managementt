<?php

namespace App\Http\Livewire\Closing;

use App\Models\Closing;
use App\Models\Invoice;
use App\Models\Expense;
use App\Services\ClosingService;
use Livewire\Component;

class Summary extends Component
{
    public $branchId;
    public $month;
    public $year;
    public $totalIncome = 0;
    public $totalExpense = 0;
    public $profit = 0;
    public $hpp = 0;
    public $closing = null;
    public $incomeByCustomer = [];
    public $expenseByCategory = [];
    public $closingGenerated = false;

    protected $listeners = ['refreshSummary' => 'loadData'];

    public function mount($branchId, $month, $year)
    {
        $this->branchId = $branchId;
        $this->month = $month;
        $this->year = $year;
        $this->loadData();
    }

    public function loadData()
    {
        $startDate = "{$this->year}-" . str_pad($this->month, 2, '0', STR_PAD_LEFT) . "-01";
        $endDate = date("{$this->year}-" . str_pad($this->month, 2, '0', STR_PAD_LEFT) . "-t");

        $this->totalIncome = Invoice::where('branch_id', $this->branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('total');

        // Expense = ALL non-material expenses (kategori "Bahan Baku" DIKECUALIKAN)
        $this->totalExpense = Expense::where('branch_id', $this->branchId)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $this->profit = $this->totalIncome - $this->totalExpense;

        $this->incomeByCustomer = Invoice::where('branch_id', $this->branchId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('customer')
            ->get()
            ->groupBy('customer.name')
            ->map(fn ($invoices) => $invoices->sum('total'))
            ->sortByDesc(fn ($total) => $total)
            ->take(10);

        $this->expenseByCategory = Expense::where('branch_id', $this->branchId)
            ->where('category', '!=', 'Bahan Baku')
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('category')
            ->map(fn ($expenses) => $expenses->sum('amount'))
            ->sortByDesc(fn ($total) => $total)
            ->take(10);

        $this->closing = Closing::where('branch_id', $this->branchId)
            ->where('month', $this->month)
            ->where('year', $this->year)
            ->first();
    }

    public function generateClosing()
    {
        $service = app(ClosingService::class);
        $this->closing = $service->generate($this->branchId, $this->month, $this->year);
        $this->closingGenerated = true;
        $this->loadData();
        session()->flash('message', 'Closing berhasil digenerate!');
    }

    public function render()
    {
        return view('livewire.closing.summary');
    }
}
