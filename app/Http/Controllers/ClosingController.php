<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Branch;
use App\Models\Closing;
use App\Models\Order;
use App\Services\ClosingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ClosingController extends Controller
{
    use ResolvesBranch;

    protected ClosingService $service;

    public function __construct(ClosingService $service)
    {
        $this->service = $service;
    }

    /**
     * Halaman utama Closing.
     */
    public function index(Request $r)
    {
        $branchId = $this->branchId($r);

        $month = (int) $r->input('month', now()->month);
        $year = (int) $r->input('year', now()->year);

        /*
         * Periode closing.
         *
         * Jika start_date / end_date tidak dikirim,
         * otomatis menggunakan tanggal awal dan akhir bulan.
         */
        $startDate = $r->input(
            'start_date',
            Carbon::create($year, $month, 1)
                ->startOfMonth()
                ->toDateString()
        );

        $endDate = $r->input(
            'end_date',
            Carbon::create($year, $month, 1)
                ->endOfMonth()
                ->toDateString()
        );

        abort_if(
            $endDate < $startDate,
            422,
            'Tanggal akhir harus sama atau setelah tanggal mulai.'
        );

        /*
         * Ambil closing bulan yang sedang dipilih.
         */
        $closing = Closing::query()
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        /*
         * Ambil seluruh data closing.
         *
         * startDate dan endDate dikirim supaya
         * perhitungan mengikuti periode yang sedang dipilih.
         */
        $data = $this->service->getData(
            $branchId,
            $month,
            $year,
            $closing,
            Carbon::parse($startDate),
            Carbon::parse($endDate)
        );

        /*
         * Riwayat closing.
         */
        $closings = Closing::query()
            ->when(
                $branchId !== 0,
                fn ($q) => $q->where('branch_id', $branchId)
            )
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(15)
            ->withQueryString();

        return view(
            'closing.index',
            $data + compact(
                'branchId',
                'month',
                'year',
                'startDate',
                'endDate',
                'closing',
                'closings'
            ) + [
                'branches' => $this->branches(),
            ]
        );
    }

    /**
     * Generate closing.
     */
    public function generate(Request $r)
    {
        $branchId = $this->branchId($r);

        $data = $r->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk melakukan generate closing.'
        );

        $existing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->first();

        abort_if(
            $existing?->is_locked,
            422,
            'Closing yang sudah dikunci tidak dapat digenerate ulang.'
        );

        $this->service->generate(
            $branchId,
            $data['month'],
            $data['year'],
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date'])
        );

        return back()->with(
            'message',
            'Closing berhasil digenerate.'
        );
    }

    /**
     * Simpan pengaturan closing.
     */
    public function updateSettings(Request $r)
    {
        $branchId = $this->branchId($r);

        $d = $r->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2100',

            'saldo_tahanan' => 'nullable|numeric',
            'saldo_realtime' => 'nullable|numeric',
            'gaji_karyawan' => 'nullable|numeric',
            'operasional' => 'nullable|numeric',
            'lain_lain' => 'nullable|numeric',
            'teknisi_mesin' => 'nullable|numeric',
        ]);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk mengubah setting closing.'
        );

        $closing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $d['month'])
            ->where('year', $d['year'])
            ->first();

        abort_if(
            $closing?->is_locked,
            422,
            'Closing yang terkunci tidak dapat diubah.'
        );

        /*
         * Jika closing belum ada, generate terlebih dahulu.
         */
        if (! $closing) {
            $closing = $this->service->generate(
                $branchId,
                $d['month'],
                $d['year']
            );
        }

        $start = $closing->period_start?->copy()
            ?? Carbon::create(
                $d['year'],
                $d['month'],
                1
            )->startOfMonth();

        $end = $closing->period_end?->copy()
            ?? (clone $start)->endOfMonth();

        $closing->update([
            'saldo_tahanan' => $d['saldo_tahanan'] ?? 0,
            'saldo_realtime' => $d['saldo_realtime'] ?? 0,
            'gaji_karyawan' => $d['gaji_karyawan'] ?? 0,
            'operasional' => $d['operasional'] ?? 0,
            'lain_lain' => $d['lain_lain'] ?? 0,
            'teknisi_mesin' => $d['teknisi_mesin'] ?? 0,
        ]);

        /*
         * Hitung ulang HPP setelah setting berubah.
         */
        $this->service->calculateHpp(
            $closing,
            $start,
            $end
        );

        return back()->with(
            'message',
            'Data closing berhasil disimpan.'
        );
    }

    /**
     * Update stok akhir dan harga manual material.
     *
     * Harga komponen disimpan per closing supaya harga SO dan harga
     * perhitungan HPP material menggunakan angka manual yang sama.
     */
    public function updateStockAkhir(Request $r)
    {
        $branchId = $this->branchId($r);

        $d = $r->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2100',

            'stock_akhir' => 'nullable|array',
            'stock_akhir.*' => 'nullable|numeric|min:0',

            'harga_komponen' => 'nullable|array',
            'harga_komponen.*' => 'nullable|numeric|min:0',
        ]);

        abort_if(
            empty($d['stock_akhir']) && empty($d['harga_komponen']),
            422,
            'Tidak ada data material yang disimpan.'
        );

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk menyimpan stock akhir.'
        );

        $closing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $d['month'])
            ->where('year', $d['year'])
            ->first();

        abort_if(
            ! $closing,
            422,
            'Closing belum digenerate.'
        );

        abort_if(
            $closing->is_locked,
            422,
            'Closing yang terkunci tidak dapat diubah.'
        );

        $closing->loadMissing('materials');

        $componentIds = collect(array_keys($d['stock_akhir'] ?? []))
            ->merge(array_keys($d['harga_komponen'] ?? []))
            ->unique();

        foreach ($componentIds as $componentId) {
            $component = $closing->materials()
                ->where('id', $componentId)
                ->first();

            if ($component) {
                $updates = [];

                if (array_key_exists($componentId, $d['stock_akhir'] ?? [])) {
                    $updates['stock_akhir'] = (float) $d['stock_akhir'][$componentId];
                }

                if (array_key_exists($componentId, $d['harga_komponen'] ?? [])) {
                    $updates['harga_komponen'] = (float) $d['harga_komponen'][$componentId];
                }

                if ($updates !== []) {
                    $component->update($updates);
                }
            }
        }

        $start = $closing->period_start?->copy()
            ?? Carbon::create(
                $d['year'],
                $d['month'],
                1
            )->startOfMonth();

        $end = $closing->period_end?->copy()
            ?? (clone $start)->endOfMonth();

        /*
         * Hitung ulang HPP dan nilai SO setelah stok/harga berubah.
         */
        $this->service->calculateHpp(
            $closing,
            $start,
            $end
        );

        return back()->with(
            'message',
            'Stock akhir dan harga manual material berhasil disimpan.'
        );
    }

    /**
     * Kunci closing.
     */
    public function lock(Request $r)
    {
        $branchId = $this->branchId($r);

        $data = $r->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk mengunci closing.'
        );

        $closing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $data['month'])
            ->where('year', $data['year'])
            ->first();

        abort_if(
            ! $closing,
            422,
            'Closing belum digenerate.'
        );

        abort_if(
            $closing->is_locked,
            422,
            'Closing sudah dikunci.'
        );

        $closing->lock();

        return back()->with(
            'message',
            'Closing berhasil dikunci.'
        );
    }

    /**
     * Hapus closing.
     */
    public function destroy(Request $r, Closing $closing)
    {
        abort_if(
            $closing->is_locked,
            422,
            'Closing yang terkunci tidak dapat dihapus.'
        );

        $branchId = $this->branchId($r);

        abort_unless(
            $this->canAccessAllBranches()
                || $closing->branch_id === $branchId,
            403
        );

        $closing->materials()->delete();
        $closing->delete();

        return redirect()
            ->route('closing.index', [
                'branch_id' => $branchId,
            ])
            ->with(
                'message',
                'Closing berhasil dihapus.'
            );
    }

    /**
     * Detail closing.
     */
    public function show(Closing $closing)
    {
        abort_unless(
            $this->canAccessAllBranches()
                || $closing->branch_id === auth()->user()->branch_id,
            403
        );

        $branchId = $closing->branch_id;
        $month = (int) $closing->month;
        $year = (int) $closing->year;

        $closing->loadMissing('materials.material.machine');

        $start = $closing->period_start?->copy()
            ?? Carbon::create(
                $year,
                $month,
                1
            )->startOfMonth();

        $end = $closing->period_end?->copy()
            ?? (clone $start)->endOfMonth();

        /*
         * Pastikan HPP tersedia ketika membuka detail.
         */
        if (
            empty($closing->hpp)
            || empty($closing->hpp_per_meter)
        ) {
            $this->service->calculateHpp(
                $closing,
                $start,
                $end
            );

            $closing->refresh();
        }

        $data = $this->service->getData(
            $branchId,
            $month,
            $year,
            $closing
        );

        $branch = Branch::find($branchId);

        return view(
            'closing.show',
            $data + compact(
                'branchId',
                'month',
                'year',
                'closing',
                'branch'
            ) + [
                'branches' => $this->branches(),
            ]
        );
    }

    /**
     * Export PDF.
     */
    public function exportPdf(Request $r)
    {
        $branchId = $this->branchId($r);

        $month = (int) $r->input(
            'month',
            now()->month
        );

        $year = (int) $r->input(
            'year',
            now()->year
        );

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk melakukan export.'
        );

        $closing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        $data = $this->service->getData(
            $branchId,
            $month,
            $year,
            $closing
        );

        $branch = Branch::find($branchId);

        $orders = Order::with(['customer', 'product'])
            ->where('branch_id', $branchId)
            ->whereBetween('date', [
                $closing->period_start ?? Carbon::create($year, $month, 1)->startOfMonth(),
                $closing->period_end ?? Carbon::create($year, $month, 1)->endOfMonth(),
            ])
            ->orderBy('date')
            ->get();

        return Pdf::loadView(
            'closing.export-pdf',
            $data + compact(
                'month',
                'year',
                'branch',
                'closing',
                'orders'
            )
        )
            ->setPaper('a4', 'landscape')
            ->download(
                "closing-{$year}-{$month}.pdf"
            );
    }

    /**
     * Export Excel dengan susunan yang sama seperti halaman detail closing.
     */
    public function exportExcel(Request $r)
    {
        $branchId = $this->branchId($r);

        $month = (int) $r->input('month', now()->month);
        $year = (int) $r->input('year', now()->year);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu untuk melakukan export.'
        );

        $closing = Closing::query()
            ->where('branch_id', $branchId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        abort_if(
            ! $closing,
            422,
            'Closing belum digenerate untuk periode ini.'
        );

        $data = $this->service->getData($branchId, $month, $year, $closing);
        $branch = Branch::find($branchId);
        $period = Carbon::create($year, $month, 1)->translatedFormat('F Y');
        $invoices = $data['invoices'] ?? collect();
        $expenses = $data['expenses'] ?? collect();
        $materialRows = $data['materialRows'] ?? collect();
        $hpp = $data['hpp'] ?? ['hasilCetak' => 0, 'allRows' => collect(), 'totalRpMeter' => 0];
        $soTotal = $materialRows->sum(
            fn ($material) => (float) ($material['stock_akhir'] ?? 0)
                * (float) ($material['unit_cost'] ?? 0)
        );

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Closing '.$month.'-'.$year);
        $sheet->getSheetView()->setZoomScale(100);
        $sheet->freezePane('A5');

        $row = 1;

        /* ---------- Letterhead ---------- */
        $sheet->setCellValue("A{$row}", $branch->name);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(15);
        $row++;

        $sheet->setCellValue("A{$row}", "Laporan Closing Bulanan — {$period}");
        $sheet->getStyle("A{$row}")->getFont()->setSize(10)->getColor()->setARGB('FF64748B');
        $row++;

        $sheet->setCellValue("A{$row}", 'Dicetak '.now()->translatedFormat('d M Y, H:i'));
        $sheet->getStyle("A{$row}")->getFont()->setSize(9)->getColor()->setARGB('FF94A3B8');
        $row += 2;

        /* ---------- Ringkasan ---------- */
        $row = $this->excelSectionTitle($sheet, $row, 'RINGKASAN CLOSING', 4);

        $summary = [
            'Hasil Cetak' => (float) ($hpp['hasilCetak'] ?? 0),
            'Total HPP (info)' => $closing->hpp ?? 0,
            'HPP / Meter' => $closing->hpp_per_meter ?? 0,
            'Laba / Rugi' => $data['profit'] ?? 0,
        ];

        $col = 'A';
        foreach (array_keys($summary) as $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->setSize(9);
            $sheet->getStyle("{$col}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
            $col++;
        }
        $row++;

        $col = 'A';
        foreach ($summary as $label => $value) {
            $cell = "{$col}{$row}";
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(
                $label === 'Hasil Cetak' ? '#,##0.00 "m"' : '"Rp" #,##0'
            );
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(11);
            if ($label === 'Laba / Rugi') {
                $sheet->getStyle($cell)->getFont()->getColor()
                    ->setARGB($value >= 0 ? 'FF059669' : 'FFDC2626');
            }
            $col++;
        }
        $row += 3;

        /* ---------- Detail Pemasukan ---------- */
        $row = $this->excelSectionTitle(
            $sheet,
            $row,
            'PEMASUKAN (INVOICE) - TOTAL Rp '.number_format((float) ($data['totalIncome'] ?? 0), 0, ',', '.'),
            5
        );
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Tanggal', 'Customer', 'No. Invoice', 'Total', 'Status'],
            $invoices->map(fn ($inv) => [
                optional($inv->date)->format('Y-m-d'),
                $inv->customer->name ?? '-',
                $inv->invoice_number ?? '-',
                (float) $inv->total,
                ucfirst($inv->status ?? 'unpaid'),
            ])->all(),
            currencyCols: [3],
        );
        $row += 2;

        /* ---------- Detail Pengeluaran ---------- */
        $row = $this->excelSectionTitle(
            $sheet,
            $row,
            'PENGELUARAN (SEMUA KATEGORI, TERMASUK BAHAN BAKU) - TOTAL Rp '
                .number_format((float) ($data['totalExpense'] ?? 0), 0, ',', '.'),
            5
        );
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Tanggal', 'Kategori', 'Deskripsi', 'Total', 'Keterangan'],
            $expenses->map(fn ($exp) => [
                optional($exp->date)->format('Y-m-d'),
                $exp->category,
                $exp->material?->display_name ?? $exp->description,
                (float) $exp->amount,
                $exp->payment_method ?? '-',
            ])->all(),
            currencyCols: [3],
        );
        $row += 2;

        /* ---------- Saldo & selisih ---------- */
        $row = $this->excelSectionTitle($sheet, $row, 'SALDO & SELISIH', 2);
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Keterangan', 'Nilai'],
            [
                ['Saldo Tahanan', (float) ($data['saldoTahanan'] ?? 0)],
                ['Remaining Material', (float) ($data['remainingMaterial'] ?? 0)],
                ['Saldo Tahanan Sisa', (float) ($data['saldoTahananSisa'] ?? 0)],
                ['Sisa Saldo (Profit + Saldo Tahanan Sisa)', (float) ($data['sisaSaldo'] ?? 0)],
                ['Saldo Realtime', (float) ($data['saldoRealtime'] ?? 0)],
                ['Selisih (Realtime - Sisa Saldo)', (float) ($data['selisih'] ?? 0)],
            ],
            currencyCols: [1],
        );
        $row += 2;

        /* ---------- Stock opname bahan baku ---------- */
        $row = $this->excelSectionTitle(
            $sheet,
            $row,
            'STOCK OPNAME BAHAN BAKU - TOTAL NILAI SO Rp '.number_format($soTotal, 0, ',', '.'),
            4
        );
        $stockOpnameRows = $materialRows->map(fn ($m) => [
            $m['material']->display_name ?? 'N/A',
            number_format((float) ($m['stock_akhir'] ?? 0), 2, '.', '')
                .' '.($m['material']->unit ?? ''),
            (float) ($m['unit_cost'] ?? 0),
            (float) ($m['stock_akhir'] ?? 0) * (float) ($m['unit_cost'] ?? 0),
        ])->all();
        $stockOpnameRows[] = ['Total Stock Opname', null, null, $soTotal];
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Stok Bahan Baku', 'Satuan', 'Harga', 'Total'],
            $stockOpnameRows,
            currencyCols: [2, 3],
        );
        $row += 2;

        /* ---------- Rincian material ---------- */
        $row = $this->excelSectionTitle($sheet, $row, 'RINCIAN MATERIAL', 6);
        $materialExportRows = $materialRows->map(fn ($m) => [
            ($m['material']->display_name ?? 'N/A').' '.($m['material']->unit ?? ''),
            (float) ($m['stock_awal'] ?? 0),
            (float) ($m['unit_cost'] ?? 0),
            (float) ($m['incoming_quantity'] ?? 0),
            (float) ($m['purchase_value'] ?? 0),
            (float) ($m['stock_akhir'] ?? 0),
        ])->all();

        $fixedCosts = [
            'Gaji Karyawan' => (float) ($closing->gaji_karyawan ?? 0),
            'Operasional' => (float) ($closing->operasional ?? 0),
            'Teknisi Mesin' => (float) ($closing->teknisi_mesin ?? 0),
        ];
        foreach ($fixedCosts as $label => $value) {
            if ($value > 0) {
                $materialExportRows[] = [$label, null, null, null, $value, null];
            }
        }
        $materialExportRows[] = [
            'Total',
            null,
            null,
            null,
            (float) $materialRows->sum('purchase_value') + array_sum($fixedCosts),
            null,
        ];

        $row = $this->excelTable(
            $sheet,
            $row,
            ['Nama Komponen', 'Stock Awal', 'Harga Komponen', 'Qty', 'Pembelian', 'Stock Akhir'],
            $materialExportRows,
            currencyCols: [2, 4],
        );
        $row += 2;

        /* ---------- Rincian HPP / meter ---------- */
        $row = $this->excelSectionTitle(
            $sheet,
            $row,
            'RINCIAN HPP / METER - HASIL CETAK '
                .number_format((float) ($hpp['hasilCetak'] ?? 0), 2, ',', '.').' m',
            4
        );
        $hppRows = collect($hpp['allRows'] ?? [])->map(fn ($item) => [
            $item['nama'],
            $item['basis'],
            (float) $item['totalHarga'],
            (float) $item['rpMeter'],
        ])->all();
        $hppRows[] = ['Total HPP / Meter', null, null, (float) ($hpp['totalRpMeter'] ?? 0)];
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Komponen', 'Basis', 'Total Harga', 'Rp / Meter'],
            $hppRows,
            currencyCols: [2, 3],
        );
        $row += 2;

        /* ---------- Data manual closing ---------- */
        $row = $this->excelSectionTitle($sheet, $row, 'DATA MANUAL CLOSING', 2);
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Keterangan', 'Nilai'],
            [
                ['Operasional', (float) ($closing->operasional ?? 0)],
                ['Gaji Karyawan', (float) ($closing->gaji_karyawan ?? 0)],
                ['Teknisi Mesin', (float) ($closing->teknisi_mesin ?? 0)],
                ['Lain-lain', (float) ($closing->lain_lain ?? 0)],
                ['Saldo Tahanan', (float) ($closing->saldo_tahanan ?? 0)],
                ['Saldo Realtime', (float) ($closing->saldo_realtime ?? 0)],
            ],
            currencyCols: [1],
        );
        $row += 2;

        /* ---------- Ringkasan laba/rugi ---------- */
        $row = $this->excelSectionTitle($sheet, $row, 'RINGKASAN LABA/RUGI', 2);
        $row = $this->excelTable(
            $sheet,
            $row,
            ['Keterangan', 'Nilai'],
            [
                ['Total Pemasukan (Invoice)', (float) ($data['totalIncome'] ?? 0)],
                ['Saldo Tahanan', (float) ($data['saldoTahanan'] ?? 0)],
                ['Saldo Realtime', (float) ($data['saldoRealtime'] ?? 0)],
                [
                    'Total Pemasukan + Saldo',
                    (float) ($data['totalIncome'] ?? 0)
                        + (float) ($data['saldoTahanan'] ?? 0)
                        + (float) ($data['saldoRealtime'] ?? 0),
                ],
                ['Total Pengeluaran (semua kategori)', (float) ($data['totalExpense'] ?? 0)],
                ['Remaining Material', (float) ($data['remainingMaterial'] ?? 0)],
                ['HPP / Meter (info)', (float) ($closing->hpp_per_meter ?? 0)],
                ['Total HPP (info, tidak memengaruhi laba)', (float) ($closing->hpp ?? 0)],
                ['Laba/Rugi Bersih (Pemasukan - Pengeluaran)', (float) ($data['profit'] ?? 0)],
            ],
            currencyCols: [1],
        );

        /* ---------- Column widths ---------- */
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(28);

        $filename = "closing-{$branch->name}-{$year}-{$month}.xlsx";
        $filename = str_replace(' ', '-', $filename);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Tulis judul section (baris solid gelap, teks putih, merge sepanjang $span kolom).
     */
    private function excelSectionTitle($sheet, int $row, string $title, int $span): int
    {
        $lastCol = chr(ord('A') + $span - 1);

        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0F172A');
        $sheet->getStyle("A{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);

        return $row + 1;
    }

    /**
     * Tulis header + baris data sebuah tabel, dengan zebra striping dan
     * format currency ("Rp #,##0") pada kolom yang disebut di $currencyCols.
     * Mengembalikan nomor baris berikutnya (setelah tabel selesai).
     */
    private function excelTable($sheet, int $startRow, array $headers, array $rows, array $currencyCols = []): int
    {
        $row = $startRow;
        $colCount = count($headers);
        $lastCol = chr(ord('A') + $colCount - 1);

        // header
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}{$row}", $label);
        }
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
        $row++;

        if (empty($rows)) {
            $sheet->setCellValue("A{$row}", '(Tidak ada data)');
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF94A3B8');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        } else {
            foreach ($rows as $r => $values) {
                $isTotalRow = isset($values[0])
                    && (str_starts_with((string) $values[0], 'Total')
                        || str_starts_with((string) $values[0], 'Laba/Rugi Bersih'));
                foreach ($values as $i => $value) {
                    $col = chr(ord('A') + $i);
                    $sheet->setCellValue("{$col}{$row}", $value);
                    if (in_array($i, $currencyCols, true)) {
                        $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    }
                }
                if ($r % 2 === 1) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }
                if ($isTotalRow) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getTop()
                        ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB('FF0F172A');
                }
                $row++;
            }
        }

        $sheet->getStyle("A{$startRow}:{$lastCol}".($row - 1))
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

        return $row;
    }
}
