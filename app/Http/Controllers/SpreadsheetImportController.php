<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Services\SpreadsheetPasteImporter;
use Illuminate\Http\Request;

class SpreadsheetImportController extends Controller
{
    use ResolvesBranch;

    public function incomeForm(Request $request)
    {
        return view('imports.paste', ['type' => 'income', 'branchId' => $this->branchId($request), 'branches' => $this->branches()]);
    }

    public function expenseForm(Request $request)
    {
        $branchId = $this->branchId($request);

        return view('imports.paste', [
            'type' => 'expense',
            'branchId' => $branchId,
            'branches' => $this->branches(),
        ]);
    }

    public function income(Request $request, SpreadsheetPasteImporter $importer)
    {
        $data = $request->validate(['branch_id' => 'nullable|exists:branches,id', 'paste_data' => 'required|string']);
        $count = $importer->importIncome($data['paste_data'], $this->branchId($request));

        return redirect()->route('orders.index', ['branch_id' => $this->branchId($request)])
            ->with('message', "{$count} pesanan berhasil diimpor dari spreadsheet.");
    }

    public function expenses(Request $request, SpreadsheetPasteImporter $importer)
    {
        $branchId = $this->branchId($request);
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'paste_data' => 'required|string',
        ]);
        $count = $importer->importExpenses($data['paste_data'], $branchId);
        $created = $importer->createdMaterialNames();
        $message = "{$count} pengeluaran berhasil diimpor dari spreadsheet.";
        if ($created !== []) {
            $message .= ' Material baru: '.implode(', ', $created).'.';
        }

        return redirect()->route('expenses.index', ['branch_id' => $branchId])
            ->with('message', $message);
    }
}
