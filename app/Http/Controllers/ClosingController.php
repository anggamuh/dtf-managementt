<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Closing;
use App\Services\ClosingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ClosingController extends Controller
{
    use ResolvesBranch;

    protected ClosingService $service;

    public function __construct(ClosingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $r)
    {
        $branchId = $this->branchId($r);
        $month = (int) $r->input('month', now()->month);
        $year = (int) $r->input('year', now()->year);

        $closing = Closing::when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $data = $this->service->getData($branchId, $month, $year, $closing);

        $closings = Closing::when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(15)
            ->withQueryString();

        return view('closing.index', $data + compact(
            'branchId', 'month', 'year', 'closing', 'closings'
        ) + [
            'branches' => $this->branches(),
        ]);
    }

    public function generate(Request $r)
    {
        $branchId = $this->branchId($r);

        $data = $r->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2100',
        ]);

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk melakukan generate closing.');

        $existing = Closing::where('branch_id', $branchId)
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->first();

        abort_if($existing?->is_locked, 422, 'Closing yang sudah dikunci tidak dapat digenerate ulang.');

        $this->service->generate($branchId, $data['month'], $data['year']);

        return back()->with('message', 'Closing berhasil digenerate.');
    }

    public function updateSettings(Request $r)
    {
        $branchId = $this->branchId($r);

        $d = $r->validate([
            'month'              => 'required|integer|between:1,12',
            'year'               => 'required|integer|min:2020|max:2100',
            'saldo_tahanan'      => 'nullable|numeric',
            'saldo_realtime'     => 'nullable|numeric',
            'gaji_karyawan'      => 'nullable|numeric',
            'operasional'        => 'nullable|numeric',
            'lain_lain'          => 'nullable|numeric',
            'teknisi_mesin'      => 'nullable|numeric',
        ]);

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk mengubah setting closing.');

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk menyimpan stock akhir.');

        $closing = Closing::where('branch_id', $branchId)
            ->where('month', $d['month'])
            ->where('year', $d['year'])
            ->first();

        abort_if($closing?->is_locked, 422, 'Closing yang terkunci tidak dapat diubah.');

        if (! $closing) {
            $closing = $this->service->generate($branchId, $d['month'], $d['year']);
        }

        $start = Carbon::create($d['year'], $d['month'], 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $closing->update([
            'saldo_tahanan'  => $d['saldo_tahanan'] ?? 0,
            'saldo_realtime' => $d['saldo_realtime'] ?? 0,
            'gaji_karyawan'  => $d['gaji_karyawan'] ?? 0,
            'operasional'    => $d['operasional'] ?? 0,
            'lain_lain'      => $d['lain_lain'] ?? 0,
            'teknisi_mesin'  => $d['teknisi_mesin'] ?? 0,
        ]);

        $this->service->calculateHpp($closing, $start, $end);

        return back()->with('message', 'Data closing berhasil disimpan.');
    }

    public function updateStockAkhir(Request $r)
    {
        $branchId = $this->branchId($r);

        $d = $r->validate([
            'month'              => 'required|integer|between:1,12',
            'year'               => 'required|integer|min:2020|max:2100',
            'stock_akhir'        => 'required|array',
            'stock_akhir.*'      => 'required|numeric|min:0',
        ]);

        $closing = Closing::where('branch_id', $branchId)
            ->where('month', $d['month'])
            ->where('year', $d['year'])
            ->first();

        abort_if(! $closing, 422, 'Closing belum digenerate.');
        abort_if($closing->is_locked, 422, 'Closing yang terkunci tidak dapat diubah.');

        $closing->loadMissing('materials');

        foreach ($d['stock_akhir'] as $materialId => $stockAkhir) {
            $component = $closing->materials()
                ->where('id', $materialId)
                ->first();

            if ($component) {
                $component->update(['stock_akhir' => (float) $stockAkhir]);
            }
        }

        $start = Carbon::create($d['year'], $d['month'], 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $this->service->calculateHpp($closing, $start, $end);

        return back()->with('message', 'Stock akhir material berhasil disimpan.');
    }

    public function lock(Request $r)
    {
        $branchId = $this->branchId($r);

        $data = $r->validate([
            'month' => 'required|integer',
            'year'  => 'required|integer',
        ]);

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk mengunci closing.');

        $closing = Closing::where('branch_id', $branchId)
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->first();

        abort_if(! $closing, 422, 'Closing belum digenerate.');
        abort_if($closing->is_locked, 422, 'Closing sudah dikunci.');

        $closing->lock();

        return back()->with('message', 'Closing berhasil dikunci.');
    }

    public function destroy(Request $r, Closing $closing)
    {
        abort_if($closing->is_locked, 422, 'Closing yang terkunci tidak dapat dihapus.');

        $branchId = $this->branchId($r);
        abort_unless($closing->branch_id === $branchId, 403);

        $closing->materials()->delete();
        $closing->delete();

        return redirect()->route('closing.index', ['branch_id' => $branchId])
            ->with('message', 'Closing berhasil dihapus.');
    }

    public function show(Closing $closing)
    {
        abort_unless(
            $this->canAccessAllBranches() || $closing->branch_id === auth()->user()->branch_id,
            403
        );

        $branchId = $closing->branch_id;
        $month = (int) $closing->month;
        $year = (int) $closing->year;

        $closing->loadMissing('materials.material');

        // Ensure HPP is calculated automatically when viewing if missing
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();
        if (empty($closing->hpp) || empty($closing->hpp_per_meter)) {
            $this->service->calculateHpp($closing, $start, $end);
            $closing->refresh();
        }

        $data = $this->service->getData($branchId, $month, $year, $closing);

        $branch = \App\Models\Branch::find($branchId);

        return view('closing.show', $data + compact(
            'branchId', 'month', 'year', 'closing', 'branch'
        ) + [
            'branches' => $this->branches(),
        ]);
    }

    public function exportPdf(Request $r)
    {
        $branchId = $this->branchId($r);
        $month = (int) $r->input('month', now()->month);
        $year = (int) $r->input('year', now()->year);

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk melakukan export.');

        abort_if($branchId === 0, 422, 'Pilih cabang terlebih dahulu untuk melakukan export.');

        $closing = Closing::where('branch_id', $branchId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

                $data = $this->service->getData($branchId, $month, $year, $closing);

                $branch = \App\Models\Branch::find($branchId);

                $data = $this->service->getData($branchId, $month, $year, $closing);

                $branch = \App\Models\Branch::find($branchId);

                return Pdf::loadView('closing.export-pdf', $data + compact(
                        'month', 'year', 'branch'
                ))->setPaper('a4', 'landscape')
                    ->download("closing-{$year}-{$month}.pdf");
    }

    public function exportExcel(Request $r)
    {
        $branchId = $this->branchId($r);
        $month = (int) $r->input('month', now()->month);
        $year = (int) $r->input('year', now()->year);

        $closing = Closing::where('branch_id', $branchId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $data = $this->service->getData($branchId, $month, $year, $closing);

        $branch = \App\Models\Branch::find($branchId);

        return response()->streamDownload(function () use ($data, $month, $year, $branch) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fputs($handle, "\xEF\xBB\xBF");
            
            // Header
            fputcsv($handle, ['CLOSING REPORT'], ';');
            fputcsv($handle, ["Cabang: {$branch->name}", "Periode: " . Carbon::create($year, $month, 1)->translatedFormat('F Y')], ';');
            fputcsv($handle, [], ';');
            
            // Summary Section
            fputcsv($handle, ['RINGKASAN'], ';');
            fputcsv($handle, ['Total Penjualan', 'Total Pengeluaran', 'Laba Bersih', 'HPP'], ';');
            fputcsv($handle, [
                $data['totalIncome'] ?? 0,
                $data['totalExpense'] ?? 0,
                $data['profit'] ?? 0,
                $closing?->hpp ?? 0
            ], ';');
            fputcsv($handle, [], ';');
            
            // Income Details
            fputcsv($handle, ['DETAIL PEMASUKAN'], ';');
            fputcsv($handle, ['Invoice', 'Customer', 'Tanggal', 'Total', 'Status'], ';');
            if (isset($data['invoices']) && $data['invoices']->isNotEmpty()) {
                foreach ($data['invoices'] as $invoice) {
                    fputcsv($handle, [
                        $invoice->invoice_number,
                        $invoice->customer->name ?? 'N/A',
                        $invoice->date->format('d/m/Y'),
                        $invoice->total,
                        $invoice->status
                    ], ';');
                }
            }
            fputcsv($handle, [], ';');
            
            // Expense Details
            fputcsv($handle, ['DETAIL PENGELUARAN'], ';');
            fputcsv($handle, ['Tanggal', 'Kategori', 'Deskripsi', 'Jumlah', 'Metode'], ';');
            if (isset($data['expenses']) && $data['expenses']->isNotEmpty()) {
                foreach ($data['expenses'] as $expense) {
                    fputcsv($handle, [
                        $expense->date->format('d/m/Y'),
                        $expense->category,
                        $expense->description,
                        $expense->amount,
                        $expense->payment_method
                    ], ';');
                }
            }
            fputcsv($handle, [], ';');
            
            // Material Details
            fputcsv($handle, ['DETAIL MATERIAL & STOK'], ';');
            fputcsv($handle, ['Material', 'Satuan', 'Stok Awal', 'Pembelian', 'Pemakaian', 'Stok Akhir', 'HPP'], ';');
            if (isset($data['materialRows']) && $data['materialRows']->isNotEmpty()) {
                foreach ($data['materialRows'] as $material) {
                    fputcsv($handle, [
                        $material['material']->name ?? 'N/A',
                        $material['material']->unit ?? '',
                        $material['stock_awal'] ?? 0,
                        $material['purchase_value'] ?? 0,
                        $material['usage'] ?? 0,
                        $material['stock_akhir'] ?? 0,
                        $material['material_cost'] ?? 0
                    ], ';');
                }
            }
            
            fclose($handle);
        }, "closing-{$year}-{$month}.csv");
    }
}
