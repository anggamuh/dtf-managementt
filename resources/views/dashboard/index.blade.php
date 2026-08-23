@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    {{-- Finance Overview Header --}}
    <section class="rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1.5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#2563EB] dark:text-[#3B82F6]">Finance Overview</p>
                <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Dashboard</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#64748B] dark:text-[#94A3B8]">Pantau penjualan, pengeluaran, invoice terbaru, dan ringkasan bisnis dalam satu tampilan yang jelas, modern, dan responsif.</p>
            </div>
            <div class="grid w-full gap-4 sm:grid-cols-2 lg:w-[420px]">
                <form method="GET" action="{{ route('dashboard') }}" class="grid gap-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Periode</span>
                        <input type="month" name="month" value="{{ $selectedMonth->format('Y-m') }}" onchange="this.form.submit()" class="mt-1.5 w-full rounded-xl border border-[#E2E8F0] bg-white px-3.5 py-2.5 text-sm text-[#0F172A] outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#2563EB]/20 dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]" />
                    </label>
                </form>
                <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 dark:border-[#253247] dark:bg-[#0B1220]">
                    <p class="text-xs font-medium uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Status Periode</p>
                    <p class="mt-1 text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $periodLabel }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Statistic Cards --}}
    <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Total Penjualan</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalSales ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <x-heroicon-o-currency-dollar class="h-6 w-6"/>
                </div>
            </div>
            <p class="mt-3 text-xs text-[#64748B] dark:text-[#94A3B8]">Pendapatan penjualan selama periode yang dipilih.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Total Pengeluaran</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($totalExpense ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <x-heroicon-o-arrow-down-tray class="h-6 w-6"/>
                </div>
            </div>
            <p class="mt-3 text-xs text-[#64748B] dark:text-[#94A3B8]">Total biaya operasional dan pengeluaran lain.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Laba Bersih</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($profit ?? 0,0,',','.') }}</p>
                </div>
                <div class="app-icon bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <x-heroicon-o-arrow-up-right class="h-6 w-6"/>
                </div>
            </div>
            <p class="mt-3 text-xs text-[#64748B] dark:text-[#94A3B8]">Selisih penjualan dan pengeluaran selama periode ini.</p>
        </article>

        <article class="app-stat-card">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#64748B] dark:text-[#94A3B8]">Pesanan Bulan Ini</p>
                    <p class="mt-2 text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">{{ $orderCount }}</p>
                </div>
                <div class="app-icon bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                    <x-heroicon-o-shopping-cart class="h-6 w-6"/>
                </div>
            </div>
            <p class="mt-3 text-xs text-[#64748B] dark:text-[#94A3B8]">Jumlah pesanan valid untuk periode terpilih.</p>
        </article>
    </section>

    {{-- Main Grid: Transactions + Expenses --}}
    <section class="grid gap-6 xl:grid-cols-12">
        {{-- Transaksi Terbaru --}}
        <div class="xl:col-span-8 rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <div class="flex flex-col gap-1 border-b border-[#E2E8F0] p-6 dark:border-[#253247]">
                <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Transaksi Terbaru</h2>
                <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">Lihat detail pesanan terbaru dan nilai transaksi.</p>
            </div>
            <div class="divide-y divide-[#E2E8F0] dark:divide-[#253247]">
                @forelse($recentOrders as $order)
                    <div class="flex flex-col gap-3 p-5 transition hover:bg-[#F8FAFC] dark:hover:bg-[#172033] sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="app-icon-sm bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                <x-heroicon-o-shopping-cart class="h-4 w-4"/>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $order->order_number }}</p>
                                <p class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $order->customer->name }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 sm:flex-col sm:items-end sm:gap-1">
                            <span class="text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $order->date->format('d M Y') }}</span>
                            <span class="text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($order->total,0,',','.') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="app-empty">
                        <x-heroicon-o-shopping-cart class="app-empty-icon h-12 w-12"/>
                        <p class="app-empty-title">Belum ada transaksi</p>
                        <p class="app-empty-desc">Belum terdapat transaksi pada periode ini.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Pengeluaran Terbaru --}}
        <div class="xl:col-span-4 space-y-6">
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
                <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Pengeluaran Terbaru</h2>
                <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Ringkasan pengeluaran terbaru untuk memantau arus kas.</p>
                <div class="mt-5 space-y-3">
                    @forelse($recentExpenses as $expense)
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4 dark:border-[#253247] dark:bg-[#0B1220]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $expense->description }}</p>
                                    <p class="mt-0.5 text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $expense->category }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-rose-600 dark:text-rose-400">Rp {{ number_format($expense->amount,0,',','.') }}</p>
                                    <p class="mt-0.5 text-xs text-[#64748B] dark:text-[#94A3B8]">{{ $expense->date->format('d M Y') }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="app-empty">
                            <x-heroicon-o-arrow-down-tray class="app-empty-icon h-10 w-10"/>
                            <p class="app-empty-title">Belum ada pengeluaran</p>
                            <p class="app-empty-desc">Data pengeluaran akan tampil setelah ditambahkan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-2xl border border-[#E2E8F0] bg-white p-6 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
                <h2 class="text-lg font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Akses Cepat</h2>
                <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Akses cepat ke halaman penting.</p>
                <div class="mt-5 grid gap-2.5">
                    <a href="{{ route('orders.index') }}" class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-medium text-[#0F172A] transition hover:border-[#2563EB]/40 hover:bg-[#F8FAFC] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">
                        <span>Kelola Pesanan</span>
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-[#64748B] dark:text-[#94A3B8]"/>
                    </a>
                    <a href="{{ route('invoices.index') }}" class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-medium text-[#0F172A] transition hover:border-[#2563EB]/40 hover:bg-[#F8FAFC] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">
                        <span>Kelola Invoice</span>
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-[#64748B] dark:text-[#94A3B8]"/>
                    </a>
                    <a href="{{ route('expenses.index') }}" class="flex items-center justify-between rounded-xl border border-[#E2E8F0] bg-white px-4 py-3 text-sm font-medium text-[#0F172A] transition hover:border-[#2563EB]/40 hover:bg-[#F8FAFC] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">
                        <span>Kelola Pengeluaran</span>
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-[#64748B] dark:text-[#94A3B8]"/>
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection