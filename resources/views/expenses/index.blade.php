@extends('layouts.app')

@section('title', 'Pengeluaran')

@section('content')
    <div x-data="expenseBulkActions()" class="space-y-4">
        {{-- Header --}}
        <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#0F172A] dark:text-[#F8FAFC] tracking-tight">Pengeluaran</h1>
                <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Pencatatan seluruh biaya operasional</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('imports.expenses.form', ['branch_id' => $branchId]) }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm font-medium text-[#0F172A] hover:bg-[#F8FAFC] transition-all duration-200 shadow-sm dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Paste Excel
                </a>
                <a class="inline-flex items-center gap-2 rounded-xl bg-[#2563EB] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1D4ED8] transition-all duration-200 shadow-sm hover:shadow-md dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]"
                   href="{{ route('expenses.create', ['branch_id' => $branchId]) }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Tambah
                </a>
            </div>
        </div>

        {{-- Bulk Actions Bar --}}
        <div x-show="selected.length > 0"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="flex rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-blue-900">
                    <span x-text="selected.length"></span> pengeluaran dipilih
                </span>
                <button @click="clearSelection()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    Batal pilih
                </button>
            </div>
            <button @click="confirmBulkDelete()" 
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-white text-sm font-medium hover:bg-rose-700 transition-all duration-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Hapus yang Dipilih
            </button>
        </div>

        {{-- Filters --}}
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <form method="GET" action="{{ route('expenses.index', ['branch_id' => $branchId]) }}" class="flex flex-wrap items-end gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Cari</label>
                    <svg class="absolute left-3 top-[2.1rem] -translate-y-1/2 w-4 h-4 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, kategori, metode..."
                        class="w-full rounded-xl border border-[#E2E8F0] bg-white pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:placeholder-[#64748B]">
                </div>
                <div class="w-full sm:w-auto">
                    <label class="block text-xs font-semibold text-[#64748B] mb-1.5 dark:text-[#94A3B8]">Kategori</label>
                    <select name="category" class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC]">
                        <option value="">Semua</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') == $category)>{{ $category }}</option>
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
                <button type="submit" class="rounded-xl bg-[#0F172A] px-5 py-2.5 text-white text-sm font-medium hover:bg-[#1E293B] transition-all duration-200 shadow-sm dark:bg-[#F8FAFC] dark:text-[#0F172A] dark:hover:bg-white">
                    <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 01-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category', 'date_start', 'date_end']))
                    <a href="{{ route('expenses.index', ['branch_id' => $branchId]) }}"
                       class="rounded-xl px-4 py-2.5 text-sm font-medium text-[#64748B] hover:text-[#0F172A] transition-all duration-200 dark:text-[#94A3B8] dark:hover:text-[#F8FAFC]">
                        <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Expenses Table --}}
        <div class="overflow-x-auto rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
            <table class="w-full text-sm">
                <thead class="bg-[#F8FAFC] border-b border-[#E2E8F0] text-left dark:bg-[#0B1220] dark:border-[#253247]">
                    <tr>
                        <th class="p-4 w-12">
                            <input type="checkbox"
                                   x-ref="selectAll"
                                   :checked="selected.length > 0 && selected.length === {{ $expenses->count() }}"
                                   @change="toggleAll($el.checked)"
                                   class="h-4 w-4 rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                        </th>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Tanggal</th>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Kategori</th>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Nama</th>
                        <th class="p-4 font-semibold text-[#64748B] text-right dark:text-[#94A3B8]">Jumlah</th>
                        <th class="p-4 font-semibold text-[#64748B] dark:text-[#94A3B8]">Metode</th>
                        <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Bukti</th>
                        <th class="p-4 font-semibold text-[#64748B] text-center dark:text-[#94A3B8]">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $e)
                        <tr class="border-b border-[#E2E8F0] hover:bg-[#F8FAFC] transition-colors duration-150 dark:border-[#253247] dark:hover:bg-[#172033]"
                            :class="{ 'bg-blue-50/30': selected.includes({{ $e->id }}) }">
                            <td class="p-4">
                                <input type="checkbox" 
                                       value="{{ $e->id }}"
                                       @change="toggle({{ $e->id }}, $el.checked)"
                                       :checked="selected.includes({{ $e->id }})"
                                       class="h-4 w-4 rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB]/20 dark:border-[#334155] dark:bg-[#111827]">
                            </td>
                            <td class="p-4 text-[#64748B] dark:text-[#94A3B8]">{{ $e->date->format('d/m/Y') }}</td>
                            <td class="p-4">
                                <span class="inline-flex rounded-lg bg-[#F1F5F9] px-2.5 py-1 text-xs font-medium text-[#0F172A] dark:bg-[#172033] dark:text-[#F8FAFC]">
                                    {{ $e->category }}
                                </span>
                            </td>
                            <td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $e->description }}</td>
                            <td class="p-4 text-right font-medium text-rose-600 dark:text-rose-400">Rp {{ number_format($e->amount,0,',','.') }}</td>
                            <td class="p-4">
                                <span class="inline-flex rounded-lg bg-[#F1F5F9] px-2.5 py-1 text-xs font-medium text-[#0F172A] dark:bg-[#172033] dark:text-[#F8FAFC]">
                                    {{ $e->payment_method }}
                                </span>
                            </td>
                            <td class="p-4 text-center">
                                @if($e->proof)
                                    <a href="{{ asset('storage/' . $e->proof) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 transition-colors dark:bg-emerald-500/10 dark:text-emerald-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Ada Bukti
                                    </a>
                                @else
                                    <span class="inline-flex items-center rounded-lg bg-[#F1F5F9] px-2.5 py-1 text-xs font-medium text-[#94A3B8] dark:bg-[#172033] dark:text-[#64748B]">Belum Ada</span>
                                @endif
                            </td>
                            <td class="p-4">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('expenses.edit', $e) }}"
                                       class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-blue-50 hover:text-[#2563EB] hover:border-blue-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-blue-500/10 dark:hover:text-blue-400 dark:hover:border-blue-500/30"
                                       title="Edit" aria-label="Edit pengeluaran">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="event.preventDefault(); showDeleteExpenseConfirmation('{{ $e->description }}', '{{ route('expenses.destroy', $e) }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#E2E8F0] text-[#64748B] hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 transition-all duration-150 dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-rose-500/10 dark:hover:text-rose-400 dark:hover:border-rose-500/30"
                                                title="Hapus" aria-label="Hapus pengeluaran">
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p class="font-medium text-[#0F172A] dark:text-[#F8FAFC]">Belum ada pengeluaran</p>
                                    <p class="text-sm">Pengeluaran akan muncul di sini setelah ditambahkan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $expenses->links() }}
        </div>

    {{-- Bulk Delete Confirmation Modal --}}
    <div x-show="showDeleteModal"
         x-cloak
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div @click="showDeleteModal = false" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
        <div x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative rounded-2xl bg-white shadow-2xl max-w-md w-full p-6 dark:bg-[#111827] dark:text-[#F8FAFC]">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-[#0F172A] dark:text-[#F8FAFC]">Konfirmasi Hapus</h3>
                    <p class="text-sm text-[#64748B] dark:text-[#94A3B8]">Tindakan ini tidak dapat dibatalkan</p>
                </div>
            </div>
            <p class="text-sm text-[#64748B] dark:text-[#94A3B8] mb-6">
                Apakah Anda yakin ingin menghapus <span class="font-semibold" x-text="selected.length"></span> pengeluaran yang dipilih?
            </p>
            <div class="flex gap-3 justify-end">
                <button @click="showDeleteModal = false" 
                    class="rounded-xl border border-[#E2E8F0] bg-white px-5 py-2.5 text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC] transition-all duration-200 dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">
                    Batal
                </button>
                <form action="{{ route('expenses.bulk-destroy') }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    <button type="submit" 
                            class="rounded-xl bg-rose-600 px-5 py-2.5 text-white text-sm font-medium hover:bg-rose-700 transition-all duration-200 shadow-sm hover:shadow-md">
                        Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
    </div>

    @push('scripts')
    <script>
        function showDeleteExpenseConfirmation(description, deleteUrl) {
            Swal.fire({
                title: 'Hapus Pengeluaran?',
                text: `Yakin ingin menghapus pengeluaran "${description}"?`,
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

        function expenseBulkActions() {
            return {
                selected: [],
                showDeleteModal: false,
                toggle(id, checked) {
                    if (checked) {
                        this.selected.push(id);
                    } else {
                        this.selected = this.selected.filter(s => s !== id);
                    }
                },
                toggleAll(checked) {
                    this.selected = checked ? @js($expenses->pluck('id')->toArray()) : [];
                },
                clearSelection() {
                    this.selected = [];
                },
                confirmBulkDelete() {
                    if (this.selected.length > 0) {
                        this.showDeleteModal = true;
                    }
                }
            }
        }
    </script>
    @endpush
@endsection