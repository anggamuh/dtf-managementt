<?php

namespace App\Http\Livewire\Closing;

use Livewire\Component;
use App\Models\Expense;
use App\Models\Material;
use App\Services\MaterialPurchaseService;
use Illuminate\Support\Facades\DB;

class ExpenseInput extends Component
{
    public $rows = [];
    public $pasteData = '';
    public $branchId;
    public $month;
    public $year;

    protected $listeners = ['refreshExpense' => 'refreshComponent'];

    public function mount($branchId, $month, $year)
    {
        $this->branchId = $branchId;
        $this->month = $month;
        $this->year = $year;
        $this->rows = [
            ['date' => '', 'bon_number' => '', 'category' => '', 'description' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']
        ];
    }

    public function pasteRows()
    {
        $lines = explode("\n", trim($this->pasteData));
        $this->rows = [];

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));
            if (count($cols) >= 7) {
                $this->rows[] = [
                    'date' => $cols[0] ?? '',
                    'bon_number' => $cols[1] ?? '',
                    'category' => $cols[2] ?? '',
                    'description' => $cols[3] ?? '',
                    'qty' => $cols[4] ?? '',
                    'price' => $cols[5] ?? '',
                    'total' => $cols[6] ?? '',
                    'note' => $cols[7] ?? '',
                ];
            }
        }

        if (empty($this->rows)) {
            $this->rows = [['date' => '', 'bon_number' => '', 'category' => '', 'description' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']];
        }

        $this->pasteData = '';
    }

    public function addRow()
    {
        $this->rows[] = ['date' => '', 'bon_number' => '', 'category' => '', 'description' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => ''];
    }

    public function removeRow($index)
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function updatedRows($value, $name)
    {
        preg_match('/rows\.(\d+)\.(qty|price)/', $name, $matches);
        if ($matches) {
            $index = $matches[1];
            $qty = floatval($this->rows[$index]['qty'] ?? 0);
            $price = floatval($this->rows[$index]['price'] ?? 0);
            $this->rows[$index]['total'] = $qty * $price;
        }
    }

    public function save()
    {
        $this->validate([
            'rows.*.date' => 'required|date',
            'rows.*.category' => 'required|string',
            'rows.*.description' => 'required|string',
            'rows.*.total' => 'required|numeric',
            'rows.*.qty' => 'nullable|numeric',
        ]);

        $purchases = app(MaterialPurchaseService::class);

        DB::transaction(function () use ($purchases) {
            foreach ($this->rows as $row) {
                $materialId = null;
                $quantity = null;

                // Jika kategori "Bahan Baku", cari Material berdasarkan deskripsi
                if ($row['category'] === 'Bahan Baku') {
                    $material = Material::where('branch_id', $this->branchId)
                        ->where('name', $row['description'])
                        ->first();

                    // Jika material tidak ditemukan, buat baru
                    if (! $material) {
                        $material = Material::create([
                            'branch_id' => $this->branchId,
                            'name' => $row['description'],
                            'unit' => 'kg',
                            'stock' => 0,
                            'minimum_stock' => 0,
                            'price' => 0,
                        ]);
                    }

                    $materialId = $material->id;
                    $quantity = (float) ($row['qty'] ?? 1);
                }

                $expense = Expense::create([
                    'branch_id' => $this->branchId,
                    'material_id' => $materialId,
                    'date' => $row['date'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'amount' => $row['total'],
                    'quantity' => $quantity,
                    'payment_method' => $row['note'] ?? 'Tunai',
                ]);

                // Sync material purchase untuk "Bahan Baku"
                $purchases->sync($expense);
            }
        });

        $this->dispatch('refreshExpense');
        $this->dispatch('refreshSummary');
        session()->flash('message', 'Data pengeluaran berhasil disimpan!');
    }

    public function refreshComponent()
    {
        $this->rows = [
            ['date' => '', 'bon_number' => '', 'category' => '', 'description' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']
        ];
    }

    public function render()
    {
        return view('livewire.closing.expense-input');
    }
}
