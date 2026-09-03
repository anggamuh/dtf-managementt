@extends('layouts.app')

@section('title', 'Invoice')

@section('content')
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC] tracking-tight">Invoice</h1>
            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Kelola invoice dan pembayaran pelanggan</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('invoices.create', ['branch_id' => $branchId]) }}"
               class="inline-flex items-center gap-2 rounded-xl bg-[#2563EB] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1D4ED8] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat dari Pesanan
            </a>
            <button type="button" id="bulk-delete-btn" class="branch-required inline-flex items-center gap-2 rounded-xl bg-[#DC2626] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#B91C1C] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#EF4444] dark:hover:bg-[#F87171]" data-branch-title="Pilih cabang terlebih dahulu">
                Hapus Yang Dipilih
            </button>
        </div>
    </div>

    {{-- Hidden form used purely to submit the bulk delete request --}}
    <form id="bulk-invoice-form" action="{{ route('invoices.bulk-destroy') }}" method="POST" class="hidden branch-required">
        @csrf
        @method('DELETE')
        <div id="bulk-invoice-ids"></div>
    </form>

    {{-- Filters --}}
    <div class="mt-6 rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <form class="flex flex-wrap items-end gap-3">
            <div class="relative flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Cari</label>
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-[2.1rem] -translate-y-1/2 w-4 h-4 text-[#94A3B8]"/>
                <input name="search"
                       value="{{ request('search') }}"
                       placeholder="Nomor / customer"
                       class="w-full rounded-xl border border-[#E2E8F0] bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:placeholder-[#64748B]">
            </div>
            <div class="w-full sm:w-auto">
                <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Status</label>
                <select name="status"
                        class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','partial'=>'Sebagian','paid'=>'Lunas'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Dari</label>
                    <input type="date" name="date_start" value="{{ request('date_start') }}"
                           class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:[color-scheme:dark]">
                </div>
                <span class="mb-2.5 text-[#94A3B8]">&ndash;</span>
                <div>
                    <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Sampai</label>
                    <input type="date" name="date_end" value="{{ request('date_end') }}"
                           class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:[color-scheme:dark]">
                </div>
            </div>
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
            <button class="rounded-xl bg-[#0F172A] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1E293B] transition-all duration-200 shadow-sm dark:bg-[#F8FAFC] dark:text-[#0F172A] dark:hover:bg-white">
                <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                Filter
            </button>
            @if(request()->hasAny(['search','status','date_start','date_end']))
                <a href="{{ route('invoices.index', ['branch_id' => $branchId]) }}"
                   class="rounded-xl px-4 py-2.5 text-sm font-medium text-[#64748B] hover:text-[#0F172A] transition-all duration-200 dark:text-[#94A3B8] dark:hover:text-[#F8FAFC]">
                    <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reset
                </a>
            @endif
        </form>
    </div>

    {{-- Invoices Table --}}
    <div class="mt-5 overflow-x-auto rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <table class="w-full text-sm">
            <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                <tr>
                    <th class="p-4 font-semibold text-[#64748B] w-10 dark:text-[#94A3B8]">
                        <input type="checkbox" id="select-all-invoices" class="rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                    </th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Invoice</th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Customer</th>
                    <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Periode</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Total</th>
                    <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Terbayar</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Status</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $i)
                    {{-- $i->live_total / $i->live_status dihitung ulang dari
                         orders yang terhubung, bukan dari kolom total/status
                         yang tersimpan — jadi selalu akurat walau order-nya
                         diedit belakangan. --}}
                    <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] transition-colors duration-150 dark:border-[#253247] dark:hover:bg-[#172033]">
                        <td class="p-4 w-10">
                            <input type="checkbox" value="{{ $i->id }}" class="invoice-checkbox rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                        </td>
                        <td class="p-4">
                            <div class="text-sm font-medium text-[#0F172A] dark:text-[#F8FAFC]">{{ $i->invoice_number }}</div>
                        </td>
                        <td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $i->customer->name }}</td>
                        <td class="p-4 text-[#64748B] dark:text-[#94A3B8]">
                            @if($i->period_start && $i->period_end)
                                {{ $i->period_start->format('d/m/Y') }} &ndash; {{ $i->period_end->format('d/m/Y') }}
                            @else
                                <span class="text-[#94A3B8]">&mdash;</span>
                            @endif
                        </td>
                        <td class="p-4 text-right font-medium text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($i->live_total,0,',','.') }}</td>
                        <td class="p-4 text-right text-[#64748B] dark:text-[#94A3B8]">Rp {{ number_format($i->paid,0,',','.') }}</td>
                        <td class="p-4 text-center">
                            @php
                                $statusConfig = [
                                    'paid' => ['label' => 'Lunas', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20'],
                                    'unpaid' => ['label' => 'Belum Bayar', 'class' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20'],
                                    'draft' => ['label' => 'Belum Bayar', 'class' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20'],
                                    'partial' => ['label' => 'Sebagian', 'class' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'],
                                ];
                                $status = $statusConfig[$i->live_status] ?? ['label' => $i->live_status, 'class' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-700/40 dark:text-slate-300 dark:border-slate-600'];
                            @endphp
                            <span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $status['class'] }}">
                                {{ $status['label'] }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('invoices.edit', $i) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-blue-50 hover:text-[#2563EB] hover:border-blue-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-blue-500/10 dark:hover:text-blue-400 dark:hover:border-blue-500/30"
                                   title="Edit invoice" aria-label="Edit invoice">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <a href="{{ route('invoices.print', $i) }}"
                                   target="_blank"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-300 transition-all duration-150 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                   title="Cetak Invoice" aria-label="Cetak invoice">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm3-12v3m0 0v3m0-3h3m-3 0H9"/>
                                    </svg>
                                </a>

                                <form action="{{ route('invoices.destroy', $i) }}" method="POST" class="branch-required"
                                    onsubmit="event.preventDefault(); showDeleteInvoiceConfirmation('{{ $i->invoice_number }}', '{{ route('invoices.destroy', $i) }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/30"
                                            title="Hapus invoice" aria-label="Hapus invoice">
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
                        <td colspan="8" class="p-10 text-center text-[#94A3B8] dark:text-[#64748B]">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-12 h-12 text-[#CBD5E1] dark:text-[#334155]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Belum ada invoice</p>
                                <p class="text-sm">Invoice akan muncul di sini setelah dibuat.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $invoices->links() }}
    </div>

    <script>
        function showDeleteInvoiceConfirmation(invoiceNumber, deleteUrl) {
            Swal.fire({
                title: 'Hapus Invoice?',
                text: `Yakin ingin menghapus invoice ${invoiceNumber}?`,
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

        (function () {
            const selectAll = document.getElementById('select-all-invoices');
            const checkboxes = () => document.querySelectorAll('.invoice-checkbox');

            if (selectAll) {
                selectAll.addEventListener('change', function (e) {
                    checkboxes().forEach(cb => cb.checked = e.target.checked);
                });
            }

            const bulkBtn = document.getElementById('bulk-delete-btn');
            if (bulkBtn) {
                bulkBtn.addEventListener('click', function () {
                    const selected = Array.from(checkboxes()).filter(cb => cb.checked);

                    if (selected.length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan',
                            text: 'Pilih minimal satu invoice terlebih dahulu.',
                            confirmButtonColor: '#d97706'
                        });
                        return;
                    }

                    Swal.fire({
                        title: 'Hapus Invoice?',
                        text: `Yakin ingin menghapus ${selected.length} invoice yang dipilih?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const ids = selected.map(cb => cb.value);
                            const form = document.getElementById('bulk-invoice-form');
                            document.getElementById('bulk-invoice-ids').innerHTML = ids.map(id => `<input type="hidden" name="ids[]" value="${id}">`).join('');
                            form.submit();
                        }
                    });
                });
            }
        })();
    </script>

@endsection