@extends('layouts.app')

@section('title', 'Dashboard Owner')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard Owner</h1>
            <p class="mt-2 text-sm text-gray-600">Lihat ringkasan semua cabang atau pilih cabang untuk detail lebih lanjut.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <span class="inline-flex items-center rounded-full bg-purple-100 px-3 py-1 text-sm font-semibold text-purple-800">Owner</span>
            <form method="GET" action="{{ route('dashboard') }}" class="inline-flex w-full max-w-sm items-center gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-slate-100">
                <label class="sr-only" for="branch_id">Pilih Cabang</label>
                <select id="branch_id" name="branch_id" onchange="this.form.submit()" class="w-full bg-transparent text-sm text-gray-900 outline-none dark:text-white">
                    <option value="">Pilih Cabang</option>
                    @foreach($allBranches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    @php
        $allBranches = \App\Models\Branch::all();
        $month = date('m');
        $year = date('Y');
        $startDate = "{$year}-{$month}-01";
        $endDate = date("{$year}-{$month}-t");
        $totalIncomeAll = 0;
        $totalExpenseAll = 0;
    @endphp

    <!-- Ringkasan Semua Cabang -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @foreach($allBranches as $branch)
            @php
                $income = \App\Models\Invoice::where('branch_id', $branch->id)->whereBetween('date', [$startDate, $endDate])->sum('total');
                $expense = \App\Models\Expense::where('branch_id', $branch->id)->whereBetween('date', [$startDate, $endDate])->sum('amount');
                $totalIncomeAll += $income;
                $totalExpenseAll += $expense;
            @endphp
            <div class="bg-white rounded-lg shadow p-6 dark:bg-slate-800 dark:text-slate-200">
                <h3 class="text-lg font-semibold text-gray-900">{{ $branch->name }}</h3>
                <div class="mt-3 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Pemasukan:</span>
                        <span class="font-medium text-green-700">Rp {{ number_format($income, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Pengeluaran:</span>
                        <span class="font-medium text-red-700">Rp {{ number_format($expense, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm font-bold border-t pt-1">
                        <span class="text-gray-600">Laba/Rugi:</span>
                        <span class="{{ ($income - $expense) >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                            Rp {{ number_format($income - $expense, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('closing.index', ['branch_id' => $branch->id]) }}" class="mt-3 inline-block text-sm text-indigo-600 hover:text-indigo-800">Lihat Detail →</a>
            </div>
        @endforeach
    </div>

    <!-- Total Semua Cabang -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <p class="text-sm text-green-600 font-medium">Total Pemasukan Semua Cabang</p>
            <p class="text-2xl font-bold text-green-700">Rp {{ number_format($totalIncomeAll, 0, ',', '.') }}</p>
        </div>
        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
            <p class="text-sm text-red-600 font-medium">Total Pengeluaran Semua Cabang</p>
            <p class="text-2xl font-bold text-red-700">Rp {{ number_format($totalExpenseAll, 0, ',', '.') }}</p>
        </div>
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-600 font-medium">Laba/Rugi Bersih</p>
            <p class="text-2xl font-bold {{ ($totalIncomeAll - $totalExpenseAll) >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                Rp {{ number_format($totalIncomeAll - $totalExpenseAll, 0, ',', '.') }}
            </p>
        </div>
    </div>
</div>
@endsection