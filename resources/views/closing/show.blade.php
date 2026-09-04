@extends('layouts.app')

@section('title', 'Closing Detail')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Closing {{ \Carbon\Carbon::create()->month($closing->month)->translatedFormat('F') }} {{ $closing->year }}</h1>
                @if(isset($branch))
                    <span class="inline-flex items-center gap-2 rounded-full bg-[#F1F5F9] px-3 py-1 text-xs font-semibold text-[#0F172A] dark:bg-[#172033] dark:text-[#F8FAFC]">
                        <x-heroicon-o-map class="h-4 w-4" /> {{ $branch->name }}
                    </span>
                @endif
            </div>
            <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">Patokan HPP: Pemakaian = Stock Awal + Pembelian (Qty) − Stock Akhir</p>
        </div>

        <div class="flex items-center gap-2">
            @if($closing->is_locked)
                <span class="inline-flex rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-xs font-bold dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                    Terkunci sejak {{ $closing->locked_at?->translatedFormat('d M Y H:i') }}
                </span>
            @else
                <form method="POST" action="{{ route('closing.lock') }}" onsubmit="return confirm('Kunci closing bulan ini? Stok material akan diperbarui sesuai Stock Akhir dan tidak bisa diedit lagi.');">
                    @csrf
                    <input type="hidden" name="month" value="{{ $closing->month }}">
                    <input type="hidden" name="year" value="{{ $closing->year }}">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition dark:bg-amber-500 dark:hover:bg-amber-600">
                        <x-heroicon-o-lock-closed class="h-4 w-4" /> Kunci Closing
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('message'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Hasil Cetak</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">{{ number_format($hpp['hasilCetak'],2,',','.') }} m</p>
                </div>
                <div class="app-icon bg-slate-100 text-slate-600 dark:bg-[#172033] dark:text-slate-300">
                    <x-heroicon-o-clipboard-document-list class="h-6 w-6"/>
                </div>
            </div>
        </div>
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Total HPP <span class="normal-case font-normal">(info)</span></p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($closing->hpp,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-slate-100 text-slate-600 dark:bg-[#172033] dark:text-slate-300">
                    <x-heroicon-o-currency-dollar class="h-6 w-6"/>
                </div>
            </div>
        </div>
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">HPP / Meter</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($closing->hpp_per_meter,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-slate-100 text-slate-600 dark:bg-[#172033] dark:text-slate-300">
                    <x-heroicon-o-scale class="h-6 w-6"/>
                </div>
            </div>
        </div>
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Laba/Rugi</p>
                    <p class="mt-2 text-2xl font-bold {{ $profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">Rp {{ number_format($profit,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-slate-100 text-slate-600 dark:bg-[#172033] dark:text-slate-300">
                    <x-heroicon-o-chart-bar class="h-6 w-6"/>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 1: INVOICES (Pemasukan) --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="p-5 border-b border-[#E2E8F0] dark:border-[#253247]">
            <h2 class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Pemasukan (Invoice)</h2>
            <p class="text-xs text-[#64748B] mt-1 dark:text-[#94A3B8]">Total: Rp {{ number_format($totalIncome,0,',','.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                    <tr>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Tanggal</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Customer</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">No. Invoice</th>
                        <th class="p-3 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Total</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] dark:border-[#253247] dark:hover:bg-[#172033]">
                            <td class="p-3 text-[#64748B] dark:text-[#94A3B8]">{{ $invoice->date->format('Y-m-d') }}</td>
                            <td class="p-3 text-[#0F172A] font-medium dark:text-[#F8FAFC]">{{ $invoice->customer->name ?? '-' }}</td>
                            <td class="p-3 text-[#64748B] dark:text-[#94A3B8]">{{ $invoice->invoice_number ?? '-' }}</td>
                            <td class="p-3 text-right font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($invoice->total,0,',','.') }}</td>
                            <td class="p-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $invoice->status == 'paid' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' }}">
                                    {{ ucfirst($invoice->status ?? 'unpaid') }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-[#94A3B8] dark:text-[#64748B]">Tidak ada invoice bulan ini</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- SECTION 2: EXPENSES (Pengeluaran) --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="p-5 border-b border-[#E2E8F0] dark:border-[#253247]">
            <h2 class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Pengeluaran (Expenses)</h2>
            <p class="text-xs text-[#64748B] mt-1 dark:text-[#94A3B8]">Total (semua kategori, termasuk Bahan Baku): Rp {{ number_format($totalExpense,0,',','.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                    <tr>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Tanggal</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Kategori</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Deskripsi</th>
                        <th class="p-3 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Total</th>
                        <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] dark:border-[#253247] dark:hover:bg-[#172033]">
                            <td class="p-3 text-[#64748B] dark:text-[#94A3B8]">{{ $expense->date->format('Y-m-d') }}</td>
                            <td class="p-3">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                    {{ $expense->category }}
                                </span>
                            </td>
                            <td class="p-3 text-[#0F172A] font-medium dark:text-[#F8FAFC]">{{ $expense->material?->display_name ?? $expense->description }}</td>
                            <td class="p-3 text-right font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($expense->amount,0,',','.') }}</td>
                            <td class="p-3 text-[#64748B] dark:text-[#94A3B8]">{{ $expense->payment_method ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-[#94A3B8] dark:text-[#64748B]">Tidak ada pengeluaran bulan ini</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- SECTION 3: SALDO & SELISIH --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <h2 class="text-lg font-bold text-[#0F172A] mb-4 dark:text-[#F8FAFC]">Saldo & Selisih</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Saldo Tahanan</p>
                <p class="mt-1 text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($saldoTahanan,0,',','.') }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Remaining Material</p>
                <p class="mt-1 text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($remainingMaterial,0,',','.') }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Saldo Tahanan Sisa</p>
                <p class="mt-1 text-lg font-bold {{ $saldoTahananSisa >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    Rp {{ number_format($saldoTahananSisa,0,',','.') }}
                </p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Sisa Saldo (Profit + Saldo Tahanan Sisa)</p>
                <p class="mt-1 text-lg font-bold {{ $sisaSaldo >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    Rp {{ number_format($sisaSaldo,0,',','.') }}
                </p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Saldo Realtime</p>
                <p class="mt-1 text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($saldoRealtime,0,',','.') }}</p>
            </div>
            <div class="rounded-xl bg-[#F8FAFC] p-4 dark:bg-[#0B1220]">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Selisih (Realtime - Sisa Saldo)</p>
                <p class="mt-1 text-lg font-bold {{ $selisih >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    Rp {{ number_format($selisih,0,',','.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 4: STOCK OPNAME (SO) BAHAN BAKU --}}
    @php
        $soTotal = $materialRows->sum(
            fn ($row) => (float) $row['stock_akhir'] * (float) $row['unit_cost']
        );
    @endphp

    <div id="stock-opname" class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="flex flex-col gap-3 border-b border-[#E2E8F0] p-5 sm:flex-row sm:items-center sm:justify-between dark:border-[#253247]">
            <div>
                <h2 class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Stock Opname Bahan Baku</h2>
                <p class="mt-1 text-xs text-[#64748B] dark:text-[#94A3B8]">
                    Satuan diambil dari Stock Akhir material. Total = Satuan × Harga Manual.
                </p>
            </div>

            <div class="rounded-xl bg-[#F8FAFC] px-4 py-3 text-right dark:bg-[#0B1220]">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Total Nilai SO</p>
                <p id="so-grand-total" class="mt-1 text-xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">
                    Rp {{ number_format($soTotal, 0, ',', '.') }}
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('closing.update-stock-akhir') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $closing->month }}">
            <input type="hidden" name="year" value="{{ $closing->year }}">

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-[#E2E8F0] bg-[#F8FAFC] text-left dark:border-[#253247] dark:bg-[#0B1220]">
                        <tr>
                            <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Stok Bahan Baku</th>
                            <th class="p-4 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Satuan</th>
                            <th class="p-4 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Harga</th>
                            <th class="p-4 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($materialRows as $row)
                            @php
                                $stockAkhir = (float) $row['stock_akhir'];
                                $hargaSo = (float) $row['unit_cost'];
                                $totalSo = $stockAkhir * $hargaSo;
                            @endphp

                            <tr class="so-row border-b border-[#E2E8F0] hover:bg-[#F8FAFC] dark:border-[#253247] dark:hover:bg-[#172033]"
                                data-stock="{{ $stockAkhir }}">
                                <td class="p-4 font-medium text-[#0F172A] dark:text-[#F8FAFC]">
                                    {{ $row['material']->display_name ?? 'N/A' }}
                                </td>
                                <td class="p-4 text-right text-[#64748B] dark:text-[#94A3B8]">
                                    {{ number_format($stockAkhir, 2, ',', '.') }}
                                    {{ $row['material']->unit ?? '' }}
                                    <input type="hidden" name="stock_akhir[{{ $row['id'] }}]" value="{{ $stockAkhir }}">
                                </td>
                                <td class="p-4 text-right">
                                    @if(!$closing->is_locked)
                                        <div class="ml-auto flex w-44 items-center overflow-hidden rounded-xl border border-[#E2E8F0] bg-white focus-within:border-[#2563EB] focus-within:ring-2 focus-within:ring-[#2563EB]/20 dark:border-[#253247] dark:bg-[#111827]">
                                            <span class="px-3 text-xs font-semibold text-[#64748B] dark:text-[#94A3B8]">Rp</span>
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                name="harga_komponen[{{ $row['id'] }}]"
                                                value="{{ $hargaSo }}"
                                                class="so-price-input w-full border-0 bg-transparent px-3 py-2 text-right text-sm font-semibold text-[#0F172A] outline-none dark:text-[#F8FAFC]"
                                            >
                                        </div>
                                    @else
                                        <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">
                                            Rp {{ number_format($hargaSo, 0, ',', '.') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="so-row-total p-4 text-right font-bold text-[#0F172A] dark:text-[#F8FAFC]">
                                    Rp {{ number_format($totalSo, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-[#94A3B8] dark:text-[#64748B]">
                                    Belum ada data material. Generate closing terlebih dahulu.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#0F172A] bg-[#F8FAFC] dark:border-[#F8FAFC] dark:bg-[#0B1220]">
                            <td colspan="3" class="p-4 font-bold text-[#0F172A] dark:text-[#F8FAFC]">Total Stock Opname</td>
                            <td id="so-footer-total" class="p-4 text-right font-bold text-[#0F172A] dark:text-[#F8FAFC]">
                                Rp {{ number_format($soTotal, 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if(!$closing->is_locked && $materialRows->isNotEmpty())
                <div class="flex justify-end border-t border-[#E2E8F0] p-4 dark:border-[#253247]">
                    <button type="submit" class="rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#1D4ED8] dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                        Simpan Harga SO
                    </button>
                </div>
            @endif
        </form>
    </div>

    @if(!$closing->is_locked)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const formatRupiah = (value) =>
                    'Rp ' + new Intl.NumberFormat('id-ID', {
                        maximumFractionDigits: 0,
                    }).format(value);

                const recalculateSo = () => {
                    let grandTotal = 0;

                    document.querySelectorAll('#stock-opname .so-row').forEach((row) => {
                        const stock = Number.parseFloat(row.dataset.stock || '0') || 0;
                        const priceInput = row.querySelector('.so-price-input');
                        const price = Number.parseFloat(priceInput?.value || '0') || 0;
                        const total = stock * price;

                        grandTotal += total;
                        row.querySelector('.so-row-total').textContent = formatRupiah(total);
                    });

                    document.getElementById('so-grand-total').textContent = formatRupiah(grandTotal);
                    document.getElementById('so-footer-total').textContent = formatRupiah(grandTotal);
                };

                document.querySelectorAll('#stock-opname .so-price-input').forEach((input) => {
                    input.addEventListener('input', recalculateSo);
                });
            });
        </script>
    @endif

    {{-- SECTION 5: HPP COMPONENT TABLE (Material Bahan Baku) --}}
    @include('closing._material_components', [
        'materialRows' => $materialRows,
        'materialValue' => $materialValue,
        'closing' => $closing,
        'hpp' => $hpp,
    ])

    {{-- SECTION 6: HPP PER METER DETAIL --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="p-5 border-b border-[#E2E8F0] dark:border-[#253247]">
            <h2 class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rincian HPP / Meter</h2>
            <p class="text-xs text-[#64748B] mt-1 dark:text-[#94A3B8]">
                Hasil Cetak: {{ number_format($hpp['hasilCetak'],2,',','.') }} m
                (dari total qty invoice) &middot; <span class="italic">Metrik informasi, tidak memengaruhi Laba/Rugi</span>
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                    <tr>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Komponen</th>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Basis</th>
                        <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Total Harga</th>
                        <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Rp / Meter</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hpp['allRows'] as $row)
                        <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] dark:border-[#253247] dark:hover:bg-[#172033]">
                            <td class="p-4 text-[#0F172A] font-medium dark:text-[#F8FAFC]">{{ $row['nama'] }}</td>
                            <td class="p-4 text-[#64748B] text-sm dark:text-[#94A3B8]">{{ $row['basis'] }}</td>
                            <td class="p-4 text-right font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($row['totalHarga'],0,',','.') }}</td>
                            <td class="p-4 text-right text-[#64748B] dark:text-[#94A3B8]">Rp {{ number_format($row['rpMeter'],0,',','.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-[#94A3B8] dark:text-[#64748B]">Belum ada data HPP</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[#0F172A] bg-[#F8FAFC] dark:border-[#F8FAFC] dark:bg-[#0B1220]">
                        <td colspan="3" class="p-4 font-bold text-[#0F172A] dark:text-[#F8FAFC]">Total HPP / Meter</td>
                        <td class="p-4 text-right font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($hpp['totalRpMeter'],0,',','.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- SECTION 7: MANUAL DATA --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <p class="text-sm font-semibold text-[#0F172A] mb-4 dark:text-[#F8FAFC]">Data Manual Closing</p>
        @php
            $manualFields = [
                'operasional' => 'Operasional',
                'gaji_karyawan' => 'Gaji Karyawan',
                'teknisi_mesin' => 'Teknisi Mesin',
                'lain_lain' => 'Lain-lain',
                'saldo_tahanan' => 'Saldo Tahanan',
                'saldo_realtime' => 'Saldo Realtime',
            ];
        @endphp

        @if(!($pdfView ?? false))
            <form method="POST" action="{{ route('closing.settings') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @csrf
                <input type="hidden" name="month" value="{{ $closing->month }}">
                <input type="hidden" name="year" value="{{ $closing->year }}">

                @foreach($manualFields as $field => $label)
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">{{ $label }}</label>
                        <input type="number" step=".01" name="{{ $field }}" value="{{ $closing->{$field} }}" @disabled($closing->is_locked)
                               class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition disabled:bg-[#F8FAFC] disabled:text-[#94A3B8] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:disabled:bg-[#0B1220] dark:disabled:text-[#64748B]">
                    </div>
                @endforeach

                @if(!$closing->is_locked)
                    <div class="sm:col-span-2 lg:col-span-3">
                        <button type="submit" class="rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#1D4ED8] transition dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                            Simpan Data Manual
                        </button>
                    </div>
                @endif
            </form>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($manualFields as $field => $label)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">{{ $label }}</p>
                        <p class="mt-1 text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($closing->{$field} ?? 0,0,',','.') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- SECTION 7: PROFIT SUMMARY --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <p class="text-sm font-semibold text-[#0F172A] mb-4 dark:text-[#F8FAFC]">Ringkasan Laba/Rugi</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">Total Pemasukan (Invoice)</span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalIncome, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">Saldo Tahanan</span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($saldoTahanan, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">Saldo Realtime</span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($saldoRealtime, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm pt-2 border-t border-[#E2E8F0] dark:border-[#253247]">
                    <span class="font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Total Pemasukan + Saldo</span>
                    <span class="font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalIncome + $saldoTahanan + $saldoRealtime, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">Total Pengeluaran (semua kategori)</span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalExpense, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">Remaining Material</span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($remainingMaterial, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-[#64748B] dark:text-[#94A3B8]">HPP / Meter <span class="text-[10px] italic">(info)</span></span>
                    <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($closing->hpp_per_meter, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm pt-2 border-t border-[#E2E8F0] dark:border-[#253247]">
                    <span class="font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Total HPP <span class="text-[10px] font-normal italic">(info, tidak memengaruhi laba)</span></span>
                    <span class="font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($closing->hpp, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t-2 border-[#0F172A] dark:border-[#F8FAFC]">
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Laba/Rugi Bersih (Pemasukan − Pengeluaran)</span>
                <span class="text-2xl font-bold {{ $profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                    Rp {{ number_format($profit, 0, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
@endsection
