@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100">Pesanan Baru</h1>
        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Isi form, tambahkan ke draft, ulangi untuk beberapa pesanan, lalu simpan semua sekaligus.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-3 items-start">
        {{-- FORM: 2 kolom --}}
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100">
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
                        <input type="date" id="f_date" value="{{ now()->toDateString() }}"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Nama Pelanggan</span>
                        <select id="f_customer_id"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">Pilih pelanggan</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-name="{{ $c->name }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Produk</span>
                        <input type="text" id="f_product_name" placeholder="Contoh: Print dtf"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Keterangan</span>
                        <select id="f_status"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">Pilih status</option>
                            @foreach(['waiting'=>'Diambil','processing'=>'Diproses','printed'=>'Dicetak','completed'=>'Selesai','cancelled'=>'Dibatalkan'] as $k=>$v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-3 mt-5">
                    <label class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Qty/m</span>
                        <input type="number" step=".01" id="f_qty" placeholder="0.00" oninput="calcTotal()"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                    </label>

                    <label class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Harga Satuan</span>
                        <div class="relative mt-1.5">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400 dark:text-slate-500">Rp</span>
                            <input type="number" id="f_price" placeholder="25000" oninput="calcTotal()"
                                   class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        </div>
                    </label>

                    <div class="block">
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Total</span>
                        <div id="f_totalDisplay" class="mt-1.5 w-full rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">Rp 0</div>
                    </div>
                </div>

                <label class="block mt-5">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Catatan Tambahan</span>
                    <textarea id="f_note" rows="2" placeholder="Tambahkan catatan jika diperlukan..."
                              class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"></textarea>
                </label>

                <div id="f_error" class="mt-3 hidden text-sm text-rose-600 dark:text-rose-400"></div>

                <div class="mt-6">
                    <button type="button" onclick="addToDraft()"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-5 py-2.5 text-white text-sm font-medium hover:bg-slate-700 transition-all duration-200 shadow-sm dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Tambah ke Draft
                    </button>
                </div>
            </div>
        </div>

        {{-- DRAFT PANEL: 1 kolom, kanan --}}
        <div class="rounded-2xl border border-slate-200 bg-white dark:bg-slate-900 shadow-sm dark:border-slate-700 dark:text-slate-100 sticky top-4">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Draft Pesanan (<span id="draftCount">0</span>)</div>
                <button type="button" onclick="clearDraft()" class="text-xs text-rose-500 hover:underline">Kosongkan</button>
            </div>

            <div id="draftList" class="max-h-[420px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                <div id="draftEmpty" class="p-5 text-center text-sm text-slate-400 dark:text-slate-500">Belum ada draft.</div>
            </div>

            <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
                <div class="flex justify-between text-sm mb-3">
                    <span class="text-slate-500 dark:text-slate-400">Total Qty / Rp</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-100"><span id="draftQtySum">0</span> m &middot; <span id="draftTotalSum">Rp 0</span></span>
                </div>
                <button type="button" onclick="saveAllDrafts()" id="saveAllBtn" disabled
                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all duration-200 shadow-sm">
                    Simpan Semua (<span id="draftCount2">0</span>)
                </button>
            </div>
        </div>
    </div>

    {{-- Form asli, hanya dipakai buat submit bulk beneran ke server --}}
    <form method="POST" action="{{ route('orders.store-bulk') }}" id="bulkForm" class="hidden">
        @csrf
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <textarea name="orders_json" id="orders_json"></textarea>
    </form>
@endsection

@push('scripts')
<script>
    let drafts = [];

    function calcTotal() {
        const qty = parseFloat(document.getElementById('f_qty').value) || 0;
        const price = parseFloat(document.getElementById('f_price').value) || 0;
        document.getElementById('f_totalDisplay').textContent = 'Rp ' + (qty * price).toLocaleString('id-ID');
    }

    function showError(msg) {
        const el = document.getElementById('f_error');
        el.textContent = msg;
        el.classList.remove('hidden');
    }
    function clearError() {
        document.getElementById('f_error').classList.add('hidden');
    }

    function addToDraft() {
        clearError();
        const customerSelect = document.getElementById('f_customer_id');
        const customerId = customerSelect.value;
        const customerName = customerSelect.selectedOptions[0]?.dataset.name || '';
        const date = document.getElementById('f_date').value;
        const productName = document.getElementById('f_product_name').value.trim();
        const status = document.getElementById('f_status').value;
        const qty = parseFloat(document.getElementById('f_qty').value) || 0;
        const price = parseFloat(document.getElementById('f_price').value) || 0;
        const note = document.getElementById('f_note').value.trim();

        if (!date || !customerId || !productName || !status || qty <= 0 || price <= 0) {
            showError('Tanggal, pelanggan, produk, status, qty, dan harga wajib diisi.');
            return;
        }

        drafts.push({
            date, customer_id: customerId, customer_name: customerName,
            product_name: productName, status, qty, price,
            total: qty * price, note,
        });

        // reset field produk/qty/harga/catatan biar cepat input order berikutnya,
        // tanggal & pelanggan dibiarkan (biasanya sama untuk beberapa order berurutan)
        document.getElementById('f_product_name').value = '';
        document.getElementById('f_qty').value = '';
        document.getElementById('f_price').value = '';
        document.getElementById('f_note').value = '';
        calcTotal();

        renderDrafts();
    }

    function removeDraft(index) {
        drafts.splice(index, 1);
        renderDrafts();
    }

    function clearDraft() {
        if (drafts.length && !confirm('Kosongkan semua draft?')) return;
        drafts = [];
        renderDrafts();
    }

    const rupiah = (n) => 'Rp ' + Math.max(0, Math.round(n)).toLocaleString('id-ID');
    const statusLabel = {waiting:'Diambil',processing:'Diproses',printed:'Dicetak',completed:'Selesai',cancelled:'Dibatalkan'};

    function renderDrafts() {
        const list = document.getElementById('draftList');
        const empty = document.getElementById('draftEmpty');

        list.querySelectorAll('.draft-row').forEach(el => el.remove());

        if (drafts.length === 0) {
            empty.classList.remove('hidden');
        } else {
            empty.classList.add('hidden');
            drafts.forEach((d, i) => {
                const row = document.createElement('div');
                row.className = 'draft-row p-4 text-sm';
                row.innerHTML = `
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">${d.product_name}</div>
                            <div class="text-xs text-slate-400 dark:text-slate-500">${d.customer_name} &middot; ${d.date} &middot; ${statusLabel[d.status] || d.status}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">${d.qty} m &times; ${rupiah(d.price)}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">${rupiah(d.total)}</div>
                            <button type="button" onclick="removeDraft(${i})" class="mt-1 text-xs text-rose-500 hover:underline">Hapus</button>
                        </div>
                    </div>`;
                list.appendChild(row);
            });
        }

        const qtySum = drafts.reduce((s, d) => s + d.qty, 0);
        const totalSum = drafts.reduce((s, d) => s + d.total, 0);
        document.getElementById('draftCount').textContent = drafts.length;
        document.getElementById('draftCount2').textContent = drafts.length;
        document.getElementById('draftQtySum').textContent = qtySum.toLocaleString('id-ID');
        document.getElementById('draftTotalSum').textContent = rupiah(totalSum);
        document.getElementById('saveAllBtn').disabled = drafts.length === 0;
    }

    function saveAllDrafts() {
        if (drafts.length === 0) return;
        document.getElementById('orders_json').value = JSON.stringify(drafts);
        document.getElementById('bulkForm').submit();
    }
</script>
@endpush
