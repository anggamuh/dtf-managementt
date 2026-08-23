@extends('layouts.app')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-800">{{ $expense->exists ? 'Edit' : 'Tambah' }} Pengeluaran</h1>

    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
        @csrf
        @if($expense->exists) @method('PUT') @endif
        <input type="hidden" name="branch_id" value="{{ $expense->branch_id }}">

        <div class="grid gap-5 md:grid-cols-2">
            <label class="block"><span class="text-sm font-medium text-slate-700">Tanggal</span><input required type="date" name="date" value="{{ old('date', $expense->date?->format('Y-m-d')) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></label>
            <label class="block"><span class="text-sm font-medium text-slate-700">Kategori</span><select required name="category" id="expense-category" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">@foreach($categories as $category)<option value="{{ $category }}" @selected(old('category', $expense->category) === $category)>{{ $category }}</option>@endforeach</select></label>
            <label class="block md:col-span-2"><span class="text-sm font-medium text-slate-700">Nama pengeluaran</span><textarea required name="description" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">{{ old('description', $expense->description) }}</textarea></label>
            <div id="material-fields" class="contents">
                <label class="block"><span class="text-sm font-medium text-slate-700">Material</span><select name="material_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"><option value="">Pilih material</option>@foreach($materials as $material)<option value="{{ $material->id }}" @selected((string) old('material_id', $expense->material_id) === (string) $material->id)>{{ $material->name }} ({{ $material->unit }})</option>@endforeach</select></label>
                <label class="block"><span class="text-sm font-medium text-slate-700">Qty masuk</span><input type="number" min="0.01" step="0.01" name="quantity" value="{{ old('quantity', $expense->quantity) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></label>
            </div>
            <label class="block"><span class="text-sm font-medium text-slate-700">Jumlah</span><input required type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></label>
            <label class="block"><span class="text-sm font-medium text-slate-700">Metode pembayaran</span><input required type="text" name="payment_method" value="{{ old('payment_method', $expense->payment_method) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></label>
            <label class="block md:col-span-2">
                <span class="text-sm font-medium text-slate-700">Bukti Transaksi (Proof)</span>
                <div class="mt-2 flex items-center gap-4">
                    <div class="flex-1">
                        <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="hidden" id="proof-input" onchange="previewProof(this)">
                        <label for="proof-input" class="inline-flex cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600 hover:border-blue-400 hover:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <span>Pilih file (JPG, PNG, PDF, max 5MB)</span>
                        </label>
                    </div>
                    <div id="proof-preview" class="hidden">
                        <img id="proof-image" src="" alt="Preview" class="h-20 w-20 rounded-xl object-cover border border-slate-200 dark:border-slate-700">
                        <a id="proof-pdf-link" href="" target="_blank" class="hidden h-20 w-20 rounded-xl border border-slate-200 items-center justify-center bg-slate-50 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800">
                            <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                @if($expense->proof)
                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-xs text-slate-500">File saat ini:</span>
                        <a href="{{ asset('storage/' . $expense->proof) }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Lihat Bukti</a>
                        <button type="button" onclick="document.getElementById('proof-input').click()" class="text-sm text-slate-600 hover:text-slate-700">Ganti</button>
                    </div>
                @endif
            </label>
        </div>
        <p id="material-help" class="mt-4 text-sm text-slate-500">Bahan Baku adalah pembelian material: qty dan nilai pembelian akan memperbarui stok serta harga rata-rata tertimbang.</p>
        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors shadow-sm">Simpan</button>
            <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Batal</a>
        </div>
    </form>
    <script>const category=document.getElementById('expense-category'), fields=document.getElementById('material-fields'), help=document.getElementById('material-help'); function toggle(){const material=category.value==='Bahan Baku'; fields.style.display=material?'contents':'none'; help.style.display=material?'block':'none';} category.addEventListener('change',toggle); toggle();</script>
@endsection
