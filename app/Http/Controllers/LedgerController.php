<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Closing;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerPeriodBalance;
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


        /*
        |--------------------------------------------------------------------------
        | PERIODE
        |--------------------------------------------------------------------------
        */

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

        if ($start->gt($end)) {
            [$start, $end] = [
                $end->copy()->startOfDay(),
                $start->copy()->endOfDay(),
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | SALDO TAHANAN PERIODE
        |--------------------------------------------------------------------------
        |
        | Saldo tahanan tidak lagi di-hardcode berdasarkan cabang.
        | Nilainya diambil dari LedgerPeriodBalance untuk kombinasi
        | branch_id + period_start + period_end yang sedang aktif.
        |
        */

        $saldoTahanan = 0.0;


        /*
        |--------------------------------------------------------------------------
        | INVOICE
        |--------------------------------------------------------------------------
        */

        $invoices = Invoice::with('customer')
            ->where('branch_id', $branchId)
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('period_end', [
                        $start->toDateString(),
                        $end->toDateString(),
                    ])
                    ->orWhere(function ($fallbackQuery) use ($start, $end) {
                        $fallbackQuery
                            ->whereNull('period_end')
                            ->whereBetween('date', [
                                $start->toDateString(),
                                $end->toDateString(),
                            ]);
                    });
            })
            ->orderByRaw('COALESCE(period_end, date)')
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
        | TOTAL
        |--------------------------------------------------------------------------
        */

        $totalPemasukan = (float) $invoices->sum('total');

        $totalPengeluaran = (float) $expenses->sum('amount');

        $profit = $totalPemasukan - $totalPengeluaran;


        /*
        |--------------------------------------------------------------------------
        | SALDO REALTIME PERIODE AKTIF
        |--------------------------------------------------------------------------
        */

        $periodBalance = $this->findPeriodBalance(
            $branchId,
            $start,
            $end
        );

        $saldoTahanan = (float) (
            $periodBalance?->saldo_tahanan ?? 0
        );

        $saldoRealtime = (float) (
            $periodBalance?->saldo_realtime ?? 0
        );

        $saldoRealtimeLocked = (bool) $periodBalance?->locked_at;

        $saldoRealtimeLockedAt = $periodBalance?->locked_at;


        /*
        |--------------------------------------------------------------------------
        | RIWAYAT PERIODE YANG SUDAH DIKUNCI
        |--------------------------------------------------------------------------
        |
        | Hanya tampilkan periode dari cabang yang sedang aktif.
        |
        */

        $lockedPeriods = collect();

        if ((int) $branchId !== 0) {
            $lockedPeriods = LedgerPeriodBalance::query()
                ->where('branch_id', $branchId)
                ->whereNotNull('locked_at')
                ->orderByDesc('period_end')
                ->orderByDesc('period_start')
                ->get();
        }


        /*
        |--------------------------------------------------------------------------
        | CLOSING PERIODE AKTIF
        |--------------------------------------------------------------------------
        |
        | Buku Besar menggunakan Stock Opname dari Closing periode
        | yang sama persis.
        |
        */

        $closing = Closing::query()
            ->with('materials')
            ->where('branch_id', $branchId)
            ->whereDate(
                'period_start',
                $start->toDateString()
            )
            ->whereDate(
                'period_end',
                $end->toDateString()
            )
            ->latest('id')
            ->first();


        /*
        |--------------------------------------------------------------------------
        | STOCK OPNAME / REMAINING MATERIAL
        |--------------------------------------------------------------------------
        |
        | Nilai:
        |
        | Stock Akhir Closing × Harga Komponen Closing
        |
        | Kalau belum ada Closing periode tersebut:
        | nilai Stock Opname = 0.
        |
        */

        $remainingMaterial = 0.0;

        if ($closing) {
            $remainingMaterial = (float) $closing
                ->materials
                ->sum(function ($component) {
                    $stockAkhir = (float) (
                        $component->stock_akhir ?? 0
                    );

                    $hargaKomponen = (float) (
                        $component->harga_komponen ?? 0
                    );

                    return $stockAkhir * $hargaKomponen;
                });
        }


        /*
        |--------------------------------------------------------------------------
        | PERHITUNGAN SALDO
        |--------------------------------------------------------------------------
        |
        | Saldo Tahanan Sisa
        | = Saldo Tahanan - Stock Opname
        |
        | Sisa Saldo
        | = Profit + Saldo Tahanan Sisa
        |
        | Selisih
        | = Saldo Realtime - Sisa Saldo
        |
        */

        $saldoTahananSisa =
            $saldoTahanan - $remainingMaterial;

        $sisaSaldo =
            $profit + $saldoTahananSisa;

        $selisih =
            $saldoRealtime - $sisaSaldo;


        /*
        |--------------------------------------------------------------------------
        | MUTASI
        |--------------------------------------------------------------------------
        */

        $entries = collect();


        /*
        |--------------------------------------------------------------------------
        | INVOICE
        |--------------------------------------------------------------------------
        */

        foreach ($invoices as $invoice) {
            $invoiceDate =
                $this->getInvoicePeriodEndDate($invoice);

            $entries->push([
                'date' => $invoiceDate,

                'ref' =>
                    $invoice->invoice_number ?? '-',

                'keterangan' =>
                    'Pembayaran dari '
                    . ($invoice->customer->name ?? 'pelanggan'),

                'debit' =>
                    (float) $invoice->total,

                'kredit' =>
                    0.0,

                'sort' =>
                    $invoice->id,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | EXPENSE
        |--------------------------------------------------------------------------
        */

        foreach ($expenses as $expense) {
            $entries->push([
                'date' =>
                    Carbon::parse($expense->date),

                'ref' =>
                    'EXP-'
                    . str_pad(
                        $expense->id,
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),

                'keterangan' =>
                    $expense->description,

                'debit' =>
                    0.0,

                'kredit' =>
                    (float) $expense->amount,

                'sort' =>
                    $expense->id,
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

                return $a['sort']
                    <=> $b['sort'];
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
        */

        $running += $saldoTahanan;

        $rows->push([
            'date' =>
                $start->copy(),

            'ref' =>
                '-',

            'keterangan' =>
                'Saldo Awal (Saldo Tahanan)',

            'debit' =>
                $saldoTahanan,

            'kredit' =>
                0.0,

            'saldo' =>
                $running,
        ]);


        /*
        |--------------------------------------------------------------------------
        | MUTASI TRANSAKSI
        |--------------------------------------------------------------------------
        */

        foreach ($entries as $entry) {
            $running +=
                $entry['debit']
                - $entry['kredit'];

            $rows->push([
                'date' =>
                    $entry['date'],

                'ref' =>
                    $entry['ref'],

                'keterangan' =>
                    $entry['keterangan'],

                'debit' =>
                    $entry['debit'],

                'kredit' =>
                    $entry['kredit'],

                'saldo' =>
                    $running,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | PENYESUAIAN STOCK OPNAME
        |--------------------------------------------------------------------------
        */

        if ($remainingMaterial > 0) {
            $running -= $remainingMaterial;

            $rows->push([
                'date' =>
                    $end->copy(),

                'ref' =>
                    '-',

                'keterangan' =>
                    'Penyesuaian: Stock Opname bahan baku (belum jadi kas)',

                'debit' =>
                    0.0,

                'kredit' =>
                    $remainingMaterial,

                'saldo' =>
                    $running,
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('ledger.index', [
            'rows' =>
                $rows,

            'branchId' =>
                $branchId,

            'branches' =>
                $branches,

            'branchName' =>
                $branchName,

            'startDate' =>
                $start->toDateString(),

            'endDate' =>
                $end->toDateString(),

            'saldoTahanan' =>
                $saldoTahanan,

            'saldoTahananSisa' =>
                $saldoTahananSisa,

            'saldoRealtime' =>
                $saldoRealtime,

            'saldoRealtimeLocked' =>
                $saldoRealtimeLocked,

            'saldoRealtimeLockedAt' =>
                $saldoRealtimeLockedAt,

            'totalPemasukan' =>
                $totalPemasukan,

            'totalPengeluaran' =>
                $totalPengeluaran,

            'profit' =>
                $profit,

            'sisaSaldo' =>
                $sisaSaldo,

            'selisih' =>
                $selisih,

            'remainingMaterial' =>
                $remainingMaterial,

            'closing' =>
                $closing,

            'lockedPeriods' =>
                $lockedPeriods,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TANGGAL AKHIR PERIODE INVOICE
    |--------------------------------------------------------------------------
    */

    private function getInvoicePeriodEndDate(
        Invoice $invoice
    ): Carbon {
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
                    return Carbon::parse(
                        $invoice->{$field}
                    );
                } catch (\Throwable $e) {
                    //
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PERIODE STRING
        |--------------------------------------------------------------------------
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

                    try {
                        return Carbon::createFromFormat(
                            'd/m/Y',
                            $lastPart
                        );
                    } catch (\Throwable $e) {
                        //
                    }

                    try {
                        return Carbon::parse(
                            $lastPart
                        );
                    } catch (\Throwable $e) {
                        //
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FALLBACK
        |--------------------------------------------------------------------------
        */

        return Carbon::parse(
            $invoice->date
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE SALDO PERIODE
    |--------------------------------------------------------------------------
    */

    public function updateRealtime(
        Request $request
    ) {
        $branchId =
            $this->branchId($request);

        $validated = $request->validate([
            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'saldo_tahanan' => [
                'required',
                'numeric',
                'min:0',
            ],

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

        [$start, $end] =
            $this->parsedPeriod(
                $validated['start_date'],
                $validated['end_date']
            );

        $periodBalance =
            $this->findOrCreatePeriodBalance(
                $branchId,
                $start,
                $end
            );

        abort_if(
            $periodBalance->locked_at,
            422,
            'Saldo periode ini sudah dikunci dan tidak dapat diubah.'
        );

        // Assign langsung agar tidak bergantung pada $fillable model.
        $periodBalance->saldo_tahanan =
            (float) $validated['saldo_tahanan'];

        $periodBalance->saldo_realtime =
            (float) $validated['saldo_realtime'];

        $periodBalance->save();

        return $this->redirectToPeriod(
            $branchId,
            $start,
            $end,
            'Saldo tahanan dan saldo realtime periode berhasil diperbarui.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | KUNCI SALDO PERIODE
    |--------------------------------------------------------------------------
    */

    public function lockRealtime(
        Request $request
    ) {
        $branchId =
            $this->branchId($request);

        $validated = $request->validate([
            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
        ]);

        abort_if(
            $branchId === 0,
            422,
            'Pilih cabang terlebih dahulu.'
        );

        [$start, $end] =
            $this->parsedPeriod(
                $validated['start_date'],
                $validated['end_date']
            );

        $periodBalance =
            $this->findOrCreatePeriodBalance(
                $branchId,
                $start,
                $end
            );

        abort_if(
            $periodBalance->locked_at,
            422,
            'Saldo periode ini sudah dikunci.'
        );

        $periodBalance->update([
            'locked_at' =>
                now(),
        ]);

        return $this->redirectToPeriod(
            $branchId,
            $start,
            $end,
            'Saldo tahanan dan saldo realtime periode berhasil dikunci.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FIND PERIOD BALANCE
    |--------------------------------------------------------------------------
    */

    private function findPeriodBalance(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): ?LedgerPeriodBalance {
        return LedgerPeriodBalance::query()
            ->where(
                'branch_id',
                $branchId
            )
            ->whereDate(
                'period_start',
                $start->toDateString()
            )
            ->whereDate(
                'period_end',
                $end->toDateString()
            )
            ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | FIND OR CREATE PERIOD BALANCE
    |--------------------------------------------------------------------------
    */

    private function findOrCreatePeriodBalance(
        int $branchId,
        Carbon $start,
        Carbon $end
    ): LedgerPeriodBalance {
        return LedgerPeriodBalance::firstOrCreate(
            [
                'branch_id' =>
                    $branchId,

                'period_start' =>
                    $start->toDateString(),

                'period_end' =>
                    $end->toDateString(),
            ],
            [
                'saldo_realtime' =>
                    0,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PARSE PERIODE
    |--------------------------------------------------------------------------
    */

    private function parsedPeriod(
        string $startDate,
        string $endDate
    ): array {
        $start =
            Carbon::parse($startDate)
                ->startOfDay();

        $end =
            Carbon::parse($endDate)
                ->endOfDay();

        abort_if(
            $start->gt($end),
            422,
            'Tanggal akhir harus sama atau setelah tanggal awal.'
        );

        return [
            $start,
            $end,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    private function redirectToPeriod(
        int $branchId,
        Carbon $start,
        Carbon $end,
        string $message
    ) {
        return redirect()
            ->route(
                'ledger.index',
                [
                    'branch_id' =>
                        $branchId,

                    'start_date' =>
                        $start->toDateString(),

                    'end_date' =>
                        $end->toDateString(),
                ]
            )
            ->with(
                'success',
                $message
            );
    }
}