@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-bold text-slate-800">Laporan</h1>

    <form class="my-5 flex flex-wrap gap-2">
        <select name="type" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            <option value="income" @selected($type==='income')>Pendapatan</option>
            <option value="expense" @selected($type==='expense')>Pengeluaran</option>
            <option value="material" @selected($type==='material')>Material</option>
        </select>

        <input type="date" name="from" value="{{ $from }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">

        <input type="date" name="to" value="{{ $to }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">

        <input type="hidden" name="branch_id" value="{{ $branchId }}">

        <button class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-white text-sm font-medium hover:bg-slate-700 transition-all duration-200 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            Filter
        </button>

          <a href="{{ route('reports.export', ['format'=>'pdf','type'=>$type,'from'=>$from,'to'=>$to,'branch_id'=>$branchId]) }}"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all duration-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            PDF
        </a>

          <a href="{{ route('reports.export', ['format'=>'xlsx','type'=>$type,'from'=>$from,'to'=>$to,'branch_id'=>$branchId]) }}"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all duration-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Excel
        </a>
    </form>

    {{-- Reports Table --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:bg-slate-800 shadow-sm dark:border-slate-700 dark:text-slate-100">
        <table class="w-full text-sm">
            <thead class="bg-slate-50/80 border-b border-slate-200 text-left dark:bg-slate-900 dark:border-slate-700">
                <tr>
                    <th class="p-4 font-semibold text-slate-600 dark:text-slate-200">Tanggal</th>
                    <th class="p-4 font-semibold text-slate-600 dark:text-slate-200">Nama</th>
                    <th class="p-4 font-semibold text-slate-600 dark:text-slate-200">Status</th>
                    <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-200">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors duration-150">
                        <td class="p-4">{{ $row['date'] }}</td>
                        <td class="p-4">{{ $row['name'] }}</td>
                        <td class="p-4">
                            <span class="inline-flex rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:text-slate-100">
                                {{ $row['status'] }}
                            </span>
                        </td>
                        <td class="p-4 text-right font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($row['amount'],0,',','.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-10 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Tidak ada data.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection