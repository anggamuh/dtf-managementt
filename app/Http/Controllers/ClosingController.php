<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Branch;
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

        return Pdf::loadView(
            'closing.export-pdf',
            $data + compact(
                'month',
                'year',
                'branch'
            )
        )
            ->setPaper('a4', 'landscape')
            ->download(
                "closing-{$year}-{$month}.pdf"
            );
    }

    /**
     * Export Excel / CSV.
     */
    public function exportExcel(Request $r)
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

        return response()->streamDownload(
            function () use (
                $data,
                $month,
                $year,
                $branch
            ) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                /*
                 * BOM UTF-8 agar Excel membaca karakter
                 * Indonesia dengan benar.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                /*
                 * Header.
                 */
                fputcsv(
                    $handle,
                    ['CLOSING REPORT'],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        "Cabang: {$branch->name}",
                        'Periode: '
                        .Carbon::create(
                            $year,
                            $month,
                            1
                        )->translatedFormat('F Y'),
                    ],
                    ';'
                );

                fputcsv(
                    $handle,
                    [],
                    ';'
                );

                /*
                 * Ringkasan.
                 */
                fputcsv(
                    $handle,
                    ['RINGKASAN'],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        'Total Penjualan',
                        'Total Pengeluaran',
                        'Laba Bersih',
                        'HPP',
                    ],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        $data['totalIncome'] ?? 0,
                        $data['totalExpense'] ?? 0,
                        $data['profit'] ?? 0,
                        $data['closing']->hpp ?? 0,
                    ],
                    ';'
                );

                fputcsv(
                    $handle,
                    [],
                    ';'
                );

                /*
                 * Detail pemasukan.
                 */
                fputcsv(
                    $handle,
                    ['DETAIL PEMASUKAN'],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        'Invoice',
                        'Customer',
                        'Tanggal',
                        'Total',
                        'Status',
                    ],
                    ';'
                );

                if (
                    isset($data['invoices'])
                    && $data['invoices']->isNotEmpty()
                ) {
                    foreach (
                        $data['invoices'] as $invoice
                    ) {
                        fputcsv(
                            $handle,
                            [
                                $invoice->invoice_number,
                                $invoice->customer->name ?? 'N/A',
                                $invoice->date->format('d/m/Y'),
                                $invoice->total,
                                $invoice->status,
                            ],
                            ';'
                        );
                    }
                }

                fputcsv(
                    $handle,
                    [],
                    ';'
                );

                /*
                 * Detail pengeluaran.
                 */
                fputcsv(
                    $handle,
                    ['DETAIL PENGELUARAN'],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        'Tanggal',
                        'Kategori',
                        'Deskripsi',
                        'Jumlah',
                        'Metode',
                    ],
                    ';'
                );

                if (
                    isset($data['expenses'])
                    && $data['expenses']->isNotEmpty()
                ) {
                    foreach (
                        $data['expenses'] as $expense
                    ) {
                        fputcsv(
                            $handle,
                            [
                                $expense->date->format('d/m/Y'),
                                $expense->category,
                                $expense->material?->display_name ?? $expense->description,
                                $expense->amount,
                                $expense->payment_method,
                            ],
                            ';'
                        );
                    }
                }

                fputcsv(
                    $handle,
                    [],
                    ';'
                );

                /*
                 * Detail material.
                 */
                fputcsv(
                    $handle,
                    ['DETAIL MATERIAL & STOK'],
                    ';'
                );

                fputcsv(
                    $handle,
                    [
                        'Material',
                        'Mesin',
                        'Satuan',
                        'Stok Awal',
                        'Pembelian',
                        'Pemakaian',
                        'Stok Akhir',
                        'HPP',
                    ],
                    ';'
                );

                if (
                    isset($data['materialRows'])
                    && $data['materialRows']->isNotEmpty()
                ) {
                    foreach (
                        $data['materialRows'] as $material
                    ) {
                        fputcsv(
                            $handle,
                            [
                                $material['material']->display_name ?? 'N/A',
                                $material['material']->machine?->name ?? '',
                                $material['material']->unit ?? '',
                                $material['stock_awal'] ?? 0,
                                $material['purchase_value'] ?? 0,
                                $material['usage'] ?? 0,
                                $material['stock_akhir'] ?? 0,
                                $material['material_cost'] ?? 0,
                            ],
                            ';'
                        );
                    }
                }

                fclose($handle);
            },
            "closing-{$year}-{$month}.csv"
        );
    }
}
