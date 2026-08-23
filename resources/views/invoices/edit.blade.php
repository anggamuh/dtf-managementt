@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Edit Invoice {{ $invoice->invoice_number }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $invoice->customer->name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank"
               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-all duration-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm3-12v3m0 0v3m0-3h3m-3 0H9"/>
                </svg>
                Cetak
            </a>
            <a href="{{ route('invoices.index', ['branch_id' => $invoice->branch_id]) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Kembali
            </a>
        </div>
    </div>

    {{-- Standalone GET form: only used to change which date range "Tambah Pesanan Lain"
         searches in below. Kept OUTSIDE the main update form on purpose — a <form>
         cannot be nested inside another <form>, browsers silently break the outer
         one if you try, which is why edits weren't saving before. --}}
    <div class="mt-5 rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm overflow-hidden dark:border-slate-700 dark:text-slate-100">
        <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3 dark:border-slate-700 dark:bg-slate-800/60">
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Cari Pesanan Lain untuk Ditambahkan</h2>
            <a href="{{ route('invoices.edit', $invoice) }}?date_start={{ optional($invoice->period_start)->toDateString() }}&date_end={{ optional($invoice->period_end)->toDateString() }}"
               class="text-xs text-blue-600 hover:underline dark:text-blue-400">gunakan periode invoice ini</a>
        </div>
        <form method="GET" action="{{ route('invoices.edit', $invoice) }}" class="px-5 py-3 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Dari</label>
                <input type="date" name="date_start" value="{{ $dateStart }}"
                       class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500">
            </div>
            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-1">Sampai</label>
                <input type="date" name="date_end" value="{{ $dateEnd }}"
                       class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]">
            </div>
            <button class="rounded-xl bg-slate-800 px-4 py-2 text-white text-sm font-medium hover:bg-slate-700 transition-all duration-200 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">Cari</button>
        </form>
    </div>

    {{-- Single form for everything that actually gets saved --}}
    <form method="POST" action="{{ route('invoices.update', $invoice) }}" id="invoice-update-form" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        {{-- Period + discount + paid --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Periode &amp; Pembayaran</div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Periode dari</label>
                        <input type="date" name="period_start" value="{{ optional($invoice->period_start)->toDateString() }}"
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Sampai</label>
                        <input type="date" name="period_end" value="{{ optional($invoice->period_end)->toDateString() }}"
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:[color-scheme:dark]">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Diskon (Rp)</label>
                        <input type="number" name="discount" id="discountInput" value="{{ $invoice->discount }}" min="0"
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1.5">Terbayar (Rp)</label>
                        <input type="number" name="paid" id="paidInput" value="{{ $invoice->paid }}" min="0"
                               class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    </div>
                </div>
            </div>
        </div>

        {{-- Current items --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Item di Invoice Ini</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50/50 border-b border-slate-200 text-left dark:bg-slate-800/60 dark:border-slate-700">
                        <tr>
                            <th class="p-4 w-10"></th>
                            <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Produk</th>
                            <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Qty</th>
                            <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Harga</th>
                            <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($invoice->items as $item)
                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                <td class="p-4">
                                    <input type="checkbox" name="remove_item_ids[]" value="{{ $item->id }}"
                                           data-subtotal="{{ $item->subtotal }}"
                                           class="remove-item-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500/30 dark:border-slate-600 dark:bg-slate-800"
                                           title="Centang untuk menghapus item ini">
                                </td>
                                <td class="p-4">
                                    <div class="font-medium text-slate-800 dark:text-slate-100">{{ $item->description }}</div>
                                    @if($item->order)
                                        <div class="text-xs text-slate-400 dark:text-slate-500">Pesanan {{ $item->order->order_number }} &middot; {{ $item->order->date->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td class="p-4 text-right text-slate-700 dark:text-slate-300">{{ $item->qty }}</td>
                                <td class="p-4 text-right text-slate-700 dark:text-slate-300">Rp {{ number_format($item->price,0,',','.') }}</td>
                                <td class="p-4 text-right font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($item->subtotal,0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-slate-400 dark:text-slate-500">Belum ada item.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-2 text-xs text-slate-400 dark:text-slate-500 border-t border-slate-100 dark:border-slate-800">Centang item lalu simpan untuk menghapusnya dari invoice (pesanan terkait akan dilepas dan bisa ditagihkan lagi).</div>
        </div>

        {{-- Add more orders (found via the search form above) --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </span>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Tambahkan ke Invoice Ini</div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50/50 border-b border-slate-200 text-left dark:bg-slate-800/60 dark:border-slate-700">
                        <tr>
                            <th class="p-4 w-10"></th>
                            <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Tanggal</th>
                            <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Nomor</th>
                            <th class="p-4 font-semibold text-slate-600 dark:text-slate-300">Produk</th>
                            <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Qty</th>
                            <th class="p-4 font-semibold text-slate-600 text-right dark:text-slate-300">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($availableOrders as $o)
                            <tr class="border-b border-slate-100 dark:border-slate-800">
                                <td class="p-4">
                                    <input type="checkbox" name="add_order_ids[]" value="{{ $o->id }}"
                                           data-total="{{ $o->total }}"
                                           class="add-order-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500/30 dark:border-slate-600 dark:bg-slate-800">
                                </td>
                                <td class="p-4 text-slate-600 dark:text-slate-400">{{ $o->date->format('d/m/Y') }}</td>
                                <td class="p-4 font-medium text-slate-800 dark:text-slate-100">{{ $o->order_number }}</td>
                                <td class="p-4 text-slate-700 dark:text-slate-300">{{ $o->product_name }}</td>
                                <td class="p-4 text-right text-slate-700 dark:text-slate-300">{{ $o->qty }}</td>
                                <td class="p-4 text-right font-medium text-slate-800 dark:text-slate-100">Rp {{ number_format($o->total,0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400 dark:text-slate-500">
                                    Tidak ada pesanan lain yang belum ditagihkan untuk customer ini di rentang tanggal tersebut.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Live summary --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800/40">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3">Ringkasan Setelah Perubahan</div>
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <dt class="text-slate-500 dark:text-slate-400">Subtotal</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-100" id="subtotalDisplay">Rp 0</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400">Diskon</dt>
                    <dd class="font-semibold text-rose-600 dark:text-rose-400" id="discountDisplay">&ndash; Rp 0</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400">Total Tagihan</dt>
                    <dd class="font-semibold text-slate-900 dark:text-slate-50" id="totalDisplay">Rp 0</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400">Sisa Piutang</dt>
                    <dd class="font-semibold text-amber-600 dark:text-amber-400" id="remainingDisplay">Rp 0</dd>
                </div>
            </dl>
        </div>

        <div class="flex justify-end">
            <button class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-white text-sm font-medium hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md dark:bg-blue-500 dark:hover:bg-blue-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Perubahan
            </button>
        </div>
    </form>

    <script>
        (function () {
            const form = document.getElementById('invoice-update-form');
            if (!form) return;

            const baseSubtotal = {{ (float) $invoice->items->sum('subtotal') }};
            const removeCheckboxes = () => document.querySelectorAll('.remove-item-checkbox');
            const addCheckboxes = () => document.querySelectorAll('.add-order-checkbox');
            const discountInput = document.getElementById('discountInput');
            const paidInput = document.getElementById('paidInput');

            const rupiah = (n) => 'Rp ' + Math.max(0, Math.round(n)).toLocaleString('id-ID');

            function recalc() {
                const removed = Array.from(removeCheckboxes())
                    .filter(cb => cb.checked)
                    .reduce((sum, cb) => sum + parseFloat(cb.dataset.subtotal || 0), 0);
                const added = Array.from(addCheckboxes())
                    .filter(cb => cb.checked)
                    .reduce((sum, cb) => sum + parseFloat(cb.dataset.total || 0), 0);

                const subtotal = baseSubtotal - removed + added;
                const discount = parseFloat(discountInput.value) || 0;
                const paid = parseFloat(paidInput.value) || 0;
                const total = Math.max(0, subtotal - discount);
                const remaining = Math.max(0, total - paid);

                document.getElementById('subtotalDisplay').textContent = rupiah(subtotal);
                document.getElementById('discountDisplay').textContent = '\u2013 ' + rupiah(discount);
                document.getElementById('totalDisplay').textContent = rupiah(total);
                document.getElementById('remainingDisplay').textContent = rupiah(remaining);
            }

            removeCheckboxes().forEach(cb => cb.addEventListener('change', recalc));
            addCheckboxes().forEach(cb => cb.addEventListener('change', recalc));
            discountInput?.addEventListener('input', recalc);
            paidInput?.addEventListener('input', recalc);

            recalc();
        })();
    </script>
@endsection