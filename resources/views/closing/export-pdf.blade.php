@php
    $totalPembelian = $materialRows->sum('purchase_value');
    $showMachineGroups = $materialRows->contains(fn ($row) => $row['material']->machine_id !== null);
    $lastMachineGroup = null;
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font: 11px DejaVu Sans; color: #111; }
        h1 { text-align: center; font-size: 14px; margin-bottom: 16px; }
        h2 { font-size: 12px; margin-top: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; }
        th { background: #eee; font-size: 10px; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .total-row td { border-top: 2px solid #000; font-weight: bold; background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Closing {{ $branch->name }} — {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }} {{ $year }}</h1>

    {{-- Summary --}}
    <table>
        <tr>
            <th>Pendapatan</th>
            <th>Pengeluaran Operasional</th>
            <th>HPP</th>
            <th>Profit</th>
        </tr>
        <tr>
            <td class="right">Rp {{ number_format($totalIncome,0,',','.') }}</td>
            <td class="right">Rp {{ number_format($totalExpense,0,',','.') }}</td>
            <td class="right">Rp {{ number_format($closing?->hpp ?? 0,0,',','.') }}</td>
            <td class="right">Rp {{ number_format($profit,0,',','.') }}</td>
        </tr>
    </table>

    {{-- Material Table --}}
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
                @php $lastMachineGroup = $machineGroup; $machineSubtotal = $materialRows->filter(fn ($item) => ($item['material']->machine?->name ?? 'Belum Ditentukan') === $machineGroup)->sum('purchase_value'); @endphp
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

    {{-- HPP per Meter --}}
    <h2>HPP / Meter ({{ number_format($hpp['hasilCetak'],2,',','.') }} m)</h2>
    <table>
        <tr>
            <th>Komponen</th>
            <th class="right">Nilai</th>
            <th class="right">Rp/Meter</th>
        </tr>
        @foreach($hpp['allRows'] as $row)
            <tr>
                <td>{{ $row['nama'] }}</td>
                <td class="right">Rp {{ number_format($row['totalHarga'],0,',','.') }}</td>
                <td class="right">Rp {{ number_format($row['rpMeter'],0,',','.') }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
