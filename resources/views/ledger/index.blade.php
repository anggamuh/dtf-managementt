@extends('layouts.app')

@section('title', 'Buku Besar')

@section('content')

<div class="space-y-6">

    {{-- =========================================================
        HEADER + FILTER
    ========================================================== --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">

        <div class="shrink-0">
            <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">
                Buku Besar
            </h1>

            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">
                Mutasi kas berdasarkan rentang tanggal yang dipilih.
            </p>
        </div>


        <div class="flex flex-col gap-2 lg:flex-row lg:items-end">

            {{-- FILTER --}}
            <form
                method="GET"
                action="{{ route('ledger.index') }}"
                class="flex flex-wrap items-end gap-2"
            >

                {{-- Dari Tanggal --}}
                <div>
                    <label
                        class="mb-1 block text-[10px] font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Dari Tanggal
                    </label>

                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="rounded-xl border border-[#E2E8F0]
                               bg-white px-4 py-2.5 text-sm
                               outline-none transition
                               focus:border-[#2563EB]
                               focus:ring-2 focus:ring-[#2563EB]/20
                               dark:border-[#253247]
                               dark:bg-[#111827]
                               dark:text-[#F8FAFC]"
                    >
                </div>


                {{-- Sampai Tanggal --}}
                <div>
                    <label
                        class="mb-1 block text-[10px] font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Sampai Tanggal
                    </label>

                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="rounded-xl border border-[#E2E8F0]
                               bg-white px-4 py-2.5 text-sm
                               outline-none transition
                               focus:border-[#2563EB]
                               focus:ring-2 focus:ring-[#2563EB]/20
                               dark:border-[#253247]
                               dark:bg-[#111827]
                               dark:text-[#F8FAFC]"
                    >
                </div>


                {{-- Cabang --}}
                <div>
                    <label
                        class="mb-1 block text-[10px] font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Cabang
                    </label>

                    <select
                        name="branch_id"
                        class="min-w-[150px] rounded-xl
                               border border-[#E2E8F0]
                               bg-white px-4 py-2.5 text-sm
                               outline-none transition
                               focus:border-[#2563EB]
                               focus:ring-2 focus:ring-[#2563EB]/20
                               dark:border-[#253247]
                               dark:bg-[#111827]
                               dark:text-[#F8FAFC]"
                    >
                        @foreach($branches as $branch)

                            @php
                                $id = data_get($branch, 'id');

                                $name =
                                    data_get($branch, 'name')
                                    ?? data_get($branch, 'branch_name')
                                    ?? 'Cabang';
                            @endphp

                            <option
                                value="{{ $id }}"
                                @selected((int) $branchId === (int) $id)
                            >
                                {{ $name }}
                            </option>

                        @endforeach
                    </select>
                </div>


                <button
                    type="submit"
                    class="rounded-xl bg-[#0F172A]
                           px-5 py-2.5 text-sm font-medium
                           text-white transition
                           hover:bg-[#1E293B]
                           dark:bg-[#F8FAFC]
                           dark:text-[#0F172A]
                           dark:hover:bg-white"
                >
                    Tampilkan
                </button>

            </form>


            {{-- =====================================================
                PERIODE TERKUNCI
            ====================================================== --}}
            <div
                x-data="{ openLockedPeriods: false }"
                class="relative"
            >

                <label
                    class="mb-1 block text-[10px] font-semibold uppercase
                           tracking-wide text-[#64748B]
                           dark:text-[#94A3B8]"
                >
                    Riwayat
                </label>


                <button
                    type="button"
                    @click="openLockedPeriods = !openLockedPeriods"
                    @click.outside="openLockedPeriods = false"
                    class="flex min-h-[42px] min-w-[190px]
                           items-center justify-between gap-3
                           rounded-xl border border-[#E2E8F0]
                           bg-white px-4 py-2.5
                           text-sm font-semibold text-[#0F172A]
                           shadow-sm transition
                           hover:bg-[#F8FAFC]
                           dark:border-[#253247]
                           dark:bg-[#111827]
                           dark:text-[#F8FAFC]
                           dark:hover:bg-[#172033]"
                >

                    <span class="flex items-center gap-2">

                        <x-heroicon-o-lock-closed
                            class="h-4 w-4 text-amber-500"
                        />

                        <span>
                            Periode Terkunci
                        </span>

                    </span>


                    <span class="flex items-center gap-2">

                        @if(($lockedPeriods ?? collect())->isNotEmpty())

                            <span
                                class="rounded-md bg-amber-50
                                       px-2 py-0.5 text-[11px]
                                       font-bold text-amber-700
                                       dark:bg-amber-500/10
                                       dark:text-amber-400"
                            >
                                {{ $lockedPeriods->count() }}
                            </span>

                        @endif


                        <x-heroicon-o-chevron-down
                            class="h-4 w-4 text-[#94A3B8]
                                   transition-transform"
                            ::class="openLockedPeriods ? 'rotate-180' : ''"
                        />

                    </span>

                </button>


                {{-- DROPDOWN --}}
                <div
                    x-show="openLockedPeriods"
                    x-transition
                    style="display: none;"
                    class="absolute right-0 z-50 mt-2
                           w-[370px] max-w-[calc(100vw-2rem)]
                           overflow-hidden rounded-2xl
                           border border-[#E2E8F0]
                           bg-white shadow-2xl
                           dark:border-[#253247]
                           dark:bg-[#111827]"
                >

                    {{-- HEADER --}}
                    <div
                        class="border-b border-[#E2E8F0]
                               px-4 py-3
                               dark:border-[#253247]"
                    >

                        <div class="flex items-center justify-between gap-4">

                            <div>
                                <p
                                    class="text-sm font-bold
                                           text-[#0F172A]
                                           dark:text-[#F8FAFC]"
                                >
                                    Periode Terkunci
                                </p>

                                <p
                                    class="mt-0.5 text-xs
                                           text-[#64748B]
                                           dark:text-[#94A3B8]"
                                >
                                    {{ $branchName ?: 'Cabang' }}
                                </p>
                            </div>


                            <span
                                class="rounded-lg bg-amber-50
                                       px-2.5 py-1
                                       text-[11px] font-bold
                                       text-amber-700
                                       dark:bg-amber-500/10
                                       dark:text-amber-400"
                            >
                                {{ ($lockedPeriods ?? collect())->count() }}
                                periode
                            </span>

                        </div>

                    </div>


                    {{-- LIST --}}
                    <div class="max-h-[360px] overflow-y-auto">

                        @forelse(($lockedPeriods ?? collect()) as $lockedPeriod)

                            @php

                                $lockedStart =
                                    \Carbon\Carbon::parse(
                                        $lockedPeriod->period_start
                                    );

                                $lockedEnd =
                                    \Carbon\Carbon::parse(
                                        $lockedPeriod->period_end
                                    );

                                $isCurrentPeriod =
                                    $lockedStart->toDateString() === $startDate
                                    &&
                                    $lockedEnd->toDateString() === $endDate;

                            @endphp


                            <a
                                href="{{ route('ledger.index', [
                                    'branch_id' => $branchId,
                                    'start_date' => $lockedStart->toDateString(),
                                    'end_date' => $lockedEnd->toDateString(),
                                ]) }}"
                                class="group flex items-center
                                       justify-between gap-4
                                       border-b border-[#E2E8F0]
                                       px-4 py-3 transition
                                       last:border-b-0
                                       hover:bg-blue-50
                                       dark:border-[#253247]
                                       dark:hover:bg-[#172033]

                                       {{ $isCurrentPeriod
                                            ? 'bg-blue-50 dark:bg-blue-500/10'
                                            : ''
                                       }}"
                            >

                                <div class="flex min-w-0 items-center gap-3">

                                    <div
                                        class="flex h-10 w-10 shrink-0
                                               items-center justify-center
                                               rounded-xl

                                               {{ $isCurrentPeriod
                                                    ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
                                               }}"
                                    >

                                        <x-heroicon-o-lock-closed
                                            class="h-5 w-5"
                                        />

                                    </div>


                                    <div class="min-w-0">

                                        <p
                                            class="truncate text-sm
                                                   font-semibold
                                                   text-[#0F172A]
                                                   dark:text-[#F8FAFC]"
                                        >
                                            {{ $lockedStart->translatedFormat('d M Y') }}
                                            -
                                            {{ $lockedEnd->translatedFormat('d M Y') }}
                                        </p>


                                        <p
                                            class="mt-1 truncate text-[11px]
                                                   text-[#64748B]
                                                   dark:text-[#94A3B8]"
                                        >
                                            Saldo:
                                            Rp {{ number_format(
                                                $lockedPeriod->saldo_realtime ?? 0,
                                                0,
                                                ',',
                                                '.'
                                            ) }}
                                        </p>


                                        @if($lockedPeriod->locked_at)

                                            <p
                                                class="mt-0.5 text-[10px]
                                                       text-[#94A3B8]
                                                       dark:text-[#64748B]"
                                            >
                                                Dikunci
                                                {{ \Carbon\Carbon::parse(
                                                    $lockedPeriod->locked_at
                                                )->translatedFormat(
                                                    'd M Y, H:i'
                                                ) }}
                                            </p>

                                        @endif

                                    </div>

                                </div>


                                <div class="shrink-0">

                                    @if($isCurrentPeriod)

                                        <span
                                            class="rounded-lg bg-blue-100
                                                   px-2 py-1
                                                   text-[10px] font-bold
                                                   uppercase tracking-wide
                                                   text-blue-700
                                                   dark:bg-blue-500/15
                                                   dark:text-blue-400"
                                        >
                                            Aktif
                                        </span>

                                    @else

                                        <x-heroicon-o-chevron-right
                                            class="h-4 w-4
                                                   text-[#94A3B8]
                                                   transition
                                                   group-hover:translate-x-0.5
                                                   group-hover:text-blue-600"
                                        />

                                    @endif

                                </div>

                            </a>


                        @empty

                            <div class="px-6 py-8 text-center">

                                <div
                                    class="mx-auto flex h-12 w-12
                                           items-center justify-center
                                           rounded-2xl bg-[#F8FAFC]
                                           text-[#94A3B8]
                                           dark:bg-[#172033]
                                           dark:text-[#64748B]"
                                >
                                    <x-heroicon-o-lock-open
                                        class="h-6 w-6"
                                    />
                                </div>


                                <p
                                    class="mt-3 text-sm font-semibold
                                           text-[#0F172A]
                                           dark:text-[#F8FAFC]"
                                >
                                    Belum ada periode terkunci
                                </p>


                                <p
                                    class="mt-1 text-xs
                                           text-[#64748B]
                                           dark:text-[#94A3B8]"
                                >
                                    Periode yang sudah dikunci
                                    akan muncul di sini.
                                </p>

                            </div>

                        @endforelse

                    </div>

                </div>

            </div>

        </div>

    </div>



    {{-- =========================================================
        SUCCESS
    ========================================================== --}}
    @if(session('success'))

        <div
            class="flex items-center gap-3
                   rounded-xl border border-emerald-200
                   bg-emerald-50 px-4 py-3
                   text-sm font-medium text-emerald-700
                   dark:border-emerald-500/20
                   dark:bg-emerald-500/10
                   dark:text-emerald-400"
        >

            <x-heroicon-o-check-circle
                class="h-5 w-5 shrink-0"
            />

            {{ session('success') }}

        </div>

    @endif



    {{-- =========================================================
        ERROR
    ========================================================== --}}
    @if($errors->any())

        <div
            class="flex items-start gap-3
                   rounded-xl border border-rose-200
                   bg-rose-50 px-4 py-3
                   text-sm text-rose-700
                   dark:border-rose-500/20
                   dark:bg-rose-500/10
                   dark:text-rose-400"
        >

            <x-heroicon-o-exclamation-triangle
                class="mt-0.5 h-5 w-5 shrink-0"
            />

            <div>

                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach

            </div>

        </div>

    @endif



    {{-- =========================================================
        PERIODE INFO
    ========================================================== --}}
    <div
        class="flex flex-col gap-2
               rounded-xl border border-blue-100
               bg-blue-50 px-4 py-3
               text-sm text-blue-800
               dark:border-blue-500/20
               dark:bg-blue-500/10
               dark:text-blue-300
               sm:flex-row sm:items-center
               sm:justify-between"
    >

        <div class="flex items-center gap-2">

            <x-heroicon-o-calendar
                class="h-5 w-5 shrink-0"
            />

            <span>

                Periode:

                <strong>
                    {{ \Carbon\Carbon::parse(
                        $startDate
                    )->translatedFormat('d F Y') }}
                </strong>

                s/d

                <strong>
                    {{ \Carbon\Carbon::parse(
                        $endDate
                    )->translatedFormat('d F Y') }}
                </strong>

            </span>

        </div>


        <div class="flex items-center gap-2">

            @if($saldoRealtimeLocked)

                <span
                    class="inline-flex items-center gap-1
                           rounded-full bg-emerald-100
                           px-2.5 py-1 text-[10px]
                           font-bold text-emerald-700
                           dark:bg-emerald-500/15
                           dark:text-emerald-400"
                >
                    <x-heroicon-o-lock-closed
                        class="h-3.5 w-3.5"
                    />

                    TERKUNCI
                </span>

            @endif


            <span class="text-xs font-medium opacity-80">
                {{ $branchName ?: 'Cabang' }}
            </span>

        </div>

    </div>



    {{-- =========================================================
        SUMMARY CARDS
    ========================================================== --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">


        {{-- SALDO TAHANAN --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Saldo Tahanan
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Rp {{ number_format(
                            $saldoTahanan,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>


                    <p
                        class="mt-1 text-xs
                               text-[#94A3B8]
                               dark:text-[#64748B]"
                    >
                        Saldo permanen cabang
                    </p>

                </div>


                <div
                    class="app-icon bg-blue-50
                           text-blue-600
                           dark:bg-blue-500/10
                           dark:text-blue-400"
                >
                    <x-heroicon-o-credit-card
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>



        {{-- TOTAL PEMASUKAN --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Total Pemasukan
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Rp {{ number_format(
                            $totalPemasukan,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>


                    <p
                        class="mt-1 text-xs
                               text-[#94A3B8]
                               dark:text-[#64748B]"
                    >
                        Dalam periode
                    </p>

                </div>


                <div
                    class="app-icon bg-emerald-50
                           text-emerald-600
                           dark:bg-emerald-500/10
                           dark:text-emerald-400"
                >
                    <x-heroicon-o-arrow-trending-up
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>



        {{-- TOTAL PENGELUARAN --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Total Pengeluaran
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Rp {{ number_format(
                            $totalPengeluaran,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>


                    <p
                        class="mt-1 text-xs
                               text-[#94A3B8]
                               dark:text-[#64748B]"
                    >
                        Dalam periode
                    </p>

                </div>


                <div
                    class="app-icon bg-rose-50
                           text-rose-600
                           dark:bg-rose-500/10
                           dark:text-rose-400"
                >
                    <x-heroicon-o-arrow-trending-down
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>



        {{-- SISA SALDO --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Sisa Saldo
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               {{ $sisaSaldo >= 0
                                    ? 'text-[#0F172A] dark:text-[#F8FAFC]'
                                    : 'text-rose-600 dark:text-rose-400'
                               }}"
                    >
                        Rp {{ number_format(
                            $sisaSaldo,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>

                </div>


                <div
                    class="app-icon bg-violet-50
                           text-violet-600
                           dark:bg-violet-500/10
                           dark:text-violet-400"
                >
                    <x-heroicon-o-wallet
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>



        {{-- SALDO REALTIME --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0 flex-1">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Saldo Realtime
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Rp {{ number_format(
                            $saldoRealtime,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>


                    @if($saldoRealtimeLocked)

                        <span
                            class="mt-2 inline-flex items-center
                                   gap-1 rounded-full
                                   bg-emerald-50 px-2.5 py-1
                                   text-[11px] font-bold
                                   text-emerald-700
                                   dark:bg-emerald-500/10
                                   dark:text-emerald-400"
                        >

                            <x-heroicon-o-lock-closed
                                class="h-3.5 w-3.5"
                            />

                            Terkunci

                        </span>

                    @else

                        <button
                            type="button"
                            onclick="document.getElementById('saldoRealtimeModal').classList.remove('hidden')"
                            class="mt-2 text-xs font-semibold
                                   text-blue-600
                                   hover:text-blue-700
                                   dark:text-blue-400"
                        >
                            Edit saldo periode
                        </button>

                    @endif

                </div>


                <div
                    class="app-icon bg-amber-50
                           text-amber-600
                           dark:bg-amber-500/10
                           dark:text-amber-400"
                >
                    <x-heroicon-o-clock
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>



        {{-- SELISIH --}}
        <div class="app-stat-card">

            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">

                    <p
                        class="text-xs font-semibold uppercase
                               tracking-wide text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Selisih
                    </p>


                    <p
                        class="mt-2 text-xl font-bold
                               {{ $selisih == 0
                                    ? 'text-[#0F172A] dark:text-[#F8FAFC]'
                                    : 'text-rose-600 dark:text-rose-400'
                               }}"
                    >
                        Rp {{ number_format(
                            $selisih,
                            0,
                            ',',
                            '.'
                        ) }}
                    </p>


                    <p
                        class="mt-1 text-xs
                               text-[#94A3B8]
                               dark:text-[#64748B]"
                    >
                        Realtime − Sisa Saldo
                    </p>

                </div>


                <div
                    class="app-icon bg-cyan-50
                           text-cyan-600
                           dark:bg-cyan-500/10
                           dark:text-cyan-400"
                >
                    <x-heroicon-o-scale
                        class="h-6 w-6"
                    />
                </div>

            </div>

        </div>

    </div>



    {{-- =========================================================
        INPUT SALDO REALTIME
    ========================================================== --}}
    <div
        class="overflow-hidden rounded-2xl
               border
               {{ $saldoRealtimeLocked
                    ? 'border-emerald-200 dark:border-emerald-500/30'
                    : 'border-amber-200 dark:border-amber-500/30'
               }}
               bg-white shadow-sm
               dark:bg-[#111827]"
    >

        <div
            class="bg-gradient-to-r
                   {{ $saldoRealtimeLocked
                        ? 'from-emerald-50 to-white dark:from-emerald-500/10 dark:to-[#111827]'
                        : 'from-amber-50 to-white dark:from-amber-500/10 dark:to-[#111827]'
                   }}
                   p-5"
        >

            <div
                class="flex flex-col gap-5
                       lg:flex-row
                       lg:items-center
                       lg:justify-between"
            >

                <div class="flex items-start gap-4">

                    <div
                        class="flex h-12 w-12 shrink-0
                               items-center justify-center
                               rounded-2xl
                               {{ $saldoRealtimeLocked
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
                               }}"
                    >

                        @if($saldoRealtimeLocked)

                            <x-heroicon-o-lock-closed
                                class="h-6 w-6"
                            />

                        @else

                            <x-heroicon-o-banknotes
                                class="h-6 w-6"
                            />

                        @endif

                    </div>


                    <div>

                        <div class="flex flex-wrap items-center gap-2">

                            <h2
                                class="text-lg font-bold
                                       text-[#0F172A]
                                       dark:text-[#F8FAFC]"
                            >
                                Saldo Realtime Periode
                            </h2>


                            <span
                                class="rounded-full px-2.5 py-1
                                       text-[11px] font-bold
                                       {{ $saldoRealtimeLocked
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400'
                                       }}"
                            >
                                {{ $saldoRealtimeLocked
                                    ? 'TERKUNCI'
                                    : 'BELUM DIKUNCI'
                                }}
                            </span>

                        </div>


                        <p
                            class="mt-1 text-sm
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            {{ \Carbon\Carbon::parse(
                                $startDate
                            )->translatedFormat('d F Y') }}

                            s/d

                            {{ \Carbon\Carbon::parse(
                                $endDate
                            )->translatedFormat('d F Y') }}

                            &middot;

                            {{ $branchName ?: 'Cabang' }}
                        </p>


                        <p
                            class="mt-3 text-3xl font-bold
                                   tracking-tight text-[#0F172A]
                                   dark:text-[#F8FAFC]"
                        >
                            Rp {{ number_format(
                                $saldoRealtime,
                                0,
                                ',',
                                '.'
                            ) }}
                        </p>


                        @if(
                            $saldoRealtimeLocked
                            && $saldoRealtimeLockedAt
                        )

                            <p
                                class="mt-1 text-xs
                                       text-emerald-700
                                       dark:text-emerald-400"
                            >
                                Dikunci
                                {{ \Carbon\Carbon::parse(
                                    $saldoRealtimeLockedAt
                                )->translatedFormat(
                                    'd F Y, H:i'
                                ) }}
                            </p>

                        @else

                            <p
                                class="mt-1 text-xs
                                       text-[#94A3B8]
                                       dark:text-[#64748B]"
                            >
                                Rentang periode lain otomatis
                                dimulai kembali dari Rp0.
                            </p>

                        @endif

                    </div>

                </div>


                @if(!$saldoRealtimeLocked)

                    <div class="flex flex-wrap gap-2">

                        <button
                            type="button"
                            onclick="document.getElementById('saldoRealtimeModal').classList.remove('hidden')"
                            class="inline-flex items-center gap-2
                                   rounded-xl border
                                   border-[#E2E8F0]
                                   bg-white px-4 py-2.5
                                   text-sm font-semibold
                                   text-[#0F172A]
                                   shadow-sm transition
                                   hover:bg-[#F8FAFC]
                                   dark:border-[#253247]
                                   dark:bg-[#172033]
                                   dark:text-[#F8FAFC]
                                   dark:hover:bg-[#1E293B]"
                        >

                            <x-heroicon-o-pencil-square
                                class="h-4 w-4"
                            />

                            Update Saldo

                        </button>


                        <form
                            method="POST"
                            action="{{ route('ledger.realtime.lock') }}"
                            onsubmit="return confirm('Kunci Saldo Realtime periode ini? Setelah dikunci nilainya tidak dapat diubah lagi.');"
                        >

                            @csrf

                            <input
                                type="hidden"
                                name="branch_id"
                                value="{{ $branchId }}"
                            >

                            <input
                                type="hidden"
                                name="start_date"
                                value="{{ $startDate }}"
                            >

                            <input
                                type="hidden"
                                name="end_date"
                                value="{{ $endDate }}"
                            >


                            <button
                                type="submit"
                                class="inline-flex items-center gap-2
                                       rounded-xl bg-[#0F172A]
                                       px-4 py-2.5
                                       text-sm font-semibold
                                       text-white transition
                                       hover:bg-[#1E293B]
                                       dark:bg-[#F8FAFC]
                                       dark:text-[#0F172A]
                                       dark:hover:bg-white"
                            >

                                <x-heroicon-o-lock-closed
                                    class="h-4 w-4"
                                />

                                Kunci Periode

                            </button>

                        </form>

                    </div>


                @else

                    <div
                        class="max-w-xs rounded-xl
                               border border-emerald-200
                               bg-white/70 px-4 py-3
                               text-sm text-emerald-800
                               dark:border-emerald-500/20
                               dark:bg-[#0B1220]/70
                               dark:text-emerald-300"
                    >
                        Nilai periode ini sudah final dan
                        tidak dapat diedit.
                    </div>

                @endif

            </div>

        </div>

    </div>



    {{-- =========================================================
        INFO PERHITUNGAN
    ========================================================== --}}
    <div
        class="flex items-start gap-3
               rounded-xl border border-blue-100
               bg-blue-50 px-4 py-3
               text-sm text-blue-800
               dark:border-blue-500/20
               dark:bg-blue-500/10
               dark:text-blue-300"
    >

        <x-heroicon-o-information-circle
            class="mt-0.5 h-5 w-5 shrink-0"
        />


        <div>

            <p class="font-medium">
                Cara perhitungan saldo
            </p>


            <p class="mt-1 opacity-90">
                Profit = Total Pemasukan − Total Pengeluaran.
                Saldo Tahanan Sisa = Saldo Tahanan − Stock Opname.
                Sisa Saldo = Profit + Saldo Tahanan Sisa.
                Selisih = Saldo Realtime − Sisa Saldo.
            </p>


            @if(!$closing)

                <p
                    class="mt-1 text-xs font-semibold
                           text-amber-700
                           dark:text-amber-400"
                >
                    Closing untuk periode ini belum tersedia,
                    sehingga Stock Opname masih Rp0.
                </p>

            @else

                <p
                    class="mt-1 text-xs opacity-80"
                >
                    Stock Opname Closing:
                    Rp {{ number_format(
                        $remainingMaterial,
                        0,
                        ',',
                        '.'
                    ) }}
                </p>

            @endif

        </div>

    </div>



    {{-- =========================================================
        MUTASI BUKU BESAR
    ========================================================== --}}
    <div
        class="overflow-hidden rounded-2xl
               border border-[#E2E8F0]
               bg-white shadow-sm
               dark:border-[#253247]
               dark:bg-[#111827]"
    >

        <div
            class="border-b border-[#E2E8F0]
                   p-5 dark:border-[#253247]"
        >

            <div
                class="flex flex-col gap-1
                       sm:flex-row
                       sm:items-center
                       sm:justify-between"
            >

                <div>

                    <h2
                        class="text-lg font-bold
                               text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Mutasi Buku Besar
                    </h2>


                    <p
                        class="mt-1 text-sm
                               text-[#64748B]
                               dark:text-[#94A3B8]"
                    >
                        Semua transaksi pada periode yang dipilih.
                    </p>

                </div>


                <div
                    class="text-xs font-medium
                           text-[#64748B]
                           dark:text-[#94A3B8]"
                >
                    {{ $rows->count() }} transaksi
                </div>

            </div>

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-sm">

                <thead
                    class="border-b border-[#E2E8F0]
                           bg-[#F8FAFC] text-left
                           dark:border-[#253247]
                           dark:bg-[#0B1220]"
                >

                    <tr>

                        <th
                            class="whitespace-nowrap p-3
                                   font-semibold text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Tanggal
                        </th>


                        <th
                            class="whitespace-nowrap p-3
                                   font-semibold text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Referensi
                        </th>


                        <th
                            class="p-3 font-semibold
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Keterangan
                        </th>


                        <th
                            class="whitespace-nowrap p-3
                                   text-right font-semibold
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Debit
                        </th>


                        <th
                            class="whitespace-nowrap p-3
                                   text-right font-semibold
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Kredit
                        </th>


                        <th
                            class="whitespace-nowrap p-3
                                   text-right font-semibold
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            Saldo
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($rows as $row)

                        <tr
                            class="border-b border-[#E2E8F0]
                                   transition
                                   hover:bg-[#F8FAFC]
                                   dark:border-[#253247]
                                   dark:hover:bg-[#172033]"
                        >

                            <td
                                class="whitespace-nowrap p-3
                                       text-[#64748B]
                                       dark:text-[#94A3B8]"
                            >
                                {{ $row['date']->format('d/m/Y') }}
                            </td>


                            <td
                                class="whitespace-nowrap p-3
                                       text-[#64748B]
                                       dark:text-[#94A3B8]"
                            >
                                {{ $row['ref'] }}
                            </td>


                            <td
                                class="p-3 font-medium
                                       text-[#0F172A]
                                       dark:text-[#F8FAFC]"
                            >
                                {{ $row['keterangan'] }}
                            </td>


                            <td
                                class="whitespace-nowrap p-3
                                       text-right font-medium
                                       {{ $row['debit'] > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-[#94A3B8] dark:text-[#64748B]'
                                       }}"
                            >
                                {{ $row['debit'] > 0
                                    ? 'Rp '.number_format(
                                        $row['debit'],
                                        0,
                                        ',',
                                        '.'
                                    )
                                    : '-'
                                }}
                            </td>


                            <td
                                class="whitespace-nowrap p-3
                                       text-right font-medium
                                       {{ $row['kredit'] > 0
                                            ? 'text-rose-600 dark:text-rose-400'
                                            : 'text-[#94A3B8] dark:text-[#64748B]'
                                       }}"
                            >
                                {{ $row['kredit'] > 0
                                    ? 'Rp '.number_format(
                                        $row['kredit'],
                                        0,
                                        ',',
                                        '.'
                                    )
                                    : '-'
                                }}
                            </td>


                            <td
                                class="whitespace-nowrap p-3
                                       text-right font-semibold
                                       text-[#0F172A]
                                       dark:text-[#F8FAFC]"
                            >
                                Rp {{ number_format(
                                    $row['saldo'],
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="p-10 text-center
                                       text-[#94A3B8]
                                       dark:text-[#64748B]"
                            >

                                <div class="flex flex-col items-center">

                                    <x-heroicon-o-document-magnifying-glass
                                        class="h-10 w-10 opacity-50"
                                    />


                                    <p class="mt-3 font-medium">
                                        Tidak ada mutasi
                                    </p>


                                    <p class="mt-1 text-xs">
                                        Tidak ditemukan transaksi
                                        pada periode yang dipilih.
                                    </p>

                                </div>

                            </td>

                        </tr>

                    @endforelse

                </tbody>


                @if($rows->isNotEmpty())

                    <tfoot>

                        <tr
                            class="border-t-2 border-[#0F172A]
                                   bg-[#F8FAFC]
                                   dark:border-[#F8FAFC]
                                   dark:bg-[#0B1220]"
                        >

                            <td
                                colspan="3"
                                class="p-3 font-bold
                                       text-[#0F172A]
                                       dark:text-[#F8FAFC]"
                            >
                                TOTAL
                            </td>


                            <td
                                class="p-3 text-right font-bold
                                       text-emerald-600
                                       dark:text-emerald-400"
                            >
                                Rp {{ number_format(
                                    $totalPemasukan,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <td
                                class="p-3 text-right font-bold
                                       text-rose-600
                                       dark:text-rose-400"
                            >
                                Rp {{ number_format(
                                    $totalPengeluaran,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>


                            <td
                                class="p-3 text-right font-bold
                                       text-[#0F172A]
                                       dark:text-[#F8FAFC]"
                            >
                                Rp {{ number_format(
                                    $sisaSaldo,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                        </tr>

                    </tfoot>

                @endif

            </table>

        </div>

    </div>

</div>



{{-- =============================================================
    MODAL UPDATE SALDO REALTIME
============================================================== --}}
@if(!$saldoRealtimeLocked)

<div
    id="saldoRealtimeModal"
    class="fixed inset-0 z-[100]
           hidden overflow-y-auto"
>

    <div
        class="fixed inset-0
               bg-black/40 backdrop-blur-sm"
        onclick="document.getElementById('saldoRealtimeModal').classList.add('hidden')"
    ></div>


    <div
        class="relative flex min-h-full
               items-center justify-center p-4"
    >

        <div
            class="relative w-full max-w-md
                   rounded-2xl border
                   border-[#E2E8F0]
                   bg-white shadow-2xl
                   dark:border-[#253247]
                   dark:bg-[#111827]"
        >

            <div
                class="border-b border-[#E2E8F0]
                       p-5 dark:border-[#253247]"
            >

                <div class="flex items-center justify-between">

                    <div>

                        <h3
                            class="text-lg font-bold
                                   text-[#0F172A]
                                   dark:text-[#F8FAFC]"
                        >
                            Update Saldo Realtime
                        </h3>


                        <p
                            class="mt-1 text-xs
                                   text-[#64748B]
                                   dark:text-[#94A3B8]"
                        >
                            {{ $branchName ?: 'Cabang' }}
                        </p>

                    </div>


                    <button
                        type="button"
                        onclick="document.getElementById('saldoRealtimeModal').classList.add('hidden')"
                        class="rounded-lg p-2
                               text-[#64748B]
                               hover:bg-[#F8FAFC]
                               dark:text-[#94A3B8]
                               dark:hover:bg-[#172033]"
                    >

                        <x-heroicon-o-x-mark
                            class="h-5 w-5"
                        />

                    </button>

                </div>

            </div>


            <form
                method="POST"
                action="{{ route('ledger.realtime.update') }}"
            >

                @csrf


                <input
                    type="hidden"
                    name="branch_id"
                    value="{{ $branchId }}"
                >


                <input
                    type="hidden"
                    name="start_date"
                    value="{{ $startDate }}"
                >


                <input
                    type="hidden"
                    name="end_date"
                    value="{{ $endDate }}"
                >


                <div class="p-5">

                    <label
                        class="mb-2 block text-sm
                               font-semibold text-[#0F172A]
                               dark:text-[#F8FAFC]"
                    >
                        Saldo Realtime
                    </label>


                    <div class="relative">

                        <span
                            class="absolute left-4 top-1/2
                                   -translate-y-1/2
                                   text-sm font-medium
                                   text-[#64748B]"
                        >
                            Rp
                        </span>


                        <input
                            type="number"
                            name="saldo_realtime"
                            value="{{ $saldoRealtime }}"
                            min="0"
                            step="1"
                            required
                            class="w-full rounded-xl
                                   border border-[#E2E8F0]
                                   bg-white py-3
                                   pl-12 pr-4
                                   text-lg font-semibold
                                   text-[#0F172A]
                                   outline-none
                                   focus:border-[#2563EB]
                                   focus:ring-2
                                   focus:ring-[#2563EB]/20
                                   dark:border-[#253247]
                                   dark:bg-[#0B1220]
                                   dark:text-[#F8FAFC]"
                        >

                    </div>


                    <p
                        class="mt-2 text-xs
                               text-[#94A3B8]
                               dark:text-[#64748B]"
                    >
                        Saldo ini khusus untuk periode
                        yang sedang dipilih.
                        Setelah dikunci nilainya
                        tidak dapat diubah.
                    </p>

                </div>


                <div
                    class="flex justify-end gap-2
                           border-t border-[#E2E8F0]
                           p-5 dark:border-[#253247]"
                >

                    <button
                        type="button"
                        onclick="document.getElementById('saldoRealtimeModal').classList.add('hidden')"
                        class="rounded-xl border
                               border-[#E2E8F0]
                               px-4 py-2.5
                               text-sm font-medium
                               text-[#64748B]
                               transition
                               hover:bg-[#F8FAFC]
                               dark:border-[#253247]
                               dark:text-[#94A3B8]
                               dark:hover:bg-[#172033]"
                    >
                        Batal
                    </button>


                    <button
                        type="submit"
                        class="rounded-xl bg-[#2563EB]
                               px-5 py-2.5
                               text-sm font-semibold
                               text-white transition
                               hover:bg-[#1D4ED8]"
                    >
                        Simpan Saldo
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endif

@endsection