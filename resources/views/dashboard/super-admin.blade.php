@extends('layouts.app')

@section('title', 'Dashboard Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard Super Admin</h1>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Periode:</span>
            <select id="monthSelect" class="border-gray-300 rounded-md text-sm">
                @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $m == date('m') ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                @endfor
            </select>
            <select id="yearSelect" class="border-gray-300 rounded-md text-sm">
                @for($y = 2025; $y <= 2030; $y++)
                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    <!-- Ringkasan -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <p class="text-sm text-green-600 font-medium">Total Pemasukan</p>
            <p class="text-2xl font-bold text-green-700">Rp {{ number_format($totalIncome, 0, ',', '.') }}</p>
        </div>
        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
            <p class="text-sm text-red-600 font-medium">Total Pengeluaran</p>
            <p class="text-2xl font-bold text-red-700">Rp {{ number_format($totalExpense, 0, ',', '.') }}</p>
        </div>
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <p class="text-sm text-blue-600 font-medium">Laba/Rugi</p>
            <p class="text-2xl font-bold {{ $profit >= 0 ? 'text-blue-700' : 'text-red-700' }}">Rp {{ number_format($profit, 0, ',', '.') }}</p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
            <p class="text-sm text-yellow-600 font-medium">Status Closing</p>
            <p class="text-xl font-bold {{ $closing ? 'text-green-700' : 'text-yellow-700' }}">
                {{ $closing ? 'CLOSED' : 'OPEN' }}
            </p>
        </div>
    </div>

    <!-- Input Pendapatan & Pengeluaran -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            @livewire('closing.income-input', ['branchId' => $branchId, 'month' => date('m'), 'year' => date('Y')])
        </div>
        <div>
            @livewire('closing.expense-input', ['branchId' => $branchId, 'month' => date('m'), 'year' => date('Y')])
        </div>
    </div>

    <!-- Data Pendapatan -->
        <div class="bg-white dark:bg-slate-800 dark:text-slate-100 rounded-lg shadow p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Data Pendapatan (Pemasukan)</h3>
        <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Pelanggan</th>
                        <th class="px-3 py-2 text-left">Produk</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Harga</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                    @forelse($invoices as $invoice)
                    <tr>
                        <td class="px-3 py-2">{{ $invoice->date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $invoice->customer->name }}</td>
                        <td class="px-3 py-2">
                            @foreach($invoice->items as $item)
                                {{ $item->product->name }}@if(!$loop->last), @endif
                            @endforeach
                        </td>
                        <td class="px-3 py-2 text-right">{{ $invoice->items->sum('qty') }}</td>
                        <td class="px-3 py-2 text-right">Rp {{ number_format($invoice->items->avg('price'), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-medium">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-center">
                            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Hapus invoice ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 text-xs">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-gray-500 py-4">Belum ada data pendapatan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Data Pengeluaran -->
    <div class="bg-white dark:bg-slate-800 dark:text-slate-100 rounded-lg shadow p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Data Pengeluaran</h3>
        <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                    <tr>
                        <th class="px-3 py-2 text-left">Tanggal</th>
                        <th class="px-3 py-2 text-left">Kategori</th>
                        <th class="px-3 py-2 text-left">Rincian</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-left">Ket</th>
                        <th class="px-3 py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-800 dark:divide-slate-700">
                    @forelse($expenses as $expense)
                    <tr>
                        <td class="px-3 py-2">{{ $expense->date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">{{ $expense->category }}</td>
                        <td class="px-3 py-2">{{ $expense->description }}</td>
                        <td class="px-3 py-2 text-right font-medium">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $expense->payment_method }}</td>
                        <td class="px-3 py-2 text-center">
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Hapus pengeluaran ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 text-xs">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-gray-500 py-4">Belum ada data pengeluaran</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tombol Generate Closing -->
    <div class="flex justify-end">
        <form method="POST" action="{{ route('closing.store') }}">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <input type="hidden" name="month" value="{{ date('m') }}">
            <input type="hidden" name="year" value="{{ date('Y') }}">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700">
                Generate Closing Otomatis
            </button>
        </form>
    </div>
</div>
@endsection