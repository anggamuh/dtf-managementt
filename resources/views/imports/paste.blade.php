@extends('layouts.app')
@section('title', $type === 'income' ? 'Impor Pemasukan' : 'Impor Pengeluaran')
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ $type === 'income' ? route('invoices.index',['branch_id'=>$branchId]) : route('expenses.index',['branch_id'=>$branchId]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Kembali</a>
    <div class="mt-5 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
        <div class="border-b border-slate-100 bg-slate-950 px-7 py-8 text-white">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">Import Spreadsheet</p>
            <h1 class="mt-2 text-2xl font-bold">{{ $type === 'income' ? 'Paste pemasukan dari Excel' : 'Paste pengeluaran dari Excel' }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">Salin tabel langsung dari Excel lalu paste di area bawah. Baris judul otomatis diabaikan, termasuk format tanggal Indonesia dan nominal Rupiah.</p>
        </div>
        <form method="POST" action="{{ $type === 'income' ? route('imports.income') : route('imports.expenses') }}" class="p-7">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            @if($type === 'expense' && isset($machines) && $machines->isNotEmpty())
                <label class="mb-5 block"><span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Mesin <b class="text-rose-500">*</b></span><select name="machine_id" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">Pilih mesin tujuan import</option>@foreach($machines as $machine)<option value="{{ $machine->id }}" @selected((string)old('machine_id')===(string)$machine->id)>{{ $machine->name }}</option>@endforeach</select><span class="mt-2 block text-xs text-slate-500 dark:text-slate-400">Semua material Bahan Baku pada paste ini hanya akan dicocokkan ke mesin tersebut.</span></label>
            @endif
            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                @if($type === 'income')<span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Tanggal</span><span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Nama Pelanggan</span><span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Produk</span><span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Qty</span><span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Harga</span><span class="rounded-full bg-blue-50 px-3 py-1.5 text-blue-700">Total</span>@else<span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">Tanggal</span><span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">Jenis Pengeluaran</span><span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">Rincian</span><span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-700">Total</span>@endif
            </div>
            <textarea name="paste_data" rows="15" required placeholder="Paste tabel Excel di sini..." class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4 font-mono text-sm leading-6 outline-none transition focus:border-blue-500 focus:bg-white dark:focus:bg-slate-800 focus:ring-4 focus:ring-blue-100 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">{{ old('paste_data') }}</textarea>
            <div class="mt-5 flex items-center justify-between gap-4"><p class="text-xs leading-5 text-slate-500">Data akan divalidasi sebelum disimpan. Setiap customer dan produk baru akan disimpan di cabang aktif.</p><button class="shrink-0 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700">Import Data</button></div>
        </form>
    </div>
</div>
@endsection
