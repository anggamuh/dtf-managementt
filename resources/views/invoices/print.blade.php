
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Invoice {{ $invoice->invoice_number }} - Awe Print</title>

    @php
        /*
        |--------------------------------------------------------------------------
        | KONFIGURASI CABANG
        |--------------------------------------------------------------------------
        */

        $branchCode = strtoupper(trim($invoice->branch?->code ?? ''));

        $isEpul = $branchCode === 'EPUL';

        // Mendukung kode RAPLY dan RAFLY.
        $isRafly = in_array(
            $branchCode,
            ['RAPLY', 'RAFLY'],
            true
        );

        $themeClass = $isEpul
            ? 'invoice-epul'
            : ($isRafly ? 'invoice-rafly' : 'invoice-other');

        $branchLabel = $isEpul
            ? 'EPUL'
            : ($isRafly ? 'RAFLY' : ($invoice->branch?->name ?? '-'));

        /*
        |--------------------------------------------------------------------------
        | DATA INVOICE - LIVE ORDERS
        |--------------------------------------------------------------------------
        */

        $unitPrice = 25000;

        $totalQty = $invoice->live_qty;
        $subtotal = $invoice->live_subtotal;
        $discount = $invoice->discount ?? 0;
        $totalAmount = $invoice->live_total;

        $hasPeriod = $invoice->period_start && $invoice->period_end;
    @endphp

    <style>

        /* ==========================================
           GLOBAL
           ========================================== */

        :root {
            --page: #eef2f3;
            --ink: #243641;
            --muted: #7f919a;
            --line: #e4ebeb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: var(--ink);
            background: var(--page);
            padding: 20px 12px 28px;
        }

        /* ==========================================
           INVOICE COMPACT
           ========================================== */

        .invoice {
            --accent: #178568;
            --accent-soft: #eef9f5;
            --accent-border: #d7ebe3;

            --header-start: #152f3e;
            --header-end: #1a4552;

            --total-bg: #152f3e;
            --total-value: #87ebc6;

            --footer-bg: #f8fbfa;
            --strong-ink: #203c47;

            width: 100%;
            max-width: 680px;
            margin: 0 auto;

            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;

            box-shadow: 0 8px 28px rgba(19, 43, 54, 0.08);
        }

        /* ==========================================
           TEMA EPUL - NAVY TEAL
           ========================================== */

        .invoice-epul {
            --accent: #118c70;
            --accent-soft: #ecf9f4;
            --accent-border: #cee9df;

            --header-start: #102632;
            --header-end: #19404e;

            --total-bg: #142e3b;
            --total-value: #83efc8;

            --footer-bg: #f6faf9;
            --strong-ink: #193845;
        }

        /* ==========================================
           TEMA RAFLY - EMERALD CREAM
           ========================================== */

        .invoice-rafly {
            --accent: #347d47;
            --accent-soft: #f3f8ed;
            --accent-border: #dbe8d1;

            --header-start: #f9fcf6;
            --header-end: #f0f8ed;

            --total-bg: #285637;
            --total-value: #d8f6a6;

            --footer-bg: #fafbf6;
            --strong-ink: #294830;

            border: 1px solid #e1ebdc;
        }

        /* ==========================================
           TOP ACCENT
           ========================================== */

        .top-accent {
            height: 4px;
            background: linear-gradient(
                90deg,
                #118c70,
                #46c5a6,
                #a1e6cf
            );
        }

        .invoice-rafly .top-accent {
            background: linear-gradient(
                90deg,
                #2c713b,
                #75b35d,
                #c3d98a
            );
        }

        /* ==========================================
           HEADER COMPACT
           ========================================== */

        .masthead {
            position: relative;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;

            min-height: 100px;
            padding: 20px 26px;

            background: linear-gradient(
                120deg,
                var(--header-start),
                var(--header-end)
            );

            color: #ffffff;
            overflow: hidden;
        }

        .masthead::after {
            content: "";
            position: absolute;

            right: -55px;
            bottom: -120px;

            width: 220px;
            height: 220px;

            border-radius: 50%;
            border: 32px solid rgba(110, 232, 191, 0.07);

            pointer-events: none;
        }

        .brand-area,
        .invoice-heading-area {
            position: relative;
            z-index: 1;
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-mark {
            flex-shrink: 0;

            display: flex;
            justify-content: center;
            align-items: center;

            width: 37px;
            height: 37px;

            border-radius: 9px;
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.08);

            color: #83efc8;

            font-size: 14px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .brand-tagline {
            margin-top: 3px;
            font-size: 10px;
            color: #b3cbd0;
        }

        .invoice-heading-area {
            text-align: right;
            flex-shrink: 0;
        }

        .heading-eyebrow {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 1px;
            color: #8bdfc2;
        }

        .invoice-title {
            margin-top: 4px;

            font-size: 23px;
            font-weight: 850;
            letter-spacing: -0.6px;
            line-height: 1.1;
        }

        .invoice-number {
            display: inline-block;

            margin-top: 7px;
            padding: 4px 10px;

            border: 1px solid rgba(255,255,255,0.17);
            border-radius: 30px;

            background: rgba(255,255,255,0.08);

            color: #b8f2dd;

            font-size: 10px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        /* ==========================================
           HEADER KHUSUS RAFLY
           ========================================== */

        .invoice-rafly .masthead {
            color: #285235;
            border-bottom: 1px solid #dfebd9;
        }

        .invoice-rafly .masthead::after {
            border-color: rgba(67, 137, 74, 0.055);
        }

        .invoice-rafly .brand-mark {
            background: #347d47;
            border-color: #347d47;
            color: #ffffff;
        }

        .invoice-rafly .brand-name {
            color: #285235;
        }

        .invoice-rafly .brand-tagline {
            color: #6e9073;
        }

        .invoice-rafly .heading-eyebrow {
            color: #66996a;
        }

        .invoice-rafly .invoice-title {
            color: #2d713e;
        }

        .invoice-rafly .invoice-number {
            color: #347d47;
            background: #e8f4e6;
            border-color: #d2e6cc;
        }

        /* ==========================================
           BRANCH STRIP
           ========================================== */

        .branch-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;

            padding: 8px 26px;

            background: #f7faf9;
            border-bottom: 1px solid var(--line);
        }

        .branch-identification {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .branch-small-label {
            font-size: 9px;
            color: #82969c;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .branch-name {
            font-size: 9px;
            font-weight: 800;
            color: var(--accent);

            background: var(--accent-soft);
            border: 1px solid var(--accent-border);

            padding: 3px 9px;
            border-radius: 20px;
            letter-spacing: 0.4px;
        }

        .document-tag {
            font-size: 9px;
            color: #89a099;
        }

        .invoice-rafly .branch-strip {
            background: #f8fbf5;
        }

        /* ==========================================
           MAIN CONTENT
           ========================================== */

        .content {
            padding: 19px 26px 22px;
        }

        /* ==========================================
           INFORMATION CARDS
           ========================================== */

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));

            gap: 9px;
            margin-bottom: 21px;
        }

        .info-grid.no-period {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .info-card {
            min-width: 0;

            padding: 11px 12px;

            background: #fbfcfc;
            border: 1px solid #e4ecec;
            border-radius: 7px;
        }

        .info-card.highlight {
            background: var(--accent-soft);
            border-color: var(--accent-border);
        }

        .info-label {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #80939a;
        }

        .info-value {
            margin-top: 4px;

            font-size: 12px;
            font-weight: 750;

            color: var(--strong-ink);
            overflow-wrap: anywhere;
        }

        .info-description {
            margin-top: 2px;

            font-size: 9px;
            color: #94a5aa;
            overflow-wrap: anywhere;
        }

        /* ==========================================
           INFORMATION RAFLY
           ========================================== */

        .invoice-rafly .info-card {
            background: #fcfdfa;
            border-color: #e5eddf;
        }

        .invoice-rafly .info-card.highlight {
            background: #f1f8ee;
            border-color: #d8e8d0;
        }

        /* ==========================================
           SECTION HEADING
           ========================================== */

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;

            margin-bottom: 10px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--strong-ink);
            letter-spacing: -0.2px;
        }

        .section-description {
            margin-top: 2px;

            font-size: 10px;
            color: #91a1a7;
        }

        .item-badge {
            flex-shrink: 0;

            padding: 4px 9px;
            border-radius: 30px;

            background: var(--accent-soft);
            color: var(--accent);

            font-size: 9px;
            font-weight: 800;
        }

        /* ==========================================
           TABLE COMPACT
           ========================================== */

        .table-wrap {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            padding: 9px 8px;
            text-align: left;

            background: var(--accent-soft);
            color: var(--accent);

            font-size: 9px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.3px;

            border-bottom: 1px solid var(--accent-border);
        }

        thead th:first-child {
            border-radius: 6px 0 0 0;
        }

        thead th:last-child {
            border-radius: 0 6px 0 0;
        }

        tbody td {
            padding: 12px 8px;

            border-bottom: 1px solid var(--line);
            vertical-align: top;

            font-size: 11px;
        }

        .col-no {
            width: 7%;
            color: #95a5ab;
        }

        .col-product {
            width: 31%;
        }

        .col-qty {
            width: 13%;
        }

        .col-price {
            width: 23%;
        }

        .col-total {
            width: 26%;
        }

        .item-name {
            font-weight: 800;
            color: var(--strong-ink);
        }

        .number {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .soft {
            color: #748990;
        }

        .line-total {
            font-weight: 800;
            color: var(--accent);
        }

        /* ==========================================
           TOTAL SECTION COMPACT
           ========================================== */

        .total-section {
            display: flex;
            justify-content: flex-end;

            padding-top: 16px;
        }

        .total-box {
            width: 285px;
            max-width: 100%;

            background: var(--total-bg);
            color: #ffffff;

            border-radius: 8px;
            padding: 14px 16px;
        }

        .total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 9px;
        }

        .total-row + .total-row {
            margin-top: 6px;
        }

        .total-label {
            font-size: 11px;
            color: #c5d6d3;
        }

        .total-amount {
            font-size: 12px;
            font-weight: 700;

            color: #ffffff;
            white-space: nowrap;

            font-variant-numeric: tabular-nums;
        }

        .final-total {
            margin-top: 10px !important;
            padding-top: 10px;

            border-top: 1px solid rgba(255,255,255,0.18);
        }

        .final-total .total-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #e4f1ed;
        }

        .final-total .total-amount {
            font-size: 19px;
            font-weight: 850;
            letter-spacing: -0.3px;

            color: var(--total-value);
        }

        /* ==========================================
           TOTAL RAFLY
           ========================================== */

        .invoice-rafly .total-box {
            background: linear-gradient(
                135deg,
                #285637 0%,
                #397647 100%
            );
        }

        .invoice-rafly .final-total {
            border-top-color: rgba(255,255,255,0.23);
        }

        /* ==========================================
           FOOTER COMPACT
           ========================================== */

        .footer {
            padding: 18px 26px;

            background: var(--footer-bg);
            border-top: 1px solid var(--line);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
        }

        .payment-section {
            flex: 1;
            min-width: 0;
            max-width: 350px;
        }

        .payment-card {
            padding: 11px 14px;

            background: #ffffff;
            border: 1px solid var(--accent-border);
            border-left: 4px solid var(--accent);
            border-radius: 7px;
        }

        .payment-label {
            font-size: 9px;
            color: #81969a;

            text-transform: uppercase;
            letter-spacing: 0.6px;
            font-weight: 800;

            margin-bottom: 5px;
        }

        .bank-name {
            font-size: 10px;
            font-weight: 800;
            color: var(--accent);

            margin-bottom: 2px;
        }

        .bank-number {
            font-size: 16px;
            font-weight: 800;

            color: var(--strong-ink);
            letter-spacing: 0.3px;

            font-variant-numeric: tabular-nums;
        }

        .bank-owner {
            margin-top: 3px;

            font-size: 11px;
            font-weight: 600;
            color: #677b82;
        }

        .thanks {
            margin-top: 9px;

            font-size: 10px;
            font-style: italic;
            color: #82969c;
        }

        .footer-brand {
            text-align: right;
            flex-shrink: 0;
        }

        .footer-company {
            font-size: 15px;
            font-weight: 850;
            color: var(--accent);
            letter-spacing: -0.4px;
        }

        .footer-branch {
            margin-top: 2px;

            font-size: 9px;
            color: #91a5a6;
            font-weight: 700;
            letter-spacing: 0.6px;
        }

        .footer-bottom {
            margin-top: 15px;
            padding-top: 10px;

            border-top: 1px solid #e4ebeb;

            display: flex;
            justify-content: space-between;
            gap: 12px;

            font-size: 9px;
            color: #96a6a9;
        }

        /* ==========================================
           FOOTER RAFLY
           ========================================== */

        .invoice-rafly .footer {
            background: #fafbf6;
        }

        .invoice-rafly .payment-card {
            border-color: #dae8d3;
            border-left: 4px solid #4a9451;
        }

        .invoice-rafly .bank-name {
            color: #397d48;
        }

        /* ==========================================
           RESPONSIVE MOBILE
           ========================================== */

        @media screen and (max-width: 620px) {

            body {
                padding: 9px 7px 20px;
            }

            .invoice {
                border-radius: 9px;
            }

            .masthead {
                min-height: 90px;
                padding: 17px 15px;
                gap: 9px;
                align-items: center;
            }

            .brand-lockup {
                gap: 8px;
            }

            .brand-mark {
                width: 32px;
                height: 32px;

                border-radius: 8px;
                font-size: 12px;
            }

            .brand-name {
                font-size: 17px;
            }

            .brand-tagline {
                font-size: 9px;
            }

            .heading-eyebrow {
                font-size: 8px;
                letter-spacing: 0.6px;
            }

            .invoice-title {
                font-size: 19px;
            }

            .invoice-number {
                font-size: 9px;
                padding: 4px 7px;
            }

            .branch-strip {
                padding: 8px 15px;
            }

            .document-tag {
                display: none;
            }

            .content {
                padding: 16px 15px;
            }

            .info-grid,
            .info-grid.no-period {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 7px;
                margin-bottom: 17px;
            }

            .info-card {
                padding: 9px;
            }

            .info-label {
                font-size: 9px;
            }

            .info-value {
                font-size: 11px;
            }

            .info-description {
                font-size: 9px;
            }

            .section-heading {
                margin-bottom: 8px;
            }

            .section-title {
                font-size: 13px;
            }

            .section-description {
                font-size: 9px;
            }

            .table-wrap {
                overflow-x: auto;
            }

            table {
                min-width: 500px;
            }

            .total-section {
                padding-top: 14px;
            }

            .total-box {
                width: 100%;
                padding: 13px 15px;
            }

            .final-total .total-amount {
                font-size: 18px;
            }

            .footer {
                padding: 15px;
            }

            .footer-content {
                flex-direction: column;
                align-items: stretch;
                gap: 13px;
            }

            .payment-section {
                max-width: none;
            }

            .footer-brand {
                text-align: right;
            }

            .footer-bottom {
                font-size: 8px;
            }
        }

        /* ==========================================
           CETAK / SIMPAN PDF A4
           ========================================== */

        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        @media print {

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;

                background: #ffffff !important;
                font-size: 11px;

                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .invoice {
                width: 100%;
                max-width: 680px;
                margin: 0 auto;

                border: none;
                border-radius: 0;
                box-shadow: none;
                overflow: visible;
            }

            .top-accent {
                height: 4px;
            }

            .masthead {
                min-height: 85px;
                padding: 15px 22px;
            }

            .brand-mark {
                width: 34px;
                height: 34px;
                font-size: 13px;
            }

            .brand-name {
                font-size: 18px;
            }

            .invoice-title {
                font-size: 21px;
            }

            .branch-strip {
                padding: 7px 22px;
            }

            .content {
                padding: 17px 22px;
            }

            .info-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
                margin-bottom: 17px;
            }

            .info-grid.no-period {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .info-card {
                padding: 9px 11px;
            }

            .info-value {
                font-size: 11px;
            }

            .table-wrap {
                overflow: visible;
            }

            table {
                min-width: 0;
            }

            thead {
                display: table-header-group;
            }

            thead th {
                padding: 8px;
            }

            tbody td {
                padding: 10px 8px;
            }

            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .total-section {
                padding-top: 14px;
            }

            .total-box {
                width: 280px;
                padding: 12px 15px;
            }

            .final-total .total-amount {
                font-size: 18px;
            }

            .footer {
                padding: 14px 22px;
            }

            .footer-content {
                flex-direction: row;
                align-items: flex-end;
            }

            .payment-section {
                max-width: 350px;
            }

            .payment-card {
                padding: 9px 12px;
            }

            .footer-brand {
                text-align: right;
            }

            .footer-bottom {
                margin-top: 11px;
                font-size: 9px;
            }

            .masthead,
            .info-card,
            .total-box,
            .payment-card,
            .footer-content {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

    </style>
</head>

<body>

<main class="invoice {{ $themeClass }}">

    {{-- ==========================================
         TOP ACCENT
         ========================================== --}}

    <div class="top-accent"></div>

    {{-- ==========================================
         HEADER
         ========================================== --}}

    <header class="masthead">

        <div class="brand-area">

            <div class="brand-lockup">

                <div class="brand-mark">
                    AP
                </div>

                <div>

                    <div class="brand-name">
                        Awe Print
                    </div>

                    <div class="brand-tagline">
                        DTF Printing Services
                    </div>

                </div>

            </div>

        </div>

        <div class="invoice-heading-area">

            <div class="heading-eyebrow">
                Dokumen Tagihan
            </div>

            <div class="invoice-title">
                INVOICE
            </div>

            <div class="invoice-number">
                No. {{ $invoice->invoice_number }}
            </div>

        </div>

    </header>

    {{-- ==========================================
         INFORMASI CABANG
         ========================================== --}}

    <div class="branch-strip">

        <div class="branch-identification">

            <span class="branch-small-label">
                Cabang
            </span>

            <span class="branch-name">
                {{ $branchLabel }}
            </span>

        </div>

        <div class="document-tag">
            Digital Textile Printing
        </div>

    </div>

    {{-- ==========================================
         CONTENT
         ========================================== --}}

    <div class="content">

        {{-- ======================================
             INFORMASI INVOICE
             ====================================== --}}

        <section class="info-grid {{ $hasPeriod ? '' : 'no-period' }}">

            {{-- PELANGGAN --}}

            <div class="info-card">

                <div class="info-label">
                    Pelanggan
                </div>

                <div class="info-value">
                    {{ $invoice->customer->name }}
                </div>

                <div class="info-description">
                    Nama pelanggan terdaftar
                </div>

            </div>

            {{-- PERIODE TAGIHAN --}}

            @if($hasPeriod)

                <div class="info-card">

                    <div class="info-label">
                        Periode Tagihan
                    </div>

                    <div class="info-value">

                        {{ $invoice->period_start->format('d M Y') }}

                        &ndash;

                        {{ $invoice->period_end->format('d M Y') }}

                    </div>

                    <div class="info-description">
                        Periode transaksi
                    </div>

                </div>

            @endif

            {{-- TANGGAL INVOICE --}}

            <div class="info-card highlight">

                <div class="info-label">
                    Tanggal Invoice
                </div>

                <div class="info-value">
                    {{ $invoice->date->format('d M Y') }}
                </div>

                <div class="info-description">
                    Referensi: {{ $invoice->invoice_number }}
                </div>

            </div>

        </section>

        {{-- ======================================
             RINCIAN LAYANAN
             ====================================== --}}

        <section>

            <div class="section-heading">

                <div>

                    <div class="section-title">
                        Rincian Layanan
                    </div>

                    <div class="section-description">
                        Detail pesanan cetak DTF
                    </div>

                </div>

                <div class="item-badge">
                    1 Layanan
                </div>

            </div>

            {{-- TABEL --}}

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th class="col-no">
                                No
                            </th>

                            <th class="col-product">
                                Produk
                            </th>

                            <th class="col-qty number">
                                Qty
                            </th>

                            <th class="col-price number">
                                Harga Satuan
                            </th>

                            <th class="col-total number">
                                Total
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td class="col-no">
                                1
                            </td>

                            <td class="col-product">

                                <span class="item-name">
                                    Print DTF
                                </span>

                            </td>

                            <td class="col-qty number soft">

                                {{ number_format($totalQty, 2, ',', '.') }} m

                            </td>

                            <td class="col-price number soft">

                                Rp{{ number_format($unitPrice, 0, ',', '.') }}

                            </td>

                            <td class="col-total number line-total">

                                Rp{{ number_format($subtotal, 0, ',', '.') }}

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </section>

        {{-- ======================================
             TOTAL PEMBAYARAN
             ====================================== --}}

        <section class="total-section">

            <div class="total-box">

                {{-- SUBTOTAL --}}

                <div class="total-row">

                    <span class="total-label">
                        Subtotal
                    </span>

                    <span class="total-amount">

                        Rp{{ number_format($subtotal, 0, ',', '.') }}

                    </span>

                </div>

                {{-- DISKON --}}

                @if($discount > 0)

                    <div class="total-row">

                        <span class="total-label">
                            Diskon
                        </span>

                        <span class="total-amount">

                            - Rp{{ number_format($discount, 0, ',', '.') }}

                        </span>

                    </div>

                @endif

                {{-- TOTAL BAYAR --}}

                <div class="total-row final-total">

                    <span class="total-label">
                        Total Bayar
                    </span>

                    <span class="total-amount">

                        Rp{{ number_format($totalAmount, 0, ',', '.') }}

                    </span>

                </div>

            </div>

        </section>

    </div>

    {{-- ==========================================
         FOOTER & REKENING PEMBAYARAN
         ========================================== --}}

    <footer class="footer">

        <div class="footer-content">

            {{-- INFORMASI PEMBAYARAN --}}

            <div class="payment-section">

                <div class="payment-card">

                    <div class="payment-label">
                        Informasi Pembayaran
                    </div>

                    {{-- REKENING EPUL --}}

                    @if($isEpul)

                        <div class="bank-name">
                            BANK BCA
                        </div>

                        <div class="bank-number">
                            3372278711
                        </div>

                        <div class="bank-owner">
                            a.n Saepul Maulana
                        </div>

                    {{-- REKENING RAFLY --}}

                    @elseif($isRafly)

                        <div class="bank-name">
                            BANK BCA
                        </div>

                        <div class="bank-number">
                            3372688103
                        </div>

                        <div class="bank-owner">
                            a.n Ashil al azzis
                        </div>

                    {{-- CABANG LAIN --}}

                    @else

                        <div class="bank-owner">

                            Informasi rekening belum tersedia
                            untuk cabang
                            {{ $invoice->branch?->name ?? '-' }}.

                        </div>

                    @endif

                </div>

                <div class="thanks">
                    Terima kasih atas kepercayaan dan pesanan Anda.
                </div>

            </div>

            {{-- BRAND FOOTER --}}

            <div class="footer-brand">

                <div class="footer-company">
                    Awe Print
                </div>

                <div class="footer-branch">
                    {{ strtoupper($branchLabel) }} BRANCH
                </div>

            </div>

        </div>

        {{-- FOOTER BOTTOM --}}

        <div class="footer-bottom">

            <div>
                Invoice No. {{ $invoice->invoice_number }}
            </div>

            <div>
                Awe Print &middot; DTF Printing Services
            </div>

        </div>

    </footer>

</main>

</body>
</html>
