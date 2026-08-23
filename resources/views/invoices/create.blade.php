@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Buat Invoice</h1>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Tagihkan pesanan yang sudah selesai ke customer.</p>
        </div>
        <a href="{{ route('invoices.index', ['branch_id' => $branchId]) }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
    </div>

    {{-- Step 1: choose customer & billing period --}}
    <form method="GET" action="{{ route('invoices.create') }}"
          class="mt-5 rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100">
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900">1</span>
            <div>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pilih Customer &amp; Periode</div>
                <div class="text-xs text-slate-400 dark:text-slate-500">Tentukan siapa yang ditagih dan rentang tanggal pesanannya.</div>
            </div>
        </div>

        <div class="p-5">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Customer</label>
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <select name="customer_id" required
                                class="w-full appearance-none rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
                            <option value="">Pilih customer&hellip;</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Periode dari</label>
                    <input type="date" name="date_start" value="{{ $dateStart }}" required
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Sampai</label>
                    <input type="date" name="date_end" value="{{ $dateEnd }}" required
                           class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]">
                </div>
            </div>
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-white text-sm font-medium hover:bg-slate-700 transition-all duration-200 shadow-sm dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                    Muat Pesanan
                </button>
                <span class="text-xs text-slate-400 dark:text-slate-500">Menampilkan pesanan berstatus "Selesai" yang belum ditagihkan di rentang tanggal ini.</span>
            </div>
        </div>
    </form>

    {{-- Step 2: pick which orders go into this invoice --}}
    @if($customerId)
        <form method="POST" action="{{ route('invoices.store', ['branch_id' => $branchId]) }}" id="invoice-store-form" class="mt-5">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customerId }}">
            <input type="hidden" name="period_start" value="{{ $dateStart }}">
            <input type="hidden" name="period_end" value="{{ $dateEnd }}">

            <div class="rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100">
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-slate-800 text-xs font-bold text-white dark:bg-slate-100 dark:text-slate-900">2</span>
                    <div>
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Pilih Pesanan</div>
                        <div class="text-xs text-slate-400 dark:text-slate-500">Uncheck pesanan yang tidak ingin dimasukkan ke invoice ini.</div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/80 border-b border-slate-200 text-left dark:bg-slate-800/60 dark:border-slate-700">
                            <tr>
                                <th class="p-4 w-10">
                                    <input type="checkbox" id="select-all-orders" checked
                                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-600/20 dark:border-slate-600 dark:bg-slate-800">
                                </th>
                                <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Tanggal</th>
                                <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Nomor</th>
                                <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Produk</th>
                                <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Qty</th>
                                <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition-colors dark:border-slate-800 dark:hover:bg-slate-800/40">
                                    <td class="p-4">
                                        <input type="checkbox" name="order_ids[]" value="{{ $o->id }}" checked
                                               data-total="{{ $o->total }}"
                                               class="order-select-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800">
                                    </td>
                                    <td class="p-4 text-slate-600 dark:text-slate-400">{{ $o->date->format('d/m/Y') }}</td>
                                    <td class="p-4 font-medium text-slate-800 dark:text-slate-100">{{ $o->order_number }}</td>
                                    <td class="p-4 text-slate-700 dark:text-slate-300">{{ $o->product_name }}</td>
                                    <td class="p-4 text-right text-slate-700 dark:text-slate-300">{{ $o->qty }}</td>
                                    <td class="p-4 text-right font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($o->total,0,',','.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-10 text-center text-slate-400 dark:text-slate-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Tidak ada pesanan selesai yang belum ditagihkan untuk customer ini di rentang tanggal tersebut.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($orders->isNotEmpty())
                    <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Penyesuaian</div>
                        <div class="flex flex-wrap gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Diskon (Rp)</label>
                                <input type="number" name="discount" id="discountInput" value="0" min="0"
                                       class="w-40 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Dibayar Sekarang (Rp)</label>
                                <input type="number" name="paid" id="paidInput" value="0" min="0"
                                       class="w-40 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Ringkasan</div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-500 dark:text-slate-400"><span id="selectedCount">0</span> pesanan dipilih</dt>
                                <dd class="font-medium text-slate-700 dark:text-slate-200" id="subtotalDisplay">Rp 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500 dark:text-slate-400">Diskon</dt>
                                <dd class="font-medium text-rose-600 dark:text-rose-400" id="discountDisplay">&ndash; Rp 0</dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-2 dark:border-slate-700">
                                <dt class="font-semibold text-slate-700 dark:text-slate-200">Total Tagihan</dt>
                                <dd class="font-semibold text-slate-900 dark:text-slate-50" id="totalDisplay">Rp 0</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-slate-500 dark:text-slate-400">Dibayar sekarang</dt>
                                <dd class="font-medium text-emerald-600 dark:text-emerald-400" id="paidDisplay">Rp 0</dd>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-2 dark:border-slate-700">
                                <dt class="font-semibold text-slate-700 dark:text-slate-200">Sisa Piutang</dt>
                                <dd class="font-semibold text-amber-600 dark:text-amber-400" id="remainingDisplay">Rp 0</dd>
                            </div>
                        </dl>

                        <button class="mt-5 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-white text-sm font-medium hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md dark:bg-blue-500 dark:hover:bg-blue-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Buat Invoice
                        </button>
                    </div>
                </div>
            @endif
        </form>
    @endif

    <script>
        (function () {
            const form = document.getElementById('invoice-store-form');
            if (!form) return;

            const selectAll = document.getElementById('select-all-orders');
            const rowCheckboxes = () => document.querySelectorAll('.order-select-checkbox');
            const discountInput = document.getElementById('discountInput');
            const paidInput = document.getElementById('paidInput');

            const rupiah = (n) => 'Rp ' + Math.max(0, Math.round(n)).toLocaleString('id-ID');

            function recalc() {
                const checked = Array.from(rowCheckboxes()).filter(cb => cb.checked);
                const subtotal = checked.reduce((sum, cb) => sum + parseFloat(cb.dataset.total || 0), 0);
                const discount = parseFloat(discountInput.value) || 0;
                const paid = parseFloat(paidInput.value) || 0;
                const total = Math.max(0, subtotal - discount);
                const remaining = Math.max(0, total - paid);

                document.getElementById('selectedCount').textContent = checked.length;
                document.getElementById('subtotalDisplay').textContent = rupiah(subtotal);
                document.getElementById('discountDisplay').textContent = '\u2013 ' + rupiah(discount);
                document.getElementById('totalDisplay').textContent = rupiah(total);
                document.getElementById('paidDisplay').textContent = rupiah(paid);
                document.getElementById('remainingDisplay').textContent = rupiah(remaining);
            }

            selectAll?.addEventListener('change', function (e) {
                rowCheckboxes().forEach(cb => cb.checked = e.target.checked);
                recalc();
            });
            rowCheckboxes().forEach(cb => cb.addEventListener('change', recalc));
            discountInput?.addEventListener('input', recalc);
            paidInput?.addEventListener('input', recalc);

            recalc();
        })();
    </script>
@endsection