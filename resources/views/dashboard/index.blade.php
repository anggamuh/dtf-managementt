@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div class="space-y-1.5">
                <p class="text-[10px] font-semibold uppercase tracking-[0.22em] text-[#2563EB] dark:text-[#60A5FA]">Finance Overview</p>
                <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Dashboard</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#64748B] dark:text-[#94A3B8]">Pantau keuangan, order, material, invoice, dan masalah kualitas data dari satu halaman yang lebih operasional.</p>
            </div>

            <div class="w-full max-w-4xl">
                <form method="GET" action="{{ route('dashboard') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <input type="hidden" name="branch_id" value="{{ $branchId ?? 0 }}">
                    <input type="hidden" name="preset" value="{{ $preset ?? 'month' }}">
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Cabang</label>
                        <select name="branch_id" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm text-[#0F172A] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                            <option value="0" @selected((int) ($branchId ?? 0) === 0)>Semua Cabang</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) ($branchId ?? 0) === (int) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Tanggal Mulai</label>
                        <input type="date" name="start_date" value="{{ $startDate ?? now()->startOfMonth()->toDateString() }}" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm text-[#0F172A] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Tanggal Akhir</label>
                        <input type="date" name="end_date" value="{{ $endDate ?? now()->endOfMonth()->toDateString() }}" class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm text-[#0F172A] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                    </div>
                    <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-2">
                        @php $headers = [['label'=>'Hari Ini','value'=>'today'],['label'=>'7 Hari','value'=>'7d'],['label'=>'Bulan Ini','value'=>'month'],['label'=>'Closing Terakhir','value'=>'last-closing']]; @endphp
                        @foreach($headers as $option)
                            <button type="submit" name="preset" value="{{ $option['value'] }}" class="rounded-xl border px-3 py-2 text-xs font-semibold {{ ($preset ?? 'month') === $option['value'] ? 'border-[#2563EB] bg-[#2563EB]/10 text-[#2563EB] dark:border-[#60A5FA] dark:bg-[#60A5FA]/10 dark:text-[#93C5FD]' : 'border-[#E2E8F0] bg-white text-[#0F172A] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]' }}">
                                {{ $option['label'] }}
                            </button>
                        @endforeach
                        <button type="submit" class="rounded-xl bg-[#0F172A] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1E293B] dark:bg-[#111827] dark:text-[#F8FAFC]">Terapkan</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <div class="flex items-center justify-between gap-3">
        <div class="text-sm text-[#64748B] dark:text-[#94A3B8]">Data terakhir diperbarui: {{ $dataUpdatedAt->translatedFormat('d F Y, H:i') }}</div>
        <a href="{{ route('dashboard', ['branch_id' => $branchId ?? 0, 'start_date' => $startDate ?? now()->startOfMonth()->toDateString(), 'end_date' => $endDate ?? now()->endOfMonth()->toDateString()]) }}" class="inline-flex items-center rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-semibold text-[#0F172A] hover:bg-[#F8FAFC] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">Refresh data</a>
    </div>

    <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Total Penjualan</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalSales ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"><x-heroicon-o-currency-dollar class="h-6 w-6"/></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[#64748B] dark:text-[#94A3B8]">Pendapatan selama periode yang dipilih.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Total Pengeluaran</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalExpense ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400"><x-heroicon-o-arrow-down-tray class="h-6 w-6"/></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[#64748B] dark:text-[#94A3B8]">Biaya operasional serta pengeluaran lainnya.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Laba Bersih</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight {{ ($profit ?? 0) >= 0 ? 'text-[#0F172A] dark:text-[#F8FAFC]' : 'text-rose-600 dark:text-rose-400' }}">Rp {{ number_format($profit ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400"><x-heroicon-o-arrow-up-right class="h-6 w-6"/></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[#64748B] dark:text-[#94A3B8]">Penjualan dikurangi pengeluaran periode ini.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Total Meter</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">{{ number_format($totalMeters ?? 0, 2, ',', '.') }} m</p>
                </div>
                <div class="app-icon bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400"><x-heroicon-o-scale class="h-6 w-6"/></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[#64748B] dark:text-[#94A3B8]">Total qty/meter aktif dalam periode ini.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Pesanan Bulan Ini</p>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">{{ $orderCount ?? 0 }}</p>
                </div>
                <div class="app-icon bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400"><x-heroicon-o-shopping-cart class="h-6 w-6"/></div>
            </div>
            <p class="mt-3 text-xs leading-5 text-[#64748B] dark:text-[#94A3B8]">Pesanan valid untuk periode yang dipilih.</p>
        </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8 rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Tren 6 Bulan</h2>
                    <p class="mt-0.5 text-sm text-[#64748B] dark:text-[#94A3B8]">Perbandingan pendapatan dan pengeluaran.</p>
                </div>
                <div class="hidden items-center gap-4 text-xs text-[#64748B] dark:text-[#94A3B8] sm:flex">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Penjualan</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>Pengeluaran</span>
                </div>
            </div>
            @php $chartMax = max(1, collect($monthly)->flatMap(fn($m) => [$m['income'], $m['expense']])->max()); @endphp
            <div class="mt-8 grid grid-cols-6 gap-3 sm:gap-5">
                @foreach($monthly as $m)
                    <div class="flex flex-col items-center gap-2">
                        <div class="flex h-40 w-full items-end justify-center gap-1">
                            <div class="w-2.5 rounded-t-md bg-emerald-500 sm:w-3.5" style="height: {{ max(8, round(($m['income'] / $chartMax) * 100)) }}%" title="Penjualan: Rp {{ number_format($m['income'],0,',','.') }}"></div>
                            <div class="w-2.5 rounded-t-md bg-rose-400 sm:w-3.5" style="height: {{ max(8, round(($m['expense'] / $chartMax) * 100)) }}%" title="Pengeluaran: Rp {{ number_format($m['expense'],0,',','.') }}"></div>
                        </div>
                        <span class="text-xs font-medium text-[#64748B] dark:text-[#94A3B8]">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Pengeluaran per Kategori</h2>
            <p class="mt-0.5 text-sm text-[#64748B] dark:text-[#94A3B8]">Kategori terbesar periode ini.</p>
            <div class="mt-6 space-y-4">
                @php $catMax = max(1, collect($expenseByCategory)->max('total')); @endphp
                @forelse($expenseByCategory as $item)
                    @php $pct = max(8, round(($item->total / $catMax) * 100)); @endphp
                    <div>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">{{ $item->category }}</span>
                            <span class="text-[#64748B] dark:text-[#94A3B8]">Rp {{ number_format($item->total,0,',','.') }}</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-[#F1F5F9] dark:bg-[#172033]">
                            <div class="h-full rounded-full bg-[#2563EB] dark:bg-[#3B82F6]" style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#E2E8F0] p-5 text-sm text-[#64748B] dark:border-[#253247] dark:text-[#94A3B8]">Belum ada data pengeluaran untuk periode ini.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex items-center justify-between gap-3 border-b border-[#E2E8F0] p-5 dark:border-[#253247]">
                <div>
                    <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Pesanan Hari Ini</h2>
                    <p class="mt-0.5 text-sm text-[#64748B] dark:text-[#94A3B8]">Ringkasan order berdasarkan status.</p>
                </div>
                <a href="{{ route('orders.index', ['branch_id' => $branchId ?? 0, 'date_start' => $startDate ?? now()->toDateString(), 'date_end' => $endDate ?? now()->toDateString()]) }}" class="text-sm font-medium text-[#2563EB] dark:text-[#60A5FA]">Lihat semua</a>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach(['masuk' => 'Pesanan masuk', 'diambil' => 'Diambil', 'diproses' => 'Diproses', 'dicetak' => 'Dicetak', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'] as $key => $label)
                    @php $count = $orderStatusBreakdown[$key] ?? 0; @endphp
                    <a href="{{ route('orders.index', ['branch_id' => $branchId ?? 0, 'status' => $key === 'masuk' ? null : ($key === 'selesai' ? 'completed' : ($key === 'dibatalkan' ? 'cancelled' : null)), 'date_start' => $startDate ?? now()->toDateString(), 'date_end' => $endDate ?? now()->toDateString()]) }}" class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 transition hover:border-[#2563EB]/30 hover:bg-[#EFF6FF] dark:border-[#253247] dark:bg-[#0B1220] dark:hover:bg-[#172033]">
                        <div class="text-xs uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">{{ $label }}</div>
                        <div class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">{{ $count }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="border-b border-[#E2E8F0] p-5 dark:border-[#253247]">
                <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Perlu Tindakan</h2>
                <p class="mt-0.5 text-sm text-[#64748B] dark:text-[#94A3B8]">Item yang butuh perhatian segera.</p>
            </div>
            <div class="space-y-3 p-5">
                @forelse($actionItems as $item)
                    @php
                        $tone = ['critical' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300', 'urgent' => 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-300', 'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300', 'info' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-300'];
                    @endphp
                    <a href="{{ $item['route'] ?? route('dashboard') }}" class="block rounded-xl border p-3 {{ $tone[$item['level']] ?? 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-500/20 dark:bg-slate-500/10 dark:text-slate-300' }}">
                        <div class="flex items-center justify-between gap-3">
                            <strong class="text-sm font-semibold">{{ $item['title'] }}</strong>
                            <span class="rounded-full bg-white/70 px-2 py-0.5 text-xs font-bold">{{ $item['count'] }}</span>
                        </div>
                        <div class="mt-1 text-xs opacity-90">{{ $item['description'] }}</div>
                    </a>
                @empty
                    <div class="rounded-xl border border-dashed border-[#E2E8F0] p-5 text-sm text-[#64748B] dark:border-[#253247] dark:text-[#94A3B8]">Tidak ada item yang perlu ditindaklanjuti.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-12">
        <div class="xl:col-span-5 rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Invoice & Piutang</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @php $invoiceSummary = $invoiceSummary ?? ['total' => 0, 'dibayar' => 0, 'belum_dibayar' => 0, 'draft' => 0, 'partial' => 0, 'paid' => 0]; @endphp
                @foreach([
                    ['label' => 'Total Invoice', 'value' => 'Rp '.number_format($invoiceSummary['total'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'date_start' => $startDate, 'date_end' => $endDate])],
                    ['label' => 'Sudah Dibayar', 'value' => 'Rp '.number_format($invoiceSummary['dibayar'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'paid', 'date_start' => $startDate, 'date_end' => $endDate])],
                    ['label' => 'Belum Dibayar', 'value' => 'Rp '.number_format($invoiceSummary['belum_dibayar'],0,',','.'), 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'partial', 'date_start' => $startDate, 'date_end' => $endDate])],
                    ['label' => 'Draft', 'value' => (string) $invoiceSummary['draft'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'draft', 'date_start' => $startDate, 'date_end' => $endDate])],
                    ['label' => 'Dibayar Sebagian', 'value' => (string) $invoiceSummary['partial'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'partial', 'date_start' => $startDate, 'date_end' => $endDate])],
                    ['label' => 'Lunas', 'value' => (string) $invoiceSummary['paid'], 'link' => route('invoices.index', ['branch_id' => $branchId ?? 0, 'status' => 'paid', 'date_start' => $startDate, 'date_end' => $endDate])]
                ] as $metric)
                    <a href="{{ $metric['link'] }}" class="block rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 dark:border-[#253247] dark:bg-[#0B1220]">
                        <div class="text-[10px] uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">{{ $metric['label'] }}</div>
                        <div class="mt-2 text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">{{ $metric['value'] }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Status Material</h2>
            <p class="mt-0.5 text-sm text-[#64748B] dark:text-[#94A3B8]">Material yang sudah menipis atau hampir habis.</p>
            <div class="mt-4 space-y-3">
                @forelse($lowMaterials as $material)
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 dark:border-[#253247] dark:bg-[#0B1220]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $material->display_name ?? $material->name }}</div>
                                <div class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $material->unit }}</div>
                            </div>
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Menipis</span>
                        </div>
                        <div class="mt-2 text-xs text-[#64748B] dark:text-[#94A3B8]">Stok {{ number_format((float) $material->stock, 2, ',', '.') }} / minimum {{ number_format((float) $material->minimum_stock, 2, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#E2E8F0] p-5 text-sm text-[#64748B] dark:border-[#253247] dark:text-[#94A3B8]">Semua material dalam kondisi aman.</div>
                @endforelse
            </div>
        </div>

        <div class="xl:col-span-3 rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Kualitas Data</h2>
            <div class="mt-4 space-y-3">
                @forelse($dataQuality as $check)
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 dark:border-[#253247] dark:bg-[#0B1220]">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-[#0F172A] dark:text-[#F8FAFC]">{{ $check['title'] }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] {{ $check['level'] === 'critical' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' : ($check['level'] === 'warning' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300') }}">{{ $check['count'] }}</span>
                        </div>
                        <div class="mt-1 text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $check['message'] }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#E2E8F0] p-5 text-sm text-[#64748B] dark:border-[#253247] dark:text-[#94A3B8]">Tidak ditemukan masalah data pada periode ini.</div>
                @endforelse
            </div>
        </div>
    </section>

    @if(($branchSummary ?? []) !== [])
        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Perbandingan Cabang</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach($branchSummary as $branch)
                    <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 dark:border-[#253247] dark:bg-[#0B1220]">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-base font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $branch['name'] }}</div>
                            <div class="text-xs uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">{{ $branch['code'] }}</div>
                        </div>
                        <div class="mt-4 grid gap-2 text-sm">
                            <div class="flex justify-between"><span>Omzet</span><span class="font-semibold">Rp {{ number_format($branch['income'],0,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Pengeluaran</span><span class="font-semibold">Rp {{ number_format($branch['expense'],0,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Laba Bersih</span><span class="font-semibold">Rp {{ number_format($branch['profit'],0,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Total Meter</span><span class="font-semibold">{{ number_format($branch['meters'],2,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Saldo Tahanan</span><span class="font-semibold">Rp {{ number_format($branch['saldo_tahanan'],0,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Saldo Realtime</span><span class="font-semibold">Rp {{ number_format($branch['saldo_realtime'],0,',','.') }}</span></div>
                            <div class="flex justify-between"><span>Selisih</span><span class="font-semibold {{ $branch['selisih'] < 0 ? 'text-rose-600' : 'text-emerald-600' }}">Rp {{ number_format($branch['selisih'],0,',','.') }}</span></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
