@php
    $totalPembelian = $materialRows->sum('purchase_value');
    $showMachineGroups = $materialRows->contains(fn ($row) => $row['material']->machine_id !== null);
    $lastMachineGroup = null;
    $soTotal = $materialRows->sum(fn ($row) => (float) $row['stock_akhir'] * (float) $row['unit_cost']);

    $manualFields = [
        'operasional' => 'Operasional',
        'gaji_karyawan' => 'Gaji Karyawan',
        'teknisi_mesin' => 'Teknisi Mesin',
        'lain_lain' => 'Lain-lain',
        'saldo_tahanan' => 'Saldo Tahanan',
        'saldo_realtime' => 'Saldo Realtime',
    ];
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font: 11px DejaVu Sans; color: #111; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 10px; color: #64748B; margin-bottom: 4px; }
        .badge { text-align: center; margin-bottom: 16px; }
        .badge span {
            display: inline-block; padding: 3px 10px; border-radius: 999px;
            font-size: 9px; font-weight: bold;
        }
        .badge-locked { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

        h2 { font-size: 12px; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #0F172A; }
        h3.section-sub { font-size: 10px; color: #64748B; margin: -6px 0 8px; font-weight: normal; }

        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th, td { border: 1px solid #E2E8F0; padding: 5px 6px; }
        th { background: #F1F5F9; font-size: 9.5px; text-align: left; color: #334155; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #64748B; }
        .total-row td { border-top: 2px solid #0F172A; font-weight: bold; background: #F8FAFC; }
        .empty-row td { text-align: center; color: #94A3B8; padding: 14px; }

        /* summary stat cards */
        .stat-grid { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 10px 0 18px; }
        .stat-grid td {
            border: 1px solid #E2E8F0; border-radius: 6px; padding: 8px 10px;
            width: 25%; vertical-align: top;
        }
        .stat-label { font-size: 8.5px; text-transform: uppercase; color: #64748B; letter-spacing: .03em; }
        .stat-value { font-size: 14px; font-weight: bold; color: #0F172A; margin-top: 3px; }
        .stat-positive { color: #059669; }
        .stat-negative { color: #E11D48; }

        /* saldo & selisih boxes */
        .box-grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .box-grid td {
            background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px;
            padding: 8px 10px; width: 33.33%;
        }
        .box-label { font-size: 8.5px; text-transform: uppercase; color: #64748B; }
        .box-value { font-size: 12px; font-weight: bold; color: #0F172A; margin-top: 3px; }

        /* status pill */
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 8.5px; font-weight: bold; }
        .pill-paid { background: #ECFDF5; color: #047857; }
        .pill-unpaid { background: #FFFBEB; color: #B45309; }
        .pill-cat { background: #FFFBEB; color: #B45309; }

        /* profit summary */
        .profit-cols { width: 100%; border-collapse: collapse; }
        .profit-cols td { border: none; padding: 0 10px; width: 50%; vertical-align: top; }
        .profit-line { width: 100%; border-collapse: collapse; }
        .profit-line td { border: none; border-bottom: 1px solid #E2E8F0; padding: 4px 0; font-size: 10.5px; }
        .profit-line tr:last-child td { border-bottom: 2px solid #0F172A; font-weight: bold; }
        .profit-net {
            margin-top: 14px; padding-top: 10px; border-top: 2px solid #0F172A;
            text-align: right; font-size: 15px; font-weight: bold;
        }

        .manual-grid { width: 100%; border-collapse: separate; border-spacing: 6px; }
        .manual-grid td { width: 33.33%; padding: 6px 8px; vertical-align: top; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <h1>Closing {{ $branch->name }} — {{ \Carbon\Carbon::create()->month($closing->month)->translatedFormat('F') }} {{ $closing->year }}</h1>
    <p class="subtitle">Patokan HPP: Pemakaian = Stock Awal + Pembelian (Qty) &minus; Stock Akhir</p>
    @if($closing->is_locked)
        <p class="badge"><span class="badge-locked">Terkunci sejak {{ $closing->locked_at?->translatedFormat('d M Y H:i') }}</span></p>
    @endif

    {{-- SUMMARY CARDS --}}
    <table class="stat-grid">
        <tr>
            <td>
                <div class="stat-label">Hasil Cetak</div>
                <div class="stat-value">{{ number_format($hpp['hasilCetak'],2,',','.') }} m</div>
            </td>
            <td>
                <div class="stat-label">Total Pemasukan</div>
                <div class="stat-value">Rp {{ number_format($closing->hpp,0,',','.') }}</div>
            </td>
            <td>
                <div class="stat-label">HPP / Meter</div>
                <div class="stat-value">Rp {{ number_format($closing->hpp_per_meter,0,',','.') }}</div>
            </td>
            <td>
                <div class="stat-label">Laba/Rugi</div>
                <div class="stat-value {{ $profit >= 0 ? 'stat-positive' : 'stat-negative' }}">Rp {{ number_format($profit,0,',','.') }}</div>
            </td>
        </tr>
    </table>

    {{-- SECTION 1: RINCIAN PESANAN (INFO SAJA, TIDAK MEMENGARUHI PERHITUNGAN) --}}
    <h2>Rincian Pesanan</h2>
    <p class="subtitle" style="text-align:left;">Daftar pesanan pada periode ini — hanya informasi, tidak memengaruhi Laba/Rugi maupun HPP.</p>
    <table>
        <tr>
            <th>Tanggal</th>
            <th>No. Pesanan</th>
            <th>Customer</th>
            <th>Produk</th>
            <th class="right">Qty</th>
            <th class="right">Total</th>
            <th>Status</th>
        </tr>
        @forelse($orders as $order)
            <tr>
                <td>{{ $order->date?->format('Y-m-d') }}</td>
                <td class="muted">{{ $order->order_number ?? '-' }}</td>
                <td>{{ $order->customer->name ?? '-' }}</td>
                <td>{{ $order->product_name ?? $order->product->name ?? '-' }}</td>
                <td class="right">{{ number_format($order->qty,2,',','.') }}</td>
                <td class="right bold">Rp {{ number_format($order->total,0,',','.') }}</td>
                <td><span class="pill pill-cat">{{ ucfirst($order->status ?? '-') }}</span></td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="7">Tidak ada pesanan pada periode ini</td></tr>
        @endforelse
    </table>

    {{-- SECTION 2: PEMASUKAN (INVOICE) --}}
    <h2>Pemasukan (Invoice)</h2>
    <p class="subtitle" style="text-align:left;">Total: Rp {{ number_format($totalIncome,0,',','.') }}</p>
    <table>
        <tr>
            <th>Tanggal</th>
            <th>Customer</th>
            <th>No. Invoice</th>
            <th class="right">Total</th>
            <th>Status</th>
        </tr>
        @forelse($invoices as $invoice)
            <tr>
                <td>{{ $invoice->date->format('Y-m-d') }}</td>
                <td>{{ $invoice->customer->name ?? '-' }}</td>
                <td>{{ $invoice->invoice_number ?? '-' }}</td>
                <td class="right bold">Rp {{ number_format($invoice->total,0,',','.') }}</td>
                <td><span class="pill {{ $invoice->status == 'paid' ? 'pill-paid' : 'pill-unpaid' }}">{{ ucfirst($invoice->status ?? 'unpaid') }}</span></td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="5">Tidak ada invoice bulan ini</td></tr>
        @endforelse
    </table>

    {{-- SECTION 3: PENGELUARAN --}}
    <h2>Pengeluaran (Expenses)</h2>
    <p class="subtitle" style="text-align:left;">Total (semua kategori, termasuk Bahan Baku): Rp {{ number_format($totalExpense,0,',','.') }}</p>
    <table>
        <tr>
            <th>Tanggal</th>
            <th>Kategori</th>
            <th>Deskripsi</th>
            <th class="right">Total</th>
            <th>Keterangan</th>
        </tr>
        @forelse($expenses as $expense)
            <tr>
                <td>{{ $expense->date->format('Y-m-d') }}</td>
                <td><span class="pill pill-cat">{{ $expense->category }}</span></td>
                <td>{{ $expense->material?->display_name ?? $expense->description }}</td>
                <td class="right bold">Rp {{ number_format($expense->amount,0,',','.') }}</td>
                <td class="muted">{{ $expense->payment_method ?? '-' }}</td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="5">Tidak ada pengeluaran bulan ini</td></tr>
        @endforelse
    </table>

    {{-- SECTION 4: SALDO & SELISIH --}}
    <h2>Saldo &amp; Selisih</h2>
    <table class="box-grid">
        <tr>
            <td>
                <div class="box-label">Saldo Tahanan</div>
                <div class="box-value">Rp {{ number_format($saldoTahanan,0,',','.') }}</div>
            </td>
            <td>
                <div class="box-label">Remaining Material</div>
                <div class="box-value">Rp {{ number_format($remainingMaterial,0,',','.') }}</div>
            </td>
            <td>
                <div class="box-label">Saldo Tahanan Sisa</div>
                <div class="box-value" style="color: {{ $saldoTahananSisa >= 0 ? '#059669' : '#E11D48' }};">Rp {{ number_format($saldoTahananSisa,0,',','.') }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="box-label">Sisa Saldo (Profit + Saldo Tahanan Sisa)</div>
                <div class="box-value" style="color: {{ $sisaSaldo >= 0 ? '#059669' : '#E11D48' }};">Rp {{ number_format($sisaSaldo,0,',','.') }}</div>
            </td>
            <td>
                <div class="box-label">Saldo Realtime</div>
                <div class="box-value">Rp {{ number_format($saldoRealtime,0,',','.') }}</div>
            </td>
            <td>
                <div class="box-label">Selisih (Realtime - Sisa Saldo)</div>
                <div class="box-value" style="color: {{ $selisih >= 0 ? '#059669' : '#E11D48' }};">Rp {{ number_format($selisih,0,',','.') }}</div>
            </td>
        </tr>
    </table>

    {{-- SECTION 5: STOCK OPNAME BAHAN BAKU --}}
    <h2>Stock Opname Bahan Baku</h2>
    <h3 class="section-sub">Satuan diambil dari Stock Akhir material. Total = Satuan &times; Harga Manual.</h3>
    <table>
        <tr>
            <th>Stok Bahan Baku</th>
            <th class="right">Satuan</th>
            <th class="right">Harga</th>
            <th class="right">Total</th>
        </tr>
        @forelse($materialRows as $row)
            @php
                $stockAkhir = (float) $row['stock_akhir'];
                $hargaSo = (float) $row['unit_cost'];
                $totalSo = $stockAkhir * $hargaSo;
            @endphp
            <tr>
                <td>{{ $row['material']->display_name ?? 'N/A' }}</td>
                <td class="right">{{ number_format($stockAkhir,2,',','.') }} {{ $row['material']->unit ?? '' }}</td>
                <td class="right">Rp {{ number_format($hargaSo,0,',','.') }}</td>
                <td class="right bold">Rp {{ number_format($totalSo,0,',','.') }}</td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="4">Belum ada data material. Generate closing terlebih dahulu.</td></tr>
        @endforelse
        @if($materialRows->isNotEmpty())
            <tr class="total-row">
                <td colspan="3">Total Stock Opname</td>
                <td class="right">Rp {{ number_format($soTotal,0,',','.') }}</td>
            </tr>
        @endif
    </table>

    {{-- SECTION 6: RINCIAN MATERIAL --}}
    <h2>Rincian Material</h2>
    <table>
        <tr>
            <th>Nama Komponen</th>
            <th class="right">Stock Awal</th>
            <th class="right">Harga Komponen</th>
            <th class="right">Qty</th>
            <th class="right">Pembelian</th>
            <th class="right">Stock Akhir</th>
        </tr>
        @foreach($materialRows as $row)
            @php $machineGroup = $row['material']->machine?->name ?? 'Belum Ditentukan'; @endphp
            @if($showMachineGroups && $lastMachineGroup !== $machineGroup)
                @php
                    $lastMachineGroup = $machineGroup;
                    $machineSubtotal = $materialRows->filter(fn ($item) => ($item['material']->machine?->name ?? 'Belum Ditentukan') === $machineGroup)->sum('purchase_value');
                @endphp
                <tr><td colspan="6" class="bold">{{ strtoupper($machineGroup) }} — Subtotal Pembelian: Rp {{ number_format($machineSubtotal,0,',','.') }}</td></tr>
            @endif
            <tr>
                <td>{{ $row['material']->display_name }} ({{ $row['material']->unit }})</td>
                <td class="right">{{ number_format($row['stock_awal'],2,',','.') }}</td>
                <td class="right">Rp {{ number_format($row['unit_cost'],0,',','.') }}</td>
                <td class="right">{{ number_format($row['incoming_quantity'],2,',','.') }}</td>
                <td class="right">Rp {{ number_format($row['purchase_value'],0,',','.') }}</td>
                <td class="right">{{ number_format($row['stock_akhir'],2,',','.') }}</td>
            </tr>
        @endforeach
        @if(isset($closing) && (float)($closing->operasional ?? 0) > 0)
            <tr>
                <td>Operasional</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="right">Rp {{ number_format($closing->operasional,0,',','.') }}</td>
                <td></td>
            </tr>
        @endif
        @if($materialRows->isNotEmpty())
            <tr class="total-row">
                <td>Total</td>
                <td class="right">{{ number_format($materialRows->sum('stock_awal'),2,',','.') }}</td>
                <td></td>
                <td class="right">{{ number_format($materialRows->sum('incoming_quantity'),2,',','.') }}</td>
                <td class="right">Rp {{ number_format($totalPembelian + (float)($closing->operasional ?? 0),0,',','.') }}</td>
                <td class="right">{{ number_format($materialRows->sum('stock_akhir'),2,',','.') }}</td>
            </tr>
        @endif
    </table>

    {{-- SECTION 7: RINCIAN HPP / METER --}}
    <h2>Rincian HPP / Meter</h2>
    <h3 class="section-sub">
        Hasil Cetak: {{ number_format($hpp['hasilCetak'],2,',','.') }} m (dari total qty invoice) &middot; Metrik informasi, tidak memengaruhi Laba/Rugi
    </h3>
    <table>
        <tr>
            <th>Komponen</th>
            <th>Basis</th>
            <th class="right">Total Harga</th>
            <th class="right">Rp / Meter</th>
        </tr>
        @forelse($hpp['allRows'] as $row)
            <tr>
                <td>{{ $row['nama'] }}</td>
                <td class="muted">{{ $row['basis'] }}</td>
                <td class="right">Rp {{ number_format($row['totalHarga'],0,',','.') }}</td>
                <td class="right">Rp {{ number_format($row['rpMeter'],0,',','.') }}</td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="4">Belum ada data HPP</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3">Total HPP / Meter</td>
            <td class="right">Rp {{ number_format($hpp['totalRpMeter'],0,',','.') }}</td>
        </tr>
    </table>

    {{-- SECTION 8: DATA MANUAL CLOSING --}}
    <h2>Data Manual Closing</h2>
    <table class="manual-grid">
        <tr>
            @foreach($manualFields as $field => $label)
                <td>
                    <div class="box-label">{{ $label }}</div>
                    <div class="box-value">Rp {{ number_format($closing->{$field} ?? 0,0,',','.') }}</div>
                </td>
                @if((($loop->index + 1) % 3) == 0 && !$loop->last)
            </tr><tr>
                @endif
            @endforeach
        </tr>
    </table>

    {{-- SECTION 9: RINGKASAN LABA/RUGI --}}
    <h2>Ringkasan Laba/Rugi</h2>
    <table class="profit-cols">
        <tr>
            <td>
                <table class="profit-line">
                    <tr><td>Total Pemasukan (Invoice)</td><td class="right">Rp {{ number_format($totalIncome,0,',','.') }}</td></tr>
                    <tr><td>Saldo Tahanan</td><td class="right">Rp {{ number_format($saldoTahanan,0,',','.') }}</td></tr>
                    <tr><td>Saldo Realtime</td><td class="right">Rp {{ number_format($saldoRealtime,0,',','.') }}</td></tr>
                    <tr><td>Total Pemasukan + Saldo</td><td class="right">Rp {{ number_format($totalIncome + $saldoTahanan + $saldoRealtime,0,',','.') }}</td></tr>
                </table>
            </td>
            <td>
                <table class="profit-line">
                    <tr><td>Total Pengeluaran (semua kategori)</td><td class="right">Rp {{ number_format($totalExpense,0,',','.') }}</td></tr>
                    <tr><td>Remaining Material</td><td class="right">Rp {{ number_format($remainingMaterial,0,',','.') }}</td></tr>
                    <tr><td>HPP / Meter (info)</td><td class="right">Rp {{ number_format($closing->hpp_per_meter,0,',','.') }}</td></tr>
                    <tr><td>Total HPP (info, tidak memengaruhi laba)</td><td class="right">Rp {{ number_format($closing->hpp,0,',','.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="profit-net {{ $profit >= 0 ? 'stat-positive' : 'stat-negative' }}">
        Laba/Rugi Bersih (Pemasukan - Pengeluaran): Rp {{ number_format($profit,0,',','.') }}
    </div>

</body>
</html>