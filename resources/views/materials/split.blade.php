@extends('layouts.app')
@section('title', 'Pemisahan Material')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div><h1 class="text-2xl font-bold text-slate-900 dark:text-white">Pemisahan Material Lama</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Distribusikan stok lama ke master material per mesin. Histori transaksi lama tetap terhubung ke material asal.</p></div>
    <a href="{{ route('materials.index', ['branch_id' => $branchId]) }}" class="app-btn app-btn-secondary">Kembali</a>
</div>
@if(session('message'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">{{ session('message') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">{{ $errors->first() }}</div>@endif
<div class="space-y-5">
@forelse($materials as $material)
    <form method="POST" action="{{ route('materials.split.store', ['material' => $material, 'branch_id' => $branchId]) }}" class="app-card overflow-hidden">@csrf
        <div class="border-b border-slate-200 p-5 dark:border-slate-700"><div class="flex flex-wrap items-center justify-between gap-2"><div><h2 class="font-bold text-slate-900 dark:text-white">{{ $material->display_name }}</h2><p class="text-xs text-slate-500">Stok lama: {{ number_format($material->stock, 2, ',', '.') }} {{ $material->unit }}</p></div><span class="app-badge bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Belum Ditentukan</span></div></div>
        <div class="grid gap-4 p-5 lg:grid-cols-2">
            @foreach($machines as $machine)
            <section class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><h3 class="font-semibold text-slate-900 dark:text-white">{{ $machine->name }}</h3><div class="mt-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3"><label class="text-xs font-semibold text-slate-500">Stok<input class="app-input mt-1" required type="number" min="0" step=".01" name="allocations[{{ $machine->id }}][stock]" value="{{ old("allocations.{$machine->id}.stock", 0) }}"></label><label class="text-xs font-semibold text-slate-500">Harga standar<input class="app-input mt-1" required type="number" min="0" step=".01" name="allocations[{{ $machine->id }}][price]" value="{{ old("allocations.{$machine->id}.price", $material->price) }}"></label><label class="text-xs font-semibold text-slate-500">Stok minimum<input class="app-input mt-1" required type="number" min="0" step=".01" name="allocations[{{ $machine->id }}][minimum_stock]" value="{{ old("allocations.{$machine->id}.minimum_stock", $material->minimum_stock) }}"></label></div></section>
            @endforeach
        </div>
        <div class="flex justify-end border-t border-slate-200 p-4 dark:border-slate-700"><button class="app-btn app-btn-primary" onclick="return confirm('Pastikan total alokasi sama dengan stok lama. Lanjutkan pemisahan?')">Konfirmasi Pemisahan</button></div>
    </form>
@empty
    <div class="app-card p-10 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada material aktif berstatus Belum Ditentukan.</div>
@endforelse
</div>
@endsection
