@extends('layouts.app')

@section('title', 'Material')

@section('content')
    <style>[x-cloak]{display:none !important;}</style>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC] tracking-tight">Material</h1>
            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Kelola stok bahan baku dan inventori</p>
        </div>
        <div class="flex flex-wrap gap-2">@if($machines->isNotEmpty())<a class="app-btn app-btn-secondary" href="{{ route('materials.split.index', ['branch_id' => $branchId]) }}">Pemisahan Material</a>@endif<a class="inline-flex items-center gap-2 rounded-xl bg-[#2563EB] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1D4ED8] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]"
           href="{{ route('materials.create', ['branch_id' => $branchId]) }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Material
        </a></div>
    </div>

    @if(session('message'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 shadow-sm dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-400">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-[#64748B] uppercase tracking-wider dark:text-[#94A3B8]">Total Pembelian</p>
                    <p class="mt-3 text-2xl font-bold text-[#0F172A] tracking-tight dark:text-[#F8FAFC]">Rp {{ number_format($totalValue,0,',','.') }}</p>
                    <p class="mt-2 text-xs text-[#64748B] dark:text-[#94A3B8]">
                        Total pembelian periode
                        {{ \Carbon\Carbon::parse($dateFrom ?? now()->startOfMonth())->format('d/m/Y') }}
                        -
                        {{ \Carbon\Carbon::parse($dateTo ?? now()->endOfMonth())->format('d/m/Y') }}.
                    </p>
                </div>
                <div class="app-icon bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="app-stat-card">
            <div class="flex items-start justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-[#64748B] uppercase tracking-wider dark:text-[#94A3B8]">Stok Minimum</p>
                    <p class="mt-3 text-2xl font-bold {{ $lowStockCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-[#0F172A] dark:text-[#F8FAFC]' }} tracking-tight">{{ $lowStockCount }}</p>
                    <p class="mt-2 text-xs text-[#64748B] dark:text-[#94A3B8]">Material di bawah stok minimum</p>
                </div>
                <div class="app-icon bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Material --}}
    <form
        class="mt-5 flex flex-col gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm dark:border-[#253247] dark:bg-[#111827]"
        method="GET"
    >
        <input type="hidden" name="branch_id" value="{{ $branchId }}">

        <div class="grid gap-3 lg:grid-cols-4">
            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">
                    Dari Tanggal
                </label>
                <input
                    class="app-input w-full"
                    type="date"
                    name="date_from"
                    value="{{ $dateFrom ?? request('date_from') }}"
                >
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">
                    Sampai Tanggal
                </label>
                <input
                    class="app-input w-full"
                    type="date"
                    name="date_to"
                    value="{{ $dateTo ?? request('date_to') }}"
                >
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">
                    Cari Material
                </label>
                <input
                    class="app-input w-full"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari Powder 4 Head..."
                >
            </div>

            @if($machines->isNotEmpty())
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">
                        Mesin
                    </label>
                    <select class="app-input w-full" name="machine_id">
                        <option value="">Semua Mesin</option>
                        @foreach($machines as $machine)
                            <option
                                value="{{ $machine->id }}"
                                @selected((string) request('machine_id') === (string) $machine->id)
                            >
                                {{ $machine->name }}
                            </option>
                        @endforeach
                        <option
                            value="unassigned"
                            @selected(request('machine_id') === 'unassigned')
                        >
                            Belum Ditentukan
                        </option>
                    </select>
                </div>
            @else
                <div class="flex items-end">
                    <button
                        type="submit"
                        class="app-btn app-btn-primary w-full"
                    >
                        Terapkan Filter
                    </button>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if($machines->isNotEmpty())
                <button
                    type="submit"
                    class="app-btn app-btn-primary"
                >
                    Terapkan Filter
                </button>
            @endif

            <a
                href="{{ route('materials.index', ['branch_id' => $branchId]) }}"
                class="app-btn app-btn-secondary"
            >
                Reset
            </a>

            <span class="ml-auto text-xs text-[#64748B] dark:text-[#94A3B8]">
                Nilai periode menggunakan perhitungan yang sama dengan Rincian Material pada Closing.
            </span>
        </div>
    </form>

    {{-- Materials Table --}}
    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100" x-data="{ opname: null }">
        <div class="border-b border-slate-200 p-5 dark:border-slate-700">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800 dark:text-white">Rincian Material</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Qty dan Pembelian berasal dari Expense kategori Bahan Baku.
                        @if($closing)
                            Stock Awal, Harga Komponen, dan Stock Akhir mengikuti snapshot Closing periode ini.
                        @else
                            Belum ada Closing untuk rentang ini; stock direkonstruksi dari movement dan Harga Komponen ditampilkan Rp0.
                        @endif
                    </p>
                </div>
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    Total Pembelian:
                    <span class="font-semibold text-slate-800 dark:text-white">
                        Rp{{ number_format($totalValue, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        <table class="w-full min-w-[980px] text-sm">
            <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                <tr>
                    <th class="p-3 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nama Komponen</th>
                    <th class="p-3 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Stock Awal</th>
                    <th class="p-3 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Harga Komponen</th>
                    <th class="p-3 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Qty</th>
                    <th class="p-3 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Pembelian</th>
                    <th class="p-3 text-right font-semibold text-[#64748B] dark:text-[#94A3B8]">Stock Akhir</th>
                    <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($materialGroups as $groupRows)
                    @php
                        $groupMaterial = $groupRows->first()['material'];
                        $machineName = $groupMaterial->machine?->name ?? 'Belum Ditentukan';
                        $machineSubtotal = $groupRows->sum('purchase_value');
                    @endphp
                    <tr class="bg-blue-50 dark:bg-blue-500/10">
                        <td colspan="7" class="p-3 text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">
                            {{ $machineName }}
                            <span class="float-right normal-case">
                                Subtotal pembelian: Rp{{ number_format($machineSubtotal, 0, ',', '.') }}
                            </span>
                        </td>
                    </tr>

                    @foreach($groupRows as $row)
                        @php
                            $m = $row['material'];
                            $isLowStock = (float) $m->stock <= (float) $m->minimum_stock;
                        @endphp
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50 dark:border-slate-700 dark:hover:bg-slate-900/50">
                        <td class="p-3 font-medium text-slate-800 dark:text-white">
                            {{ $m->display_name }}
                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $m->unit }}</span>
                            @if($isLowStock)
                                <span class="ml-2 inline-flex rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-400" title="Stok aktual {{ number_format((float) $m->stock, 2, ',', '.') }}; minimum {{ number_format((float) $m->minimum_stock, 2, ',', '.') }}">
                                    Low Stock
                                </span>
                            @endif
                        </td>
                        <td class="p-3 text-right text-slate-800 dark:text-white">{{ number_format($row['stock_awal'], 2, ',', '.') }}</td>
                        <td class="p-3 text-right font-medium text-slate-800 dark:text-white">Rp{{ number_format($row['unit_cost'], 0, ',', '.') }}</td>
                        <td class="p-3 text-right text-slate-800 dark:text-white">{{ $row['incoming_quantity'] > 0 ? number_format($row['incoming_quantity'], 2, ',', '.') : '' }}</td>
                        <td class="p-3 text-right font-semibold text-slate-800 dark:text-white">{{ $row['purchase_value'] > 0 ? 'Rp'.number_format($row['purchase_value'], 0, ',', '.') : '' }}</td>
                        <td class="p-3 text-right font-medium text-slate-800 dark:text-white">{{ number_format($row['stock_akhir'], 2, ',', '.') }}</td>
                        <td class="p-3">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" @click="opname = opname === {{ $m->id }} ? null : {{ $m->id }}"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-emerald-500/10 dark:hover:text-emerald-400 dark:hover:border-emerald-500/30"
                                        title="Stok Opname" aria-label="Stok opname">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                                <a href="{{ route('materials.edit', $m) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-blue-50 hover:text-[#2563EB] hover:border-blue-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-blue-500/10 dark:hover:text-blue-400 dark:hover:border-blue-500/30"
                                   title="Edit" aria-label="Edit material">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('materials.destroy', $m) }}" class="branch-required" onsubmit="event.preventDefault(); showDeleteMaterialConfirmation('{{ $m->display_name }}', '{{ route('materials.destroy', $m) }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/30"
                                            title="Hapus" aria-label="Hapus material">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="opname === {{ $m->id }}" x-cloak class="border-b border-[#E2E8F0] bg-emerald-50/40 dark:border-[#253247] dark:bg-emerald-500/5">
                        <td colspan="7" class="p-4">
                            <form method="POST" action="{{ route('materials.opname', $m) }}" class="flex flex-wrap items-end gap-3 branch-required">
                                @csrf
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Stok Fisik Sekarang</label>
                                    <input type="number" step=".01" name="stock" value="{{ $m->stock }}" required
                                        class="w-40 rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                                </div>
                                <div class="flex-1 min-w-[200px]">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-[#64748B] mb-1 dark:text-[#94A3B8]">Catatan</label>
                                    <input type="text" name="note" placeholder="cth. hasil hitung fisik akhir bulan"
                                        class="w-full rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:placeholder-[#64748B]">
                                </div>
                                <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition dark:bg-emerald-500 dark:hover:bg-emerald-600">
                                    Simpan Opname
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="7" class="p-10 text-center text-[#94A3B8] dark:text-[#64748B]">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-12 h-12 text-[#CBD5E1] dark:text-[#334155]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Belum ada material</p>
                                <p class="text-sm">Material akan muncul di sini setelah ditambahkan.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($materialRows->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-[#0F172A] dark:border-[#F8FAFC]">
                        <td colspan="4" class="p-3 font-bold text-[#0F172A] dark:text-[#F8FAFC]">
                            Total Pembelian Bahan Baku Periode
                        </td>
                        <td class="p-3 text-right font-bold text-[#0F172A] dark:text-[#F8FAFC]">Rp{{ number_format($totalValue,0,',','.') }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @push('scripts')
    <script>
        function showDeleteMaterialConfirmation(name, deleteUrl) {
            Swal.fire({
                title: 'Hapus Material?',
                text: `Yakin ingin menghapus material "${name}"?`,
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
    </script>
    @endpush
@endsection
