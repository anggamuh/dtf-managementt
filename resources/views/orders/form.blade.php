@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $order->exists ? 'Edit Pesanan' : 'Pesanan Baru' }}</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            {{ $order->exists ? 'Perbarui detail pesanan '.$order->order_number.'.' : 'Catat pesanan baru dari pelanggan.' }}
        </p>
    </div>

    <form method="POST"
          action="{{ $order->exists ? route('orders.update', $order) : route('orders.store') }}"
          class="max-w-3xl rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100">
        @csrf
        @if($order->exists)
            @method('PUT')
        @endif

        <input type="hidden" name="branch_id" value="{{ $order->branch_id }}">

        {{-- Section: order details --}}
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                </svg>
            </span>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Detail Pesanan</div>
        </div>

        <div class="p-6">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Tanggal</span>
                    <input type="date"
                           name="date"
                           value="{{ old('date', $order->date?->format('Y-m-d')) }}"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Pelanggan</span>
                    <select name="customer_id"
                            class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Pilih pelanggan</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" @selected(old('customer_id', $order->customer_id) == $c->id)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Produk</span>
                    <input type="text"
                           name="product_name"
                           value="{{ old('product_name', $order->product_name) }}"
                           placeholder="Contoh: Print dtf"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Keterangan</span>
                    <select name="status"
                            class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">Pilih status</option>
                        @foreach(['waiting'=>'Diambil','processing'=>'Diproses','printed'=>'Dicetak','completed'=>'Selesai','cancelled'=>'Dibatalkan'] as $k=>$v)
                            <option value="{{ $k }}" @selected(old('status', $order->status) == $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        {{-- Section: pricing --}}
        <div class="flex items-center gap-3 border-y border-slate-100 px-6 py-4 dark:border-slate-800">
            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h6m-3-12v16m-7-4h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                </svg>
            </span>
            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Perhitungan</div>
        </div>

        <div class="p-6">
            <div class="grid gap-5 md:grid-cols-3">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Qty/m</span>
                    <input type="number"
                           step=".01"
                           name="qty"
                           id="qtyInput"
                           value="{{ old('qty', $order->qty) }}"
                           placeholder="0.00"
                           oninput="calculateTotal()"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Harga Satuan</span>
                    <div class="relative mt-1.5">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400 dark:text-slate-500">Rp</span>
                        <input type="number"
                               name="price"
                               id="priceInput"
                               value="{{ old('price', $order->price) }}"
                               placeholder="25000"
                               oninput="calculateTotal()"
                               class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                    </div>
                </label>

                <div class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Total</span>
                    <div id="totalDisplay"
                         class="mt-1.5 w-full rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
                        Rp 0
                    </div>
                    <input type="hidden" name="total" id="totalInput" value="{{ old('total', $order->total ?? 0) }}">
                </div>
            </div>

            <label class="block mt-5">
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Catatan Tambahan</span>
                <textarea name="note"
                          rows="2"
                          placeholder="Tambahkan catatan jika diperlukan..."
                          class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">{{ old('note', $order->note) }}</textarea>
            </label>

            <div class="mt-6 flex gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-white text-sm font-medium hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md dark:bg-blue-500 dark:hover:bg-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
                <a href="{{ route('orders.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-all duration-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Batal
                </a>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    function calculateTotal() {
        const qty = parseFloat(document.getElementById('qtyInput').value) || 0;
        const price = parseFloat(document.getElementById('priceInput').value) || 0;
        const total = qty * price;

        document.getElementById('totalDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
        document.getElementById('totalInput').value = total;
    }

    // Calculate on page load if values exist
    document.addEventListener('DOMContentLoaded', calculateTotal);
</script>
@endpush