@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $material->exists ? 'Edit' : 'Tambah' }} Material</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                {{ $material->exists ? 'Perbarui detail material '.$material->name.'.' : 'Daftarkan material baru.' }}
            </p>
        </div>
        @if($material->exists && $material->minimum_stock !== null && $material->stock <= $material->minimum_stock)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                Stok Menipis
            </span>
        @endif
    </div>

    <form method="POST" action="{{ $material->exists ? route('materials.update', $material) : route('materials.store') }}"
          class="max-w-2xl rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100">
        @csrf
        @if($material->exists)
            @method('PUT')
        @endif
        <input type="hidden" name="branch_id" value="{{ $material->branch_id }}">

        {{-- Section: basic info --}}
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </span>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Informasi Material</div>
        </div>

        <div class="p-6">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block md:col-span-2">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Material</span>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $material->name) }}"
                           placeholder="Contoh: Tinta DTF Putih"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Satuan</span>
                    <input type="text"
                           name="unit"
                           value="{{ old('unit', $material->unit) }}"
                           placeholder="Contoh: liter, roll, pcs"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Supplier</span>
                    <input type="text"
                           name="supplier"
                           value="{{ old('supplier', $material->supplier) }}"
                           placeholder="Nama toko/supplier"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Stok Minimum</span>
                    <input type="number"
                           step=".01"
                           name="minimum_stock"
                           value="{{ old('minimum_stock', $material->minimum_stock) }}"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    <span class="mt-1 block text-xs text-slate-400 dark:text-slate-500">Batas bawah sebelum ditandai "Stok Menipis".</span>
                </label>
            </div>
        </div>

        {{-- Info: Automatic fields --}}
        <div class="border-t border-slate-100 dark:border-slate-800">
            <div class="p-6">
                <div class="rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 dark:bg-blue-500/10 dark:border-blue-500/20">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <p class="font-medium">Stok dan harga terisi otomatis</p>
                            <ul class="mt-1 list-disc list-inside text-xs space-y-0.5">
                                <li>Stok bertambah saat mencatat Expense kategori <strong>Bahan Baku</strong></li>
                                <li>Harga dihitung otomatis (rata-rata tertimbang dari pembelian)</li>
                                <li>Untuk menyesuaikan stok fisik, gunakan <strong>Stok Opname</strong> dari halaman daftar Material</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
            <div class="flex gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-white text-sm font-medium hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md dark:bg-blue-500 dark:hover:bg-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
                <a href="{{ route('materials.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all duration-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Batal
                </a>
            </div>
        </div>
    </form>
@endsection
