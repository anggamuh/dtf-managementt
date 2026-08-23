@extends('layouts.app')

@section('title', 'Pesanan')

@section('content')
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC] tracking-tight">Pesanan</h1>
            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Kelola semua pesanan dan tracking produksi</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('imports.income.form', ['branch_id' => $branchId]) }}"
               class="inline-flex items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-medium text-[#0F172A] hover:bg-[#F8FAFC] transition-all duration-200 dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033] shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Paste Excel
            </a>
            <a href="{{ route('orders.create', ['branch_id' => $branchId]) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-[#2563EB] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1D4ED8] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Pesanan Baru
            </a>
            <button type="button" id="bulk-delete-btn" class="branch-required inline-flex items-center gap-2 rounded-xl bg-[#DC2626] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#B91C1C] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#EF4444] dark:hover:bg-[#F87171]" data-branch-title="Pilih cabang terlebih dahulu">
                Hapus Yang Dipilih
            </button>
        </div>
    </div>

    {{-- Hidden form used purely to submit the bulk delete request --}}
    <form id="bulk-order-form" action="{{ route('orders.bulk-destroy') }}" method="POST" class="hidden branch-required">
        @csrf
        @method('DELETE')
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <div id="bulk-order-ids"></div>
    </form>

    {{-- Filters --}}
    <form class="my-5 flex flex-wrap items-end gap-2" id="orders-filter-form">
        <div class="relative flex-1 min-w-[200px]">
            <x-heroicon-o-magnifying-glass class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-[#94A3B8]"/>
            <input name="search"
                   value="{{ request('search') }}"
                   placeholder="Cari nomor / customer"
                   class="w-full rounded-xl border border-[#E2E8F0] bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:placeholder-[#64748B]">
        </div>
        <select name="status"
                class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
            <option value="">Semua status</option>
            @foreach(['waiting'=>'Diambil','processing'=>'Diproses','printed'=>'Dicetak','completed'=>'Selesai','cancelled'=>'Dibatalkan'] as $k=>$v)
                <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-1.5">
            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8] dark:text-[#64748B] mb-1">Dari</label>
                <input type="date" name="date_start" value="{{ request('date_start') }}"
                       class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:[color-scheme:dark]">
            </div>
            <span class="mb-2.5 text-[#94A3B8] dark:text-[#64748B]">&ndash;</span>
            <div>
                <label class="block text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8] dark:text-[#64748B] mb-1">Sampai</label>
                <input type="date" name="date_end" value="{{ request('date_end') }}"
                       class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:[color-scheme:dark]">
            </div>
        </div>
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <button class="rounded-xl bg-[#0F172A] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1E293B] transition-all duration-200 shadow-sm dark:bg-[#F8FAFC] dark:text-[#0F172A] dark:hover:bg-white">Filter</button>
        @if(request()->hasAny(['search','status','date_start','date_end']))
            <a href="{{ route('orders.index', ['branch_id' => $branchId]) }}"
               class="rounded-xl px-4 py-2.5 text-sm font-medium text-[#64748B] hover:text-[#0F172A] transition-all duration-200 dark:text-[#94A3B8] dark:hover:text-[#F8FAFC]">Reset</a>
        @endif
    </form>

    {{-- Orders Table --}}
    <div class="overflow-x-auto rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <table class="w-full text-sm">
            <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                <tr>
                    <th class="p-4 font-semibold text-[#64748B] w-10 dark:text-[#94A3B8]">
                        <input type="checkbox" id="select-all-orders" class="rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                    </th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nomor</th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Tanggal Pesanan</th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Customer</th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Produk</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Qty</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Total</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Status</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] transition-colors duration-150 dark:border-[#253247] dark:hover:bg-[#172033]">
                        <td class="p-4">
                            <input type="checkbox" value="{{ $o->id }}" class="order-checkbox rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                        </td>
                        <td class="p-4">
                            <div class="text-sm font-medium text-[#0F172A] dark:text-[#F8FAFC]">{{ $o->order_number }}</div>
                        </td>
                        <td class="p-4 text-[#64748B] dark:text-[#94A3B8]">
                            {{ $o->date->format('d/m/Y') }}
                            @if($o->time)
                                <span class="text-xs text-[#94A3B8] dark:text-[#64748B]">{{ $o->time->format('H:i') }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $o->customer->name }}</td>
                        <td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $o->product_name }}</td>
                        <td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">{{ $o->qty }}</td>
                        <td class="p-4 text-right font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($o->total,0,',','.') }}</td>
                        <td class="p-4 text-center">
                            @php
                                $statusConfig = [
                                    'waiting' => ['label' => 'Diambil', 'class' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'],
                                    'processing' => ['label' => 'Diproses', 'class' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20'],
                                    'printed' => ['label' => 'Dicetak', 'class' => 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-400 dark:border-violet-500/20'],
                                    'completed' => ['label' => 'Selesai', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20'],
                                    'cancelled' => ['label' => 'Dibatalkan', 'class' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20'],
                                ];
                                $status = $statusConfig[$o->status] ?? ['label' => $o->status, 'class' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700/40 dark:text-slate-300 dark:border-slate-600'];
                            @endphp
                            <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $status['class'] }}">
                                {{ $status['label'] }}
                            </span>
                            @if($o->invoice_id)
                                <div class="mt-1 text-[10px] font-medium text-[#94A3B8] dark:text-[#64748B]">Sudah ditagihkan</div>
                            @endif
                        </td>
                        <td class="p-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                @if($o->status !== 'completed')
                                    <form action="{{ route('orders.complete', $o) }}" method="POST" class="branch-required"
                                        onsubmit="event.preventDefault(); showCompleteOrderConfirmation('{{ $o->order_number }}', '{{ route('orders.complete', $o) }}');">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-300 transition-all duration-150 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                                title="Selesaikan" aria-label="Selesaikan pesanan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </button>
                                    </form>
                                @else
                                    <div class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                                         title="Sudah selesai">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                @endif
                                <a href="{{ route('orders.edit', $o) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-blue-50 hover:text-[#2563EB] hover:border-blue-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-blue-500/10 dark:hover:text-blue-400 dark:hover:border-blue-500/30"
                                   title="Edit" aria-label="Edit pesanan">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form action="{{ route('orders.destroy', $o) }}" method="POST" class="branch-required"
                                    onsubmit="event.preventDefault(); showDeleteOrderConfirmation('{{ $o->order_number }}', '{{ route('orders.destroy', $o) }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/30"
                                            title="Hapus pesanan" aria-label="Hapus pesanan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-10 text-center text-[#94A3B8] dark:text-[#64748B]">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-12 h-12 text-[#CBD5E1] dark:text-[#334155]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <p class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Belum ada pesanan</p>
                                <p class="text-sm">Pesanan akan muncul di sini setelah dibuat.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Per-page selector + pagination --}}
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8]">
            <span>Tampilkan</span>
            <select id="per-page-select"
                    class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                @foreach($perPageOptions ?? [25, 50, 100, 250, 500] as $option)
                    <option value="{{ $option }}" @selected(($perPage ?? 25) == $option)>{{ $option }}</option>
                @endforeach
            </select>
            <span>data</span>
            @if($orders->total() > 0)
                <span class="hidden sm:inline">&middot; Menampilkan {{ $orders->firstItem() }}&ndash;{{ $orders->lastItem() }} dari {{ $orders->total() }} data</span>
            @endif
        </div>

        @if($orders->hasPages())
            <nav class="flex items-center gap-1">
                @if($orders->onFirstPage())
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#94A3B8] dark:border-[#253247] dark:text-[#64748B]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </span>
                @else
                    <a href="{{ $orders->previousPageUrl() }}"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-[#F8FAFC] hover:border-[#CBD5E1] transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-[#172033] dark:hover:border-[#334155]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @endif

                @foreach($orders->getUrlRange(max(1, $orders->currentPage() - 2), min($orders->lastPage(), $orders->currentPage() + 2)) as $page => $url)
                    @if($page == $orders->currentPage())
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-[#2563EB] text-white text-sm font-medium dark:bg-[#3B82F6]">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}"
                           class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] text-sm hover:bg-[#F8FAFC] hover:border-[#CBD5E1] transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-[#172033] dark:hover:border-[#334155]">{{ $page }}</a>
                    @endif
                @endforeach

                @if($orders->hasMorePages())
                    <a href="{{ $orders->nextPageUrl() }}"
                       class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-[#F8FAFC] hover:border-[#CBD5E1] transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-[#172033] dark:hover:border-[#334155]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#94A3B8] dark:border-[#253247] dark:text-[#64748B]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </nav>
        @endif
    </div>

    <script>
        function showDeleteOrderConfirmation(orderNumber, deleteUrl) {
            Swal.fire({
                title: 'Hapus Pesanan?',
                text: `Yakin ingin menghapus pesanan ${orderNumber}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = deleteUrl;
                    form.innerHTML = '@csrf @method("DELETE")';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function showCompleteOrderConfirmation(orderNumber, completeUrl) {
            Swal.fire({
                title: 'Selesaikan Pesanan?',
                text: `Tandai pesanan ${orderNumber} sebagai selesai?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Selesaikan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = completeUrl;
                    form.innerHTML = '@csrf @method("PATCH")';
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        (function () {
            const selectAll = document.getElementById('select-all-orders');
            const checkboxes = () => document.querySelectorAll('.order-checkbox');

            selectAll.addEventListener('change', function (e) {
                checkboxes().forEach(cb => cb.checked = e.target.checked);
            });

            document.getElementById('bulk-delete-btn').addEventListener('click', function () {
                const selected = Array.from(checkboxes()).filter(cb => cb.checked);

                if (selected.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Peringatan',
                        text: 'Pilih minimal satu pesanan terlebih dahulu.',
                        confirmButtonColor: '#d97706'
                    });
                    return;
                }

                Swal.fire({
                    title: 'Hapus Pesanan?',
                    text: `Yakin ingin menghapus ${selected.length} pesanan yang dipilih?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Ya, Hapus',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const container = document.getElementById('bulk-order-ids');
                        container.innerHTML = '';
                        selected.forEach(cb => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'ids[]';
                            input.value = cb.value;
                            container.appendChild(input);
                        });
                        document.getElementById('bulk-order-form').submit();
                    }
                });
            });

            document.getElementById('per-page-select').addEventListener('change', function (e) {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', e.target.value);
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
        })();
    </script>
@endsection