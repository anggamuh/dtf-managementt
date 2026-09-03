<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerPeriodBalance;
use App\Models\Material;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | CABANG
        |--------------------------------------------------------------------------
        */

        $branchId = $this->branchId($request);

        $branches = $this->branches();

        $currentBranch = collect($branches)->first(function ($branch) use ($branchId) {
            return (int) data_get($branch, 'id') === (int) $branchId;
        });

        $branchName = data_get($currentBranch, 'name')
            ?? data_get($currentBranch, 'branch_name')
            ?? 'Cabang';


        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        try {
            $start = $startDateInput
                ? Carbon::parse($startDateInput)->startOfDay()
                : now()->startOfMonth()->startOfDay();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth()->startOfDay();
        }

        try {
            $end = $endDateInput
                ? Carbon::parse($endDateInput)->endOfDay()
                : now()->endOfMonth()->endOfDay();
        } catch (\Throwable $e) {
            $end = now()->endOfMonth()->endOfDay();
        }


        /*
        |--------------------------------------------------------------------------
        | JIKA TANGGAL TERBALIK
        |--------------------------------------------------------------------------
        */

        if ($start->gt($end)) {
            [$start, $end] = [
                $end->copy()->startOfDay(),
                $start->copy()->endOfDay(),
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | SALDO TAHANAN PERMANEN
        |--------------------------------------------------------------------------
        |
        | EPUL  = Rp20.000.000
        | RAPLY = Rp10.000.000
        |
        | Berdasarkan ID cabang agar tidak tergantung nama.
        |
        */

        $saldoTahanan = match ((int) $branchId) {
            1 => 20_000_000,
            2 => 10_000_000,
            default => 0,
        };


        /*
        |--------------------------------------------------------------------------
        | INVOICE
        |--------------------------------------------------------------------------
        |
        | Filter tetap menggunakan kolom `date`
        | karena kolom tersebut memang tersedia.
        |
        */

        $invoices = Invoice::with('customer')
            ->where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | EXPENSE
        |--------------------------------------------------------------------------
        */

        $expenses = Expense::where('branch_id', $branchId)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | TOTAL PEMASUKAN
        |--------------------------------------------------------------------------
        */

        $totalPemasukan = (float) $invoices->sum('total');


        /*
        |--------------------------------------------------------------------------
        | TOTAL PENGELUARAN
        |--------------------------------------------------------------------------
        */

        $totalPengeluaran = (float) $expenses->sum('amount');


        /*
        |--------------------------------------------------------------------------
        | SALDO REALTIME
        |--------------------------------------------------------------------------
        */

        /*
         * Saldo realtime mengikuti rentang tanggal persis yang dipilih.
         * Rentang boleh melewati pergantian bulan maupun tahun.
         */
        $periodBalance = $this->findPeriodBalance(
            $branchId,
            $start,
            $end
        );

        $saldoRealtime = (float) (
            $periodBalance?->saldo_realtime ?? 0
        );

        $saldoRealtimeLocked = (bool) $periodBalance?->locked_at;

        $saldoRealtimeLockedAt = $periodBalance?->locked_at;


        /*
        |--------------------------------------------------------------------------
        | SISA MATERIAL
        |--------------------------------------------------------------------------
        */

        $remainingMaterial = Material::where('branch_id', $branchId)
            ->get()
            ->sum(function ($material) {
                return (float) $material->stock
                    * (float) $material->price;
            });


        /*
        |--------------------------------------------------------------------------
        | SUSUN MUTASI
        |--------------------------------------------------------------------------
        */

        $entries = collect();


        /*
        |--------------------------------------------------------------------------
        | INVOICE
        |--------------------------------------------------------------------------
        |
        | Tanggal yang ditampilkan di Buku Besar mengikuti
        | tanggal AKHIR PERIODE invoice.
        |
        */

        foreach ($invoices as $invoice) {

            $invoiceDate = $this->getInvoicePeriodEndDate($invoice);

            $entries->push([
                'date' => $invoiceDate,

                'ref' => $invoice->invoice_number ?? '-',

                'keterangan' => 'Pembayaran dari '
                    . ($invoice->customer->name ?? 'pelanggan'),

                'debit' => (float) $invoice->total,

                'kredit' => 0.0,

                'sort' => $invoice->id,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | EXPENSE
        |--------------------------------------------------------------------------
        */

        foreach ($expenses as $expense) {

            $entries->push([
                'date' => Carbon::parse($expense->date),

                'ref' => 'EXP-'
                    . str_pad(
                        $expense->id,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),

                'keterangan' => $expense->description,

                'debit' => 0.0,

                'kredit' => (float) $expense->amount,

                'sort' => $expense->id,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | URUTKAN MUTASI
        |--------------------------------------------------------------------------
        */

        $entries = $entries
            ->sort(function ($a, $b) {

                $dateCompare =
                    $a['date']->timestamp
                    <=> $b['date']->timestamp;

                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return $a['sort'] <=> $b['sort'];
            })
            ->values();


        /*
        |--------------------------------------------------------------------------
        | RUNNING BALANCE
        |--------------------------------------------------------------------------
        */

        $rows = collect();

        $running = 0.0;


        /*
        |--------------------------------------------------------------------------
        | SALDO AWAL
        |--------------------------------------------------------------------------
        |
        | PENTING:
        |
        | Saldo awal menggunakan $start.
        |
        | Kalau periode:
        | 06/08/2026 - 10/08/2026
        |
        | maka:
        | Saldo Awal = 06/08/2026
        |
        */

        $running += $saldoTahanan;

        $rows->push([
            'date' => $start->copy(),

            'ref' => '-',

            'keterangan' => 'Saldo Awal (Saldo Tahanan)',

            'debit' => $saldoTahanan,

            'kredit' => 0.0,

            'saldo' => $running,
        ]);


        /*
        |--------------------------------------------------------------------------
        | MUTASI
        |--------------------------------------------------------------------------
        */

        foreach ($entries as $entry) {

            $running +=
                $entry['debit']
                - $entry['kredit'];

            $rows->push([
                'date' => $entry['date'],

                'ref' => $entry['ref'],

                'keterangan' => $entry['keterangan'],

                'debit' => $entry['debit'],

                'kredit' => $entry['kredit'],

                'saldo' => $running,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | PENYESUAIAN MATERIAL
        |--------------------------------------------------------------------------
        |
        | Penyesuaian ditempatkan di tanggal akhir rentang.
        |
        */

        if ($remainingMaterial > 0) {

            $running -= $remainingMaterial;

            $rows->push([
                'date' => $end->copy(),

                'ref' => '-',

                'keterangan' =>
                    'Penyesuaian: sisa stok bahan baku (belum jadi kas)',

                'debit' => 0.0,

                'kredit' => $remainingMaterial,

                'saldo' => $running,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $sisaSaldo = $running;

        $saldoTahananSisa =
            $saldoTahanan - $remainingMaterial;

        $selisih =
            $saldoRealtime - $sisaSaldo;


        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('ledger.index', [
            'rows' => $rows,

            'branchId' => $branchId,

            'branches' => $branches,

            'branchName' => $branchName,

            'startDate' => $start->toDateString(),

            'endDate' => $end->toDateString(),

            'saldoTahanan' => $saldoTahanan,

            'saldoTahananSisa' => $saldoTahananSisa,

            'saldoRealtime' => $saldoRealtime,

            'saldoRealtimeLocked' => $saldoRealtimeLocked,

            'saldoRealtimeLockedAt' => $saldoRealtimeLockedAt,

            'totalPemasukan' => $totalPemasukan,

            'totalPengeluaran' => $totalPengeluaran,

            'sisaSaldo' => $sisaSaldo,

            'selisih' => $selisih,

            'remainingMaterial' => $remainingMaterial,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TANGGAL AKHIR PERIODE INVOICE
    |--------------------------------------------------------------------------
    */

    private function getInvoicePeriodEndDate(Invoice $invoice): Carbon
    {
        /*
        | Kalau Invoice punya kolom tanggal akhir periode,
        | gunakan kolom tersebut.
        */

        $possibleDateFields = [
            'period_end',
            'period_to',
            'end_date',
            'date_end',
            'until_date',
            'tanggal_akhir',
            'periode_akhir',
        ];

        foreach ($possibleDateFields as $field) {

            if (
                array_key_exists(
                    $field,
                    $invoice->getAttributes()
                )
                && !empty($invoice->{$field})
            ) {
                try {
                    return Carbon::parse($invoice->{$field});
                } catch (\Throwable $e) {
                    // lanjut
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Kalau periode disimpan sebagai string
        |--------------------------------------------------------------------------
        |
        | Contoh:
        |
        | 24/08/2026 - 31/08/2026
        |
        */

        $possiblePeriodFields = [
            'period',
            'periode',
            'invoice_period',
        ];

        foreach ($possiblePeriodFields as $field) {

            if (
                array_key_exists(
                    $field,
                    $invoice->getAttributes()
                )
                && !empty($invoice->{$field})
            ) {

                $period = trim(
                    (string) $invoice->{$field}
                );

                if (str_contains($period, '-')) {

                    $parts = preg_split(
                        '/\s*-\s*/',
                        $period
                    );

                    $lastPart = trim(
                        end($parts)
                    );

                    /*
                    | Format Indonesia:
                    | 31/08/2026
                    */

                    try {
                        return Carbon::createFromFormat(
                            'd/m/Y',
                            $lastPart
                        );
                    } catch (\Throwable $e) {
                        // lanjut
                    }

                    try {
                        return Carbon::parse($lastPart);
                    } catch (\Throwable $e) {
                        // fallback
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FALLBACK
        |--------------------------------------------------------------------------
        |
        | Kalau field periode belum tersedia di model,
        | gunakan tanggal invoice.
        |
        */

        return Carbon::parse($invoice->date);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SALDO REALTIME
    |--------------------------------------------------------------------------
    */

    public function updateRealtime(Request $request)
    {
        $branchId = $this->branchId($request);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'saldo_realtime' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);


        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu.'
        );

        [$start, $end] = $this->parsedPeriod(
            $validated['start_date'],
            $validated['end_date']
        );

        $periodBalance = $this->findOrCreatePeriodBalance(
            $branchId,
            $start,
            $end
        );

        abort_if(
            $periodBalance->locked_at,
            422,
            'Saldo realtime periode ini sudah dikunci dan tidak dapat diubah.'
        );

        $periodBalance->update([
            'saldo_realtime' => (float) $validated['saldo_realtime'],
        ]);

        return $this->redirectToPeriod(
            $branchId,
            $start,
            $end,
            'Saldo realtime periode berhasil diperbarui.'
        );
    }

    /**
     * Kunci saldo realtime tanpa mengunci closing material.
     */
    public function lockRealtime(Request $request)
    {
        $branchId = $this->branchId($request);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu.'
        );

        [$start, $end] = $this->parsedPeriod(
            $validated['start_date'],
            $validated['end_date']
        );

        $periodBalance = $this->findOrCreatePeriodBalance(
            $branchId,
            $start,
            $end
        );

        abort_if(
            $periodBalance->locked_at,
            422,
            'Saldo realtime periode ini sudah dikunci.'
        );

        $periodBalance->update([
            'locked_at' => now(),
        ]);

        return $this->redirectToPeriod(
            $branchId,
            $start,
            $end,
            'Saldo realtime periode berhasil dikunci.'
        );
    }

    private function findPeriodBalance(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): ?LedgerPeriodBalance {
        return LedgerPeriodBalance::query()
            ->where('branch_id', $branchId)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->first();
    }

    private function findOrCreatePeriodBalance(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): LedgerPeriodBalance {
        return LedgerPeriodBalance::firstOrCreate(
            [
                'branch_id' => $branchId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'saldo_realtime' => 0,
            ]
        );
    }

    /**
     * Parse rentang periode tanpa membatasi pergantian bulan/tahun.
     */
    private function parsedPeriod(
        string $startDate,
        string $endDate
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        abort_if(
            $start->gt($end),
            422,
            'Tanggal akhir harus sama atau setelah tanggal awal.'
        );

        return [$start, $end];
    }

    private function redirectToPeriod(
        int $branchId,
        Carbon $start,
        Carbon $end,
        string $message
    ) {
        return redirect()
            ->route('ledger.index', [
                'branch_id' => $branchId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ])
            ->with('success', $message);
    }
}
