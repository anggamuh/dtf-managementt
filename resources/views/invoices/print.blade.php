<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 13px;
            color: #2b2b2b;
            background: #f2f2f2;
            padding: 30px 0;
        }

        .invoice {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        /* ===== Header ===== */
        .header {
            background: linear-gradient(135deg, #3f7a2f 0%, #6ba856 100%);
            color: #ffffff;
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .brand-tagline {
            font-size: 11px;
            opacity: 0.85;
            margin-top: 2px;
        }

        .invoice-meta {
            text-align: right;
        }

        .invoice-meta .invoice-title {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .invoice-meta .invoice-number {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 4px;
        }

        /* ===== Info row ===== */
        .info-section {
            display: flex;
            justify-content: space-between;
            padding: 24px 32px;
            border-bottom: 1px solid #eee;
        }

        .info-block .info-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #8a8a8a;
            margin-bottom: 4px;
        }

        .info-block .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #2b2b2b;
        }

        .info-block.text-right {
            text-align: right;
        }

        /* ===== Table ===== */
        table {
            width: 100%;
            border-collapse: collapse;
            padding: 0 32px;
        }

        .table-wrap {
            padding: 0 32px;
            margin-top: 8px;
        }

        thead th {
            background: #f5f8f2;
            color: #3f7a2f;
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #6ba856;
        }

        thead th:first-child { border-top-left-radius: 6px; }
        thead th:last-child { border-top-right-radius: 6px; text-align: right; }
        thead th:nth-child(3),
        thead th:nth-child(4) { text-align: right; }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        tbody td:nth-child(1) { width: 8%; color: #999; }
        tbody td:nth-child(2) { width: 42%; font-weight: 600; }
        tbody td:nth-child(3),
        tbody td:nth-child(4) { width: 20%; text-align: right; }
        tbody td:last-child { width: 20%; text-align: right; font-weight: 700; color: #3f7a2f; }

        /* ===== Total ===== */
        .total-section {
            padding: 20px 32px 28px;
        }

        .total-box {
            margin-left: auto;
            width: 260px;
            background: #f5f8f2;
            border-radius: 8px;
            padding: 16px 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-size: 13px;
            font-weight: 600;
            color: #444;
        }

        .total-amount {
            font-size: 20px;
            font-weight: 700;
            color: #3f7a2f;
        }

        /* ===== Footer ===== */
        .footer {
            background: #fafafa;
            padding: 24px 32px 28px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .footer-section .footer-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #8a8a8a;
            margin-bottom: 4px;
        }

        .footer-section .footer-text {
            font-size: 13px;
            font-weight: 600;
            color: #2b2b2b;
            line-height: 1.5;
        }

        .thanks {
            font-size: 12px;
            color: #777;
            font-style: italic;
            margin-top: 10px;
        }

        .company-name {
            font-size: 15px;
            font-weight: 700;
            color: #3f7a2f;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="invoice">

        {{-- Header --}}
        <div class="header">
            <div>
                <div class="brand-name">Awe Print</div>
                <div class="brand-tagline">DTF Printing Services</div>
            </div>
            <div class="invoice-meta">
                <div class="invoice-title">Invoice</div>
                <div class="invoice-number">No. {{ $invoice->invoice_number }}</div>
            </div>
        </div>

        {{-- Info --}}
        <div class="info-section">
            <div class="info-block">
                <div class="info-label">Pelanggan</div>
                <div class="info-value">{{ $invoice->customer->name }}</div>
            </div>
            @if($invoice->period_start && $invoice->period_end)
                <div class="info-block">
                    <div class="info-label">Periode Tagihan</div>
                    <div class="info-value">{{ $invoice->period_start->format('d M Y') }} &ndash; {{ $invoice->period_end->format('d M Y') }}</div>
                </div>
            @endif
            <div class="info-block text-right">
                <div class="info-label">Tanggal</div>
                <div class="info-value">{{ $invoice->date->format('d M Y') }}</div>
            </div>
        </div>

        {{-- Items Table (disummed, bukan per-baris) --}}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Harga Satuan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Harga satuan tetap solid Rp25.000/meter.
                        $unitPrice = 25000;
                        // Ambil langsung dari relasi orders (live), BUKAN dari
                        // invoice_items yang cuma snapshot saat invoice dibuat.
                        // Ini yang bikin print selalu sama dengan index, karena
                        // keduanya sekarang baca sumber yang sama: orders saat ini.
                        $totalQty = $invoice->live_qty;
                        $subtotal = $invoice->live_subtotal;
                        $discount = $invoice->discount ?? 0;
                        $totalAmount = $invoice->live_total;
                    @endphp
                    <tr>
                        <td>1</td>
                        <td>Print DTF</td>
                        <td>{{ number_format($totalQty, 2, ',', '.') }} m</td>
                        <td>Rp{{ number_format($unitPrice, 0, ',', '.') }}</td>
                        <td>Rp{{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Total --}}
        <div class="total-section">
            <div class="total-box">
                <div class="total-row">
                    <div class="total-label">Subtotal</div>
                    <div class="total-amount" style="font-size: 14px; color: #444;">Rp{{ number_format($subtotal, 0, ',', '.') }}</div>
                </div>
                @if($discount > 0)
                    <div class="total-row" style="margin-top: 6px;">
                        <div class="total-label">Diskon</div>
                        <div class="total-amount" style="font-size: 14px; color: #444;">- Rp{{ number_format($discount, 0, ',', '.') }}</div>
                    </div>
                @endif
                <div class="total-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dfe7d8;">
                    <div class="total-label">Total Bayar</div>
                    <div class="total-amount">Rp{{ number_format($totalAmount, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
           <div class="footer-section">
    <div class="footer-label">Pembayaran</div>

    @if(strtoupper($invoice->branch->code) === 'EPUL')
        <div class="footer-text">BCA &middot; 3372278711</div>
        <div class="footer-text">a.n Saepul Maulana</div>

    @elseif(strtoupper($invoice->branch->code) === 'RAPLY')
        <div class="footer-text">BCA &middot; 3372688103</div>
        <div class="footer-text">a.n Ashil al azzis</div>

    @else
        <div class="footer-text">
            Informasi rekening belum tersedia untuk cabang {{ $invoice->branch->name }}.
        </div>
    @endif

    <div class="thanks">Terima kasih atas pesanan Anda.</div>
</div>
            <div class="company-name">Awe Print</div>
        </div>

    </div>
</body>
</html>