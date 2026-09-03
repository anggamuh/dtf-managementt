@extends('layouts.app')

@section('title', 'Closing')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC]">Closing</h1>
            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Laporan otomatis per periode untuk setiap cabang</p>
        </div>
    </div>

    @if(session('message'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ session('message') }}
        </div>
    @endif

    {{-- Generate Closing --}}
    <div class="mt-5 rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <p class="text-sm font-medium text-[#64748B] mb-3 dark:text-[#94A3B8]">Buat / Perbarui Closing dengan rentang tanggal</p>
        <form method="POST" action="{{ route('closing.generate') }}" class="flex flex-wrap items-end gap-3" onsubmit="showGenerateClosingConfirmation(event, this);">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Bulan</label>
                <select name="month" class="rounded-xl border border-[#E2E8F0] bg-white dark:bg-[#111827] dark:text-[#F8FAFC] px-3 py-2 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($m == $month)>
                            {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Tanggal Mulai</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="rounded-xl border border-[#E2E8F0] bg-white dark:bg-[#111827] dark:text-[#F8FAFC] px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Tanggal Akhir</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="rounded-xl border border-[#E2E8F0] bg-white dark:bg-[#111827] dark:text-[#F8FAFC] px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Tahun</label>
                <input type="number" name="year" value="{{ $year }}"
                    class="w-28 rounded-xl border border-[#E2E8F0] bg-white dark:bg-[#111827] dark:text-[#F8FAFC] px-3 py-2 text-sm">
            </div>
            @if((int) $branchId === 0)
                <button type="button" onclick="showBranchRequiredAlert()" class="rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-medium text-white transition opacity-50 cursor-not-allowed dark:bg-[#3B82F6]" disabled title="Pilih cabang terlebih dahulu">
                    Generate Closing
                </button>
            @else
                <button type="submit" class="rounded-xl bg-[#2563EB] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#1D4ED8] transition dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                    Generate Closing
                </button>
            @endif
        </form>
    </div>

    {{-- Closing Table --}}
    <div class="mt-5 overflow-x-auto rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <table class="w-full text-sm">
            <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                <tr>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Periode</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Pemasukan</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Pengeluaran</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">HPP</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">HPP/Meter</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Laba/Rugi</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Status</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($closings as $c)
                    <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] transition-colors duration-150 dark:border-[#253247] dark:hover:bg-[#172033]">
                        <td class="p-4 font-medium text-[#0F172A] dark:text-[#F8FAFC]">
                            {{ $c->period_start?->format('d/m/Y') ?? \Carbon\Carbon::create()->month((int) $c->month)->startOfMonth()->format('d/m/Y') }} - {{ $c->period_end?->format('d/m/Y') ?? \Carbon\Carbon::create()->month((int) $c->month)->endOfMonth()->format('d/m/Y') }}
                        </td>
                        <td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($c->income,0,',','.') }}</td>
                        <td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($c->expense,0,',','.') }}</td>
                        <td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($c->hpp,0,',','.') }}</td>
                        <td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($c->hpp_per_meter,0,',','.') }}</td>
                        <td class="p-4 text-right font-semibold {{ $c->profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            Rp {{ number_format($c->profit,0,',','.') }}
                        </td>
                        <td class="p-4 text-center">
                            @if($c->is_locked)
                                <span class="inline-flex rounded-full bg-slate-100 text-slate-700 border border-slate-200 px-2.5 py-0.5 text-xs font-bold dark:bg-slate-700/40 dark:text-slate-300 dark:border-slate-600">Terkunci</span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-xs font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20">Draft</span>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('closing.show', $c) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-blue-50 hover:text-[#2563EB] hover:border-blue-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-blue-500/10 dark:hover:text-blue-400 dark:hover:border-blue-500/30"
                                   title="Detail" aria-label="Detail closing">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </a>
                                @if((int) $branchId === 0)
                                    <button type="button" onclick="showBranchRequiredAlert()" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 transition-all duration-150 opacity-50 cursor-not-allowed dark:border-rose-500/20 dark:text-rose-400" title="Pilih cabang terlebih dahulu">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <button type="button" onclick="showBranchRequiredAlert()" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 text-emerald-600 transition-all duration-150 opacity-50 cursor-not-allowed dark:border-emerald-500/20 dark:text-emerald-400" title="Pilih cabang terlebih dahulu">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </button>
                                @else
                                    <a href="{{ route('closing.export-pdf', ['branch_id' => $branchId, 'month' => $c->month, 'year' => $c->year]) }}"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300 transition-all duration-150 dark:border-rose-500/20 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                       title="Export PDF" aria-label="Export PDF">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('closing.export-excel', ['branch_id' => $branchId, 'month' => $c->month, 'year' => $c->year]) }}"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-300 transition-all duration-150 dark:border-emerald-500/20 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                       title="Export Excel" aria-label="Export Excel">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </a>
                                @endif
                                @if(!$c->is_locked)
                                <form method="POST" action="{{ route('closing.destroy', $c) }}" onsubmit="event.preventDefault(); showDeleteClosingConfirmation('{{ \Carbon\Carbon::create()->month((int) $c->month)->translatedFormat('F') }} {{ (int) $c->year }}', '{{ route('closing.destroy', $c) }}');">
                                    @csrf
                                    @method('DELETE')
                                    @if((int) $branchId === 0)
                                        <button type="button" onclick="showBranchRequiredAlert()" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#94A3B8] transition-all duration-150 opacity-50 cursor-not-allowed dark:border-[#253247] dark:text-[#64748B]" title="Pilih cabang terlebih dahulu" disabled>
                                    @else
                                        <button type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#94A3B8] hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all duration-150 dark:border-[#253247] dark:text-[#64748B] dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/30"
                                                title="Hapus" aria-label="Hapus closing">
                                    @endif
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-10 text-center text-[#94A3B8] dark:text-[#64748B]">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-12 h-12 text-[#CBD5E1] dark:text-[#334155]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m-6 4h6m-6 4h6m-3-12v16m-7-4h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                </svg>
                                <p class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Belum ada closing</p>
                                <p class="text-sm">Closing akan muncul di sini setelah digenerate.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $closings->links() }}
    </div>

    @push('scripts')
    <script>
        function showDeleteClosingConfirmation(period, deleteUrl) {
            Swal.fire({
                title: 'Hapus Closing?',
                text: `Yakin ingin menghapus closing ${period}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = deleteUrl;
                    form.innerHTML = '@csrf @method("DELETE")';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function showGenerateClosingConfirmation(event, form) {
            event.preventDefault();
            Swal.fire({
                title: 'Generate Closing?',
                text: 'Ini akan menghitung ulang semua data closing untuk periode yang dipilih.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Generate',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>
    @endpush
@endsection