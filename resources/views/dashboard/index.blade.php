@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $totalSalesValue = (float) ($totalSales ?? 0);
    $totalExpenseValue = (float) ($totalExpense ?? 0);
    $profitValue = (float) ($profit ?? 0);
    $marginPercent = $totalSalesValue > 0 ? ($profitValue / $totalSalesValue) * 100 : 0;

    $periodText = isset($startDate, $endDate)
        ? \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y').' — '.\Carbon\Carbon::parse($endDate)->translatedFormat('d M Y')
        : '-';

    $chartMax = max(1, collect($monthly ?? [])->flatMap(fn ($m) => [$m['income'] ?? 0, $m['expense'] ?? 0])->max() ?? 1);
    $catMax = max(1, collect($expenseByCategory ?? [])->max('total') ?? 1);

    $statusMeta = [
        'masuk' => ['label' => 'Pesanan Masuk', 'icon' => 'o-inbox', 'tone' => 'blue'],
        'diambil' => ['label' => 'Diambil', 'icon' => 'o-hand-raised', 'tone' => 'cyan'],
        'diproses' => ['label' => 'Diproses', 'icon' => 'o-arrow-path', 'tone' => 'amber'],
        'dicetak' => ['label' => 'Dicetak', 'icon' => 'o-printer', 'tone' => 'violet'],
        'selesai' => ['label' => 'Selesai', 'icon' => 'o-check-circle', 'tone' => 'emerald'],
        'dibatalkan' => ['label' => 'Dibatalkan', 'icon' => 'o-x-circle', 'tone' => 'rose'],
    ];
@endphp

<div class="space-y-6 pb-8">
    {{-- HERO / FILTER --}}
    <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-[#253247] dark:bg-[#0D1625]">
        <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl dark:bg-blue-500/15"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-500/10"></div>

        <div class="relative p-5 sm:p-6 xl:p-7">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div class="max-w-2xl">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                            Finance Overview
                        </span>
                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white/80 px-3 py-1 text-[10px] font-semibold text-slate-500 dark:border-[#253247] dark:bg-[#111827]/70 dark:text-slate-400">
                            {{ $periodText }}
                        </span>
                    </div>

                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950 dark:text-white sm:text-4xl">
                        Dashboard Operasional
                    </h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Ringkasan keuangan, produksi, invoice, material, dan kualitas data dalam satu tampilan.
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-2 dark:bg-[#111827]">
                            <x-heroicon-o-clock class="h-4 w-4" />
                            Update {{ $dataUpdatedAt->translatedFormat('d M Y, H:i') }}
                        </span>
                        <a href="{{ route('dashboard', ['branch_id' => $branchId ?? 0, 'start_date' => $startDate ?? now()->startOfMonth()->toDateString(), 'end_date' => $endDate ?? now()->endOfMonth()->toDateString()]) }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 font-semibold text-slate-700 transition hover:border-blue-300 hover:text-blue-600 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-200 dark:hover:border-blue-500/40 dark:hover:text-blue-300">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            Refresh
                        </a>
                    </div>
                </div>

                <div class="grid w-full gap-2 sm:grid-cols-3 xl:w-auto xl:min-w-[420px]">
                    <a href="{{ route('orders.index', ['branch_id' => $branchId ?? 0]) }}" class="group rounded-2xl border border-slate-200 bg-white/80 p-3 transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md dark:border-[#253247] dark:bg-[#111827]/80 dark:hover:border-blue-500/40">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                                <x-heroicon-o-shopping-cart class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Quick Access</div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">Pesanan</div>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('ledger.index', ['branch_id' => $branchId ?? 0]) }}" class="group rounded-2xl border border-slate-200 bg-white/80 p-3 transition hover:-translate-y-0.5 hover:border-violet-300 hover:shadow-md dark:border-[#253247] dark:bg-[#111827]/80 dark:hover:border-violet-500/40">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">
                                <x-heroicon-o-book-open class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Quick Access</div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">Buku Besar</div>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('closing.index', ['branch_id' => $branchId ?? 0]) }}" class="group rounded-2xl border border-slate-200 bg-white/80 p-3 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-[#253247] dark:bg-[#111827]/80 dark:hover:border-emerald-500/40">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <x-heroicon-o-calculator class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Quick Access</div>
                                <div class="text-sm font-semibold text-slate-900 dark:text-white">Closing</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="mt-6 border-t border-slate-200/80 pt-5 dark:border-[#253247]">
                <form method="GET" action="{{ route('dashboard') }}" class="grid gap-3 lg:grid-cols-12 lg:items-end">
                    <input type="hidden" name="preset" value="{{ $preset ?? 'month' }}">

                    <div class="lg:col-span-3">
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">Cabang</label>
                        <select name="branch_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 dark:border-[#253247] dark:bg-[#111827] dark:text-white">
                            <option value="0" @selected((int) ($branchId ?? 0) === 0)>Semua Cabang</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) ($branchId ?? 0) === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="{{ $startDate ?? now()->startOfMonth()->toDateString() }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 dark:border-[#253247] dark:bg-[#111827] dark:text-white">
                    </div>

                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="{{ $endDate ?? now()->endOfMonth()->toDateString() }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10 dark:border-[#253247] dark:bg-[#111827] dark:text-white">
                    </div>

                    <div class="lg:col-span-5">
                        <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.15em] text-slate-500 dark:text-slate-400">Preset Periode</label>
                        <div class="flex flex-wrap gap-2">
                            @php
                                $headers = [
                                    ['label' => 'Hari Ini', 'value' => 'today'],
                                    ['label' => '7 Hari', 'value' => '7d'],
                                    ['label' => 'Bulan Ini', 'value' => 'month'],
                                    ['label' => 'Closing Terakhir', 'value' => 'last-closing'],
                                ];
                            @endphp
                            @foreach($headers as $option)
                                <button type="submit" name="preset" value="{{ $option['value'] }}"
                                    class="rounded-xl border px-3 py-2.5 text-xs font-semibold transition {{ ($preset ?? 'month') === $option['value'] ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-300 dark:hover:bg-[#172033]' }}">
                                    {{ $option['label'] }}
                                </button>
                            @endforeach
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-blue-600 dark:hover:bg-blue-500">
                                <x-heroicon-o-funnel class="h-4 w-4" />
                                Terapkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- KPI --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @php
            $stats = [
                ['label' => 'Total Penjualan', 'value' => 'Rp '.number_format($totalSalesValue, 0, ',', '.'), 'desc' => 'Pendapatan periode aktif', 'icon' => 'currency', 'tone' => 'emerald'],
                ['label' => 'Total Pengeluaran', 'value' => 'Rp '.number_format($totalExpenseValue, 0, ',', '.'), 'desc' => 'Semua biaya periode aktif', 'icon' => 'expense', 'tone' => 'rose'],
                ['label' => 'Laba Bersih', 'value' => 'Rp '.number_format($profitValue, 0, ',', '.'), 'desc' => 'Margin '.number_format($marginPercent, 1, ',', '.').'%', 'icon' => 'profit', 'tone' => $profitValue >= 0 ? 'blue' : 'rose'],
                ['label' => 'Total Meter', 'value' => number_format((float) ($totalMeters ?? 0), 2, ',', '.').' m', 'desc' => 'Produksi dalam periode', 'icon' => 'meter', 'tone' => 'amber'],
                ['label' => 'Jumlah Pesanan', 'value' => number_format((int) ($orderCount ?? 0), 0, ',', '.'), 'desc' => 'Pesanan valid periode aktif', 'icon' => 'order', 'tone' => 'violet'],
            ];
        @endphp

        @foreach($stats as $stat)
            @php
                $toneClasses = match($stat['tone']) {
                    'emerald' => ['ring' => 'ring-emerald-500/10', 'icon' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300', 'line' => 'bg-emerald-500'],
                    'rose' => ['ring' => 'ring-rose-500/10', 'icon' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300', 'line' => 'bg-rose-500'],
                    'amber' => ['ring' => 'ring-amber-500/10', 'icon' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', 'line' => 'bg-amber-500'],
                    'violet' => ['ring' => 'ring-violet-500/10', 'icon' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300', 'line' => 'bg-violet-500'],
                    default => ['ring' => 'ring-blue-500/10', 'icon' => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300', 'line' => 'bg-blue-500'],
                };
            @endphp
            <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 {{ $toneClasses['ring'] }} transition hover:-translate-y-0.5 hover:shadow-md dark:border-[#253247] dark:bg-[#111827]">
                <div class="absolute inset-x-0 top-0 h-0.5 {{ $toneClasses['line'] }}"></div>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 truncate text-[22px] font-bold tracking-tight text-slate-950 dark:text-white" title="{{ $stat['value'] }}">{{ $stat['value'] }}</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $toneClasses['icon'] }}">
                        @if($stat['icon'] === 'currency')
                            <x-heroicon-o-banknotes class="h-5 w-5" />
                        @elseif($stat['icon'] === 'expense')
                            <x-heroicon-o-arrow-trending-down class="h-5 w-5" />
                        @elseif($stat['icon'] === 'profit')
                            <x-heroicon-o-chart-bar-square class="h-5 w-5" />
                        @elseif($stat['icon'] === 'meter')
                            <x-heroicon-o-scale class="h-5 w-5" />
                        @else
                            <x-heroicon-o-shopping-bag class="h-5 w-5" />
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $stat['desc'] }}</p>
            </article>
        @endforeach
    </section>

    {{-- TREND + EXPENSE CATEGORY --}}
    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-[#253247]">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                            <x-heroicon-o-chart-bar class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Tren 6 Bulan</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Penjualan dibanding pengeluaran</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Penjualan</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>Pengeluaran</span>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <div class="relative h-56">
                    <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                        @for($i = 0; $i < 5; $i++)
                            <div class="border-t border-dashed border-slate-200 dark:border-[#253247]"></div>
                        @endfor
                    </div>
                    <div class="relative grid h-full grid-cols-6 gap-2 sm:gap-4">
                        @foreach($monthly as $m)
                            @php
                                $incomeHeight = max(5, round((($m['income'] ?? 0) / $chartMax) * 100));
                                $expenseHeight = max(5, round((($m['expense'] ?? 0) / $chartMax) * 100));
                            @endphp
                            <div class="flex min-w-0 flex-col justify-end">
                                <div class="flex h-[190px] items-end justify-center gap-1.5 sm:gap-2">
                                    <div class="group relative w-3 rounded-t-lg bg-gradient-to-t from-emerald-600 to-emerald-400 shadow-sm sm:w-5" style="height: {{ $incomeHeight }}%">
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-950 px-2 py-1 text-[10px] font-semibold text-white shadow-lg group-hover:block">
                                            Rp {{ number_format($m['income'] ?? 0,0,',','.') }}
                                        </div>
                                    </div>
                                    <div class="group relative w-3 rounded-t-lg bg-gradient-to-t from-rose-500 to-rose-300 shadow-sm sm:w-5" style="height: {{ $expenseHeight }}%">
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-950 px-2 py-1 text-[10px] font-semibold text-white shadow-lg group-hover:block">
                                            Rp {{ number_format($m['expense'] ?? 0,0,',','.') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 truncate text-center text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ $m['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300">
                    <x-heroicon-o-chart-pie class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Pengeluaran Terbesar</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Kategori dominan periode aktif</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                @forelse($expenseByCategory as $item)
                    @php $pct = max(4, round(($item->total / $catMax) * 100)); @endphp
                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item->category }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-400">{{ $pct }}% dari kategori terbesar</div>
                            </div>
                            <div class="shrink-0 text-right text-xs font-bold text-slate-700 dark:text-slate-200">Rp {{ number_format($item->total,0,',','.') }}</div>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-[#172033]">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-violet-500" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-[#253247] dark:text-slate-400">
                        Belum ada pengeluaran untuk periode ini.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ORDER STATUS + ACTION ITEMS --}}
    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 p-5 dark:border-[#253247]">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Status Pesanan</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Distribusi status untuk periode aktif</p>
                </div>
                <a href="{{ route('orders.index', ['branch_id' => $branchId ?? 0, 'date_start' => $startDate ?? now()->toDateString(), 'date_end' => $endDate ?? now()->toDateString()]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-300">
                    Lihat Semua
                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                </a>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($statusMeta as $key => $meta)
                    @php
                        $count = (int) ($orderStatusBreakdown[$key] ?? 0);
                        $statusValue = $key === 'selesai' ? 'completed' : ($key === 'dibatalkan' ? 'cancelled' : null);
                        $tone = match($meta['tone']) {
                            'emerald' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
                            'rose' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300',
                            'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
                            'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300',
                            'cyan' => 'bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-300',
                            default => 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300',
                        };
                    @endphp
                    <a href="{{ route('orders.index', ['branch_id' => $branchId ?? 0, 'status' => $statusValue, 'date_start' => $startDate ?? now()->toDateString(), 'date_end' => $endDate ?? now()->toDateString()]) }}"
                       class="group rounded-2xl border border-slate-200 bg-slate-50/70 p-4 transition hover:-translate-y-0.5 hover:border-blue-300 hover:bg-white hover:shadow-md dark:border-[#253247] dark:bg-[#0B1220] dark:hover:border-blue-500/30 dark:hover:bg-[#101b2d]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $tone }}">
                                @switch($key)
                                    @case('masuk') <x-heroicon-o-inbox class="h-5 w-5" /> @break
                                    @case('diambil') <x-heroicon-o-hand-raised class="h-5 w-5" /> @break
                                    @case('diproses') <x-heroicon-o-arrow-path class="h-5 w-5" /> @break
                                    @case('dicetak') <x-heroicon-o-printer class="h-5 w-5" /> @break
                                    @case('selesai') <x-heroicon-o-check-circle class="h-5 w-5" /> @break
                                    @default <x-heroicon-o-x-circle class="h-5 w-5" />
                                @endswitch
                            </div>
                            <x-heroicon-o-arrow-up-right class="h-4 w-4 text-slate-300 transition group-hover:text-blue-500 dark:text-slate-600" />
                        </div>
                        <div class="mt-4 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ number_format($count, 0, ',', '.') }}</div>
                        <div class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $meta['label'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center justify-between border-b border-slate-200 p-5 dark:border-[#253247]">
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Perlu Tindakan</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Prioritas yang perlu dicek</p>
                </div>
                @if(count($actionItems ?? []) > 0)
                    <span class="rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-600 dark:bg-rose-500/10 dark:text-rose-300">{{ count($actionItems) }} ITEM</span>
                @endif
            </div>

            <div class="space-y-3 p-5">
                @forelse($actionItems as $item)
                    @php
                        $tone = match($item['level'] ?? 'info') {
                            'critical' => 'border-rose-200 bg-rose-50/80 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300',
                            'urgent' => 'border-orange-200 bg-orange-50/80 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300',
                            'warning' => 'border-amber-200 bg-amber-50/80 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300',
                            default => 'border-sky-200 bg-sky-50/80 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300',
                        };
                    @endphp
                    <a href="{{ $item['route'] ?? route('dashboard') }}" class="block rounded-2xl border p-3.5 transition hover:-translate-y-0.5 hover:shadow-sm {{ $tone }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold">{{ $item['title'] }}</div>
                                <div class="mt-1 text-xs leading-5 opacity-80">{{ $item['description'] }}</div>
                            </div>
                            <span class="shrink-0 rounded-lg bg-white/70 px-2 py-1 text-xs font-black dark:bg-white/5">{{ $item['count'] }}</span>
                        </div>
                    </a>
                @empty
                    <div class="flex min-h-[150px] flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/50 p-5 text-center dark:border-emerald-500/20 dark:bg-emerald-500/5">
                        <x-heroicon-o-check-circle class="h-8 w-8 text-emerald-500" />
                        <div class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Semua aman</div>
                        <div class="mt-1 text-xs text-emerald-600/80 dark:text-emerald-400/80">Tidak ada item prioritas saat ini.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- INVOICE + MATERIAL + DATA QUALITY --}}
    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                    <x-heroicon-o-document-text class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Invoice & Piutang</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Status penagihan periode aktif</p>
                </div>
            </div>

            @php $invoiceSummary = $invoiceSummary ?? ['total' => 0, 'dibayar' => 0, 'belum_dibayar' => 0, 'draft' => 0, 'partial' => 0, 'paid' => 0]; @endphp
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([
                    ['label' => 'Total Invoice', 'value' => 'Rp '.number_format($invoiceSummary['total'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'blue'],
                    ['label' => 'Sudah Dibayar', 'value' => 'Rp '.number_format($invoiceSummary['dibayar'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'paid', 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'emerald'],
                    ['label' => 'Belum Dibayar', 'value' => 'Rp '.number_format($invoiceSummary['belum_dibayar'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'partial', 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'rose'],
                    ['label' => 'Draft', 'value' => (string) $invoiceSummary['draft'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'draft', 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'slate'],
                    ['label' => 'Dibayar Sebagian', 'value' => (string) $invoiceSummary['partial'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'partial', 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'amber'],
                    ['label' => 'Lunas', 'value' => (string) $invoiceSummary['paid'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'paid', 'date_start' => $startDate, 'date_end' => $endDate]), 'tone' => 'emerald'],
                ] as $metric)
                    <a href="{{ $metric['link'] }}" class="group rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 transition hover:border-blue-300 hover:bg-white hover:shadow-sm dark:border-[#253247] dark:bg-[#0B1220] dark:hover:border-blue-500/30">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ $metric['label'] }}</div>
                            <x-heroicon-o-arrow-up-right class="h-3.5 w-3.5 text-slate-300 transition group-hover:text-blue-500 dark:text-slate-600" />
                        </div>
                        <div class="mt-2 truncate text-base font-bold text-slate-950 dark:text-white" title="{{ $metric['value'] }}">{{ $metric['value'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300">
                    <x-heroicon-o-cube class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Status Material</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Stok yang perlu diperhatikan</p>
                </div>
            </div>

            <div class="mt-5 max-h-[320px] space-y-3 overflow-y-auto pr-1">
                @forelse($lowMaterials as $material)
                    @php
                        $stock = (float) $material->stock;
                        $minimum = max((float) $material->minimum_stock, 0.0001);
                        $stockPct = min(100, max(0, ($stock / $minimum) * 100));
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-3.5 dark:border-[#253247] dark:bg-[#0B1220]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $material->display_name ?? $material->name }}</div>
                                <div class="mt-0.5 text-[11px] text-slate-400">{{ $material->unit }}</div>
                            </div>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Menipis</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Stok {{ number_format($stock, 2, ',', '.') }}</span>
                            <span>Min. {{ number_format((float) $material->minimum_stock, 2, ',', '.') }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-[#172033]">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ number_format($stockPct, 2, '.', '') }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="flex min-h-[180px] flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/50 p-5 text-center dark:border-emerald-500/20 dark:bg-emerald-500/5">
                        <x-heroicon-o-shield-check class="h-8 w-8 text-emerald-500" />
                        <div class="mt-2 text-sm font-semibold text-emerald-700 dark:text-emerald-300">Stok aman</div>
                        <div class="mt-1 text-xs text-emerald-600/80 dark:text-emerald-400/80">Tidak ada material di bawah minimum.</div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="xl:col-span-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300">
                    <x-heroicon-o-shield-exclamation class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-950 dark:text-white">Kualitas Data</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Validasi otomatis data</p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                @forelse($dataQuality as $check)
                    @php
                        $qualityTone = $check['level'] === 'critical'
                            ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:border-rose-500/20'
                            : ($check['level'] === 'warning'
                                ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/20'
                                : 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:border-sky-500/20');
                    @endphp
                    <div class="rounded-2xl border p-3 {{ $qualityTone }}">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs font-semibold leading-5">{{ $check['title'] }}</span>
                            <span class="shrink-0 rounded-lg bg-white/70 px-2 py-0.5 text-[10px] font-black dark:bg-white/5">{{ $check['count'] }}</span>
                        </div>
                        <div class="mt-1 text-[11px] leading-4 opacity-80">{{ $check['message'] }}</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-xs text-slate-500 dark:border-[#253247] dark:text-slate-400">Tidak ada masalah data.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- BRANCH COMPARISON --}}
    @if(($branchSummary ?? []) !== [])
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-[#253247]">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">
                            <x-heroicon-o-arrows-right-left class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-950 dark:text-white">Perbandingan Cabang</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Snapshot closing terakhir masing-masing cabang</p>
                        </div>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Closing Snapshot
                </span>
            </div>

            <div class="grid gap-4 p-5 lg:grid-cols-2">
                @foreach($branchSummary as $branch)
                    @php
                        $branchIncome = (float) ($branch['income'] ?? 0);
                        $branchProfit = (float) ($branch['profit'] ?? 0);
                        $branchMargin = $branchIncome > 0 ? ($branchProfit / $branchIncome) * 100 : 0;
                    @endphp
                    <article class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5 dark:border-[#253247] dark:from-[#0B1220] dark:to-[#101b2d]">
                        <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-blue-500/5 blur-2xl dark:bg-blue-500/10"></div>
                        <div class="relative flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-slate-950 dark:text-white">{{ $branch['name'] }}</h3>
                                    @if(!empty($branch['is_locked']))
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Final</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-[11px] text-slate-400">
                                    {{ $branch['period_label'] ?? 'Closing terakhir' }}
                                </div>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-400">{{ $branch['code'] }}</div>
                        </div>

                        <div class="relative mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:bg-[#111827] dark:ring-[#253247]">
                                <div class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Omzet</div>
                                <div class="mt-1 truncate text-sm font-bold text-slate-950 dark:text-white" title="Rp {{ number_format($branchIncome,0,',','.') }}">Rp {{ number_format($branchIncome,0,',','.') }}</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:bg-[#111827] dark:ring-[#253247]">
                                <div class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Laba Bersih</div>
                                <div class="mt-1 truncate text-sm font-bold {{ $branchProfit >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-rose-600 dark:text-rose-300' }}" title="Rp {{ number_format($branchProfit,0,',','.') }}">Rp {{ number_format($branchProfit,0,',','.') }}</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:bg-[#111827] dark:ring-[#253247]">
                                <div class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Total Meter</div>
                                <div class="mt-1 text-sm font-bold text-slate-950 dark:text-white">{{ number_format((float) ($branch['meters'] ?? 0),2,',','.') }} m</div>
                            </div>
                            <div class="rounded-xl bg-white p-3 ring-1 ring-slate-200 dark:bg-[#111827] dark:ring-[#253247]">
                                <div class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Margin</div>
                                <div class="mt-1 text-sm font-bold text-blue-600 dark:text-blue-300">{{ number_format($branchMargin,1,',','.') }}%</div>
                            </div>
                        </div>

                        <div class="relative mt-4 divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white/80 px-3 dark:divide-[#253247] dark:border-[#253247] dark:bg-[#111827]/80">
                            <div class="flex items-center justify-between gap-3 py-2.5 text-xs"><span class="text-slate-500 dark:text-slate-400">Pengeluaran</span><span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($branch['expense'] ?? 0,0,',','.') }}</span></div>
                            <div class="flex items-center justify-between gap-3 py-2.5 text-xs"><span class="text-slate-500 dark:text-slate-400">Saldo Tahanan</span><span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($branch['saldo_tahanan'] ?? 0,0,',','.') }}</span></div>
                            <div class="flex items-center justify-between gap-3 py-2.5 text-xs"><span class="text-slate-500 dark:text-slate-400">Saldo Realtime</span><span class="font-bold text-slate-800 dark:text-slate-100">Rp {{ number_format($branch['saldo_realtime'] ?? 0,0,',','.') }}</span></div>
                            <div class="flex items-center justify-between gap-3 py-2.5 text-xs"><span class="text-slate-500 dark:text-slate-400">Selisih</span><span class="font-black {{ ($branch['selisih'] ?? 0) < 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">Rp {{ number_format($branch['selisih'] ?? 0,0,',','.') }}</span></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if(count($branchSummary) >= 2)
            @php
                $comparisonBranchA = $branchSummary[0];
                $comparisonBranchB = $branchSummary[1];
                $comparisonMetrics = [
                    ['label' => 'Omzet', 'key' => 'income', 'type' => 'currency'],
                    ['label' => 'Pengeluaran', 'key' => 'expense', 'type' => 'currency'],
                    ['label' => 'Laba Bersih', 'key' => 'profit', 'type' => 'currency'],
                    ['label' => 'Total Meter', 'key' => 'meters', 'type' => 'meter'],
                ];
            @endphp

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-950 dark:text-white">Grafik Perbandingan</h2>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Visual perbedaan performa closing terakhir</p>
                    </div>
                    <div class="flex flex-wrap gap-3 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>{{ $comparisonBranchA['name'] }}</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>{{ $comparisonBranchB['name'] }}</span>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach($comparisonMetrics as $metric)
                        @php
                            $valueA = (float) ($comparisonBranchA[$metric['key']] ?? 0);
                            $valueB = (float) ($comparisonBranchB[$metric['key']] ?? 0);
                            $maxValue = max(abs($valueA), abs($valueB), 1);
                            $widthA = min(100, (abs($valueA) / $maxValue) * 100);
                            $widthB = min(100, (abs($valueB) / $maxValue) * 100);
                        @endphp
                        <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 dark:border-[#253247] dark:bg-[#0B1220]">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <h3 class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</h3>
                                @if($valueA !== $valueB)
                                    @php
                                        $diff = abs($valueA - $valueB);
                                    @endphp
                                    <span class="text-[10px] font-semibold text-slate-400">
                                        Δ {{ $metric['type'] === 'currency' ? 'Rp '.number_format($diff,0,',','.') : number_format($diff,2,',','.').' m' }}
                                    </span>
                                @endif
                            </div>

                            <div class="space-y-4">
                                @foreach([
                                    ['branch' => $comparisonBranchA, 'value' => $valueA, 'width' => $widthA, 'bar' => 'from-blue-600 to-blue-400'],
                                    ['branch' => $comparisonBranchB, 'value' => $valueB, 'width' => $widthB, 'bar' => 'from-emerald-600 to-emerald-400'],
                                ] as $row)
                                    <div>
                                        <div class="mb-1.5 flex items-center justify-between gap-3 text-[11px]">
                                            <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $row['branch']['name'] }}</span>
                                            <span class="font-bold text-slate-800 dark:text-slate-100">
                                                @if($metric['type'] === 'currency')
                                                    Rp {{ number_format($row['value'],0,',','.') }}
                                                @else
                                                    {{ number_format($row['value'],2,',','.') }} m
                                                @endif
                                            </span>
                                        </div>
                                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-200 dark:bg-[#172033]">
                                            <div class="h-full rounded-full bg-gradient-to-r {{ $row['bar'] }}" style="width: {{ number_format($row['width'],2,'.','') }}%"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
