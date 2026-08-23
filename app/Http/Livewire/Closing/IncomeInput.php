<?php

namespace App\Http\Livewire\Closing;

use Livewire\Component;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class IncomeInput extends Component
{
    public $rows = [];
    public $pasteData = '';
    public $branchId;
    public $month;
    public $year;

    protected $listeners = ['refreshIncome' => 'refreshComponent'];

    public function mount($branchId, $month, $year)
    {
        $this->branchId = $branchId;
        $this->month = $month;
        $this->year = $year;
        $this->rows = [
            ['date' => '', 'customer' => '', 'product' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']
        ];
    }

    public function pasteRows()
    {
        $lines = explode("\n", trim($this->pasteData));
        $this->rows = [];

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));
            if (count($cols) >= 6) {
                $this->rows[] = [
                    'date' => $cols[0] ?? '',
                    'customer' => $cols[1] ?? '',
                    'product' => $cols[2] ?? '',
                    'qty' => $cols[3] ?? '',
                    'price' => $cols[4] ?? '',
                    'total' => $cols[5] ?? '',
                    'note' => $cols[6] ?? '',
                ];
            }
        }

        if (empty($this->rows)) {
            $this->rows = [['date' => '', 'customer' => '', 'product' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']];
        }

        $this->pasteData = '';
    }

    public function addRow()
    {
        $this->rows[] = ['date' => '', 'customer' => '', 'product' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => ''];
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
            'rows.*.customer' => 'required|string',
            'rows.*.product' => 'required|string',
            'rows.*.qty' => 'required|numeric',
            'rows.*.price' => 'required|numeric',
            'rows.*.total' => 'required|numeric',
        ]);

        foreach ($this->rows as $index => $row) {
            $customer = Customer::firstOrCreate(
                ['branch_id' => $this->branchId, 'name' => $row['customer']],
                ['branch_id' => $this->branchId, 'name' => $row['customer']]
            );

            $product = Product::firstOrCreate(
                ['branch_id' => $this->branchId, 'name' => $row['product']],
                ['branch_id' => $this->branchId, 'name' => $row['product'], 'price' => $row['price']]
            );

            $invoiceNumber = 'INV-' . $this->branchId . '-' . now()->format('YmdHis') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'branch_id' => $this->branchId,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'date' => $row['date'],
                'subtotal' => $row['total'],
                'discount' => 0,
                'shipping' => 0,
                'total' => $row['total'],
                'paid' => $row['total'],
                'remaining' => 0,
                'status' => 'paid',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'qty' => $row['qty'],
                'price' => $row['price'],
                'subtotal' => $row['total'],
            ]);
        }

        $this->dispatch('refreshIncome');
        $this->dispatch('refreshSummary');
        session()->flash('message', 'Data pendapatan berhasil disimpan!');
    }

    public function refreshComponent()
    {
        $this->rows = [
            ['date' => '', 'customer' => '', 'product' => '', 'qty' => '', 'price' => '', 'total' => '', 'note' => '']
        ];
    }

    public function render()
    {
        return view('livewire.closing.income-input');
    }
}
