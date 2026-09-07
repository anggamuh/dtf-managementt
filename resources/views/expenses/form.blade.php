@extends('layouts.app')

@section('title', $expense->exists ? 'Edit Pengeluaran' : 'Tambah Pengeluaran')

@section('content')

@php
    $activeBranch = $branches->firstWhere('id', $expense->branch_id);
@endphp

<div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">

    <div>

        <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[.15em] text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">

            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>

            Finance workspace

        </div>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
            {{ $expense->exists ? 'Edit pengeluaran' : 'Catat pengeluaran baru' }}
        </h1>

        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Rekam biaya operasional dan pembelian bahan dengan bukti transaksi yang dapat ditelusuri.
        </p>

    </div>

    <a
        href="{{ route('expenses.index', ['branch_id' => $expense->branch_id]) }}"
        class="app-btn app-btn-secondary min-h-11"
    >
        ← Kembali ke daftar
    </a>

</div>


@if($errors->any())

    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
        {{ $errors->first() }}
    </div>

@endif


{{-- =========================================================
FORM
========================================================= --}}

<form
    method="POST"
    action="{{ $expense->exists
        ? route('expenses.update', $expense)
        : route('expenses.store') }}"
    enctype="multipart/form-data"
    class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
    x-data="{ submitting: false }"
    @submit="
        if (submitting) {
            $event.preventDefault()
        } else {
            submitting = true
        }
    "
>

    {{-- CSRF --}}
    @csrf

    <input
        type="hidden"
        name="branch_id"
        value="{{ $expense->branch_id }}"
    >


    <div class="space-y-6">


        {{-- =====================================================
        DETAIL TRANSAKSI
        ====================================================== --}}

        <section class="app-card p-5 sm:p-7">

            <div class="mb-6">

                <h2 class="font-bold text-slate-900 dark:text-white">
                    Detail transaksi
                </h2>

                <p class="text-xs text-slate-500">
                    Informasi utama pengeluaran
                </p>

            </div>


            <div class="grid gap-5 md:grid-cols-2">


                {{-- TANGGAL --}}

                <label>

                    <span class="text-sm font-semibold">
                        Tanggal transaksi
                        <b class="text-rose-500">*</b>
                    </span>

                    <input
                        required
                        type="date"
                        name="date"
                        value="{{ old(
                            'date',
                            $expense->date?->format('Y-m-d')
                        ) }}"
                        class="app-input mt-2"
                    >

                </label>


                {{-- KATEGORI --}}

                <label>

                    <span class="text-sm font-semibold">
                        Kategori
                        <b class="text-rose-500">*</b>
                    </span>

                    <select
                        required
                        name="category"
                        id="expense-category"
                        class="app-input mt-2"
                    >

                        @foreach($categories as $category)

                            <option
                                value="{{ $category }}"
                                @selected(
                                    old(
                                        'category',
                                        $expense->category
                                    ) === $category
                                )
                            >
                                {{ $category }}
                            </option>

                        @endforeach

                    </select>

                </label>


                {{-- DESKRIPSI --}}

                <label class="md:col-span-2">

                    <span class="text-sm font-semibold">
                        Nama atau keterangan pengeluaran
                        <b class="text-rose-500">*</b>
                    </span>

                    <textarea
                        required
                        name="description"
                        rows="3"
                        class="app-input mt-2 resize-y"
                    >{{ old('description', $expense->description) }}</textarea>

                </label>


                {{-- =================================================
                MATERIAL FIELDS
                ================================================== --}}

                <div
                    id="material-fields"
                    class="contents"
                >

                    {{-- MESIN --}}

                    @if($machines->isNotEmpty())

                        <label>

                            <span class="text-sm font-semibold">
                                Mesin
                                <b class="text-rose-500">*</b>
                            </span>

                            <select
                                id="expense-machine"
                                name="machine_id"
                                class="app-input mt-2"
                            >

                                <option value="">
                                    Pilih mesin
                                </option>

                                @foreach($machines as $machine)

                                    <option
                                        value="{{ $machine->id }}"
                                        @selected(
                                            (string) old(
                                                'machine_id',
                                                $expense->material?->machine_id
                                            ) ===
                                            (string) $machine->id
                                        )
                                    >
                                        {{ $machine->name }}
                                    </option>

                                @endforeach

                            </select>

                        </label>

                    @endif


                    {{-- MATERIAL --}}

                    <label>

                        <span class="text-sm font-semibold">
                            Material
                            <b class="text-rose-500">*</b>
                        </span>

                        <select
                            id="expense-material"
                            name="material_id"
                            class="app-input mt-2"
                        >

                            <option value="">
                                Pilih material
                            </option>

                            @foreach($materials as $material)

                                <option
                                    value="{{ $material->id }}"
                                    data-machine="{{ $material->machine_id }}"
                                    @selected(
                                        (string) old(
                                            'material_id',
                                            $expense->material_id
                                        ) ===
                                        (string) $material->id
                                    )
                                >
                                    {{ $material->display_name }}
                                    ·
                                    {{ $material->unit }}
                                </option>

                            @endforeach

                        </select>

                    </label>


                    {{-- QTY --}}

                    <label>

                        <span class="text-sm font-semibold">
                            Qty masuk
                            <b class="text-rose-500">*</b>
                        </span>

                        <input
                            type="number"
                            min="0.01"
                            step="0.01"
                            name="quantity"
                            value="{{ old(
                                'quantity',
                                $expense->quantity
                            ) }}"
                            class="app-input mt-2"
                            placeholder="0"
                        >

                    </label>

                </div>


                {{-- JUMLAH --}}

                <label>

                    <span class="text-sm font-semibold">
                        Jumlah pengeluaran
                        <b class="text-rose-500">*</b>
                    </span>

                    <div class="relative mt-2">

                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-blue-600">
                            Rp
                        </span>

                        <input
                            required
                            type="number"
                            min="0.01"
                            step="0.01"
                            name="amount"
                            value="{{ old(
                                'amount',
                                $expense->amount
                            ) }}"
                            class="app-input pl-12"
                        >

                    </div>

                </label>


                {{-- METODE PEMBAYARAN --}}

                <label>

                    <span class="text-sm font-semibold">
                        Metode pembayaran
                        <b class="text-rose-500">*</b>
                    </span>

                    <input
                        required
                        name="payment_method"
                        value="{{ old(
                            'payment_method',
                            $expense->payment_method
                        ) }}"
                        class="app-input mt-2"
                    >

                </label>

            </div>


            {{-- HELP MATERIAL --}}

            <div
                id="material-help"
                class="mt-5 rounded-2xl border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-800 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200"
            >

                <b>Pembelian bahan baku:</b>

                pilih mesin terlebih dahulu;
                stok hanya bertambah pada material terpilih.

            </div>

        </section>


        {{-- =====================================================
        BUKTI TRANSAKSI
        ====================================================== --}}

        <section class="app-card p-5 sm:p-7">

            <h2 class="font-bold">
                Bukti transaksi
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                JPG, PNG, atau PDF · maksimum 5 MB
            </p>


            <input
                type="file"
                name="proof"
                accept=".jpg,.jpeg,.png,.pdf"
                class="app-input mt-4"
            >


            @if($expense->proof)

                <a
                    href="{{ asset('storage/' . $expense->proof) }}"
                    target="_blank"
                    class="mt-3 inline-block text-sm font-semibold text-blue-600"
                >
                    Lihat bukti saat ini ↗
                </a>

            @endif

        </section>

    </div>


    {{-- =========================================================
    SIDEBAR
    ========================================================== --}}

    <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">

        <section class="app-card p-5">

            <span class="text-[10px] font-bold uppercase tracking-[.16em] text-slate-400">
                Ringkasan
            </span>

            <div class="mt-4 flex justify-between text-sm">

                <span class="text-slate-500">
                    Cabang
                </span>

                <b>
                    {{ $activeBranch?->name ?? 'Cabang aktif' }}
                </b>

            </div>

        </section>


        <div class="grid gap-3">


            {{-- SUBMIT --}}

            <button
                class="app-btn app-btn-primary min-h-12 w-full"
                type="submit"
                :disabled="submitting"
            >

                <span x-show="!submitting">

                    {{ $expense->exists
                        ? 'Simpan Perubahan'
                        : 'Catat Pengeluaran'
                    }}

                </span>

                <span x-show="submitting">
                    Menyimpan...
                </span>

            </button>


            {{-- BATAL --}}

            <a
                href="{{ route(
                    'expenses.index',
                    ['branch_id' => $expense->branch_id]
                ) }}"
                class="app-btn app-btn-secondary min-h-12 w-full"
            >
                Batal
            </a>

        </div>

    </aside>

</form>


{{-- =============================================================
JAVASCRIPT
============================================================= --}}

<script>

const category =
    document.getElementById('expense-category');

const fields =
    document.getElementById('material-fields');

const help =
    document.getElementById('material-help');

const machine =
    document.getElementById('expense-machine');

const material =
    document.getElementById('expense-material');


/*
|--------------------------------------------------------------------------
| Filter Material berdasarkan Mesin
|--------------------------------------------------------------------------
*/

function filterMaterials() {

    if (!machine || !material) {
        return;
    }

    const selected = machine.value;

    Array.from(material.options).forEach(
        (option, index) => {

            if (index === 0) {
                return;
            }

            const hidden =
                !selected ||
                option.dataset.machine !== selected;

            option.hidden = hidden;
            option.disabled = hidden;

        }
    );


    if (
        material.selectedOptions[0]?.disabled
    ) {
        material.value = '';
    }

}


/*
|--------------------------------------------------------------------------
| Toggle Field Bahan Baku
|--------------------------------------------------------------------------
*/

function toggleMaterial() {

    const active =
        category.value === 'Bahan Baku';


    fields.style.display =
        active ? 'contents' : 'none';


    help.style.display =
        active ? 'block' : 'none';


    fields
        .querySelectorAll('select, input')
        .forEach(el => {

            el.required = active;

        });


    filterMaterials();

}


category.addEventListener(
    'change',
    toggleMaterial
);


machine?.addEventListener(
    'change',
    filterMaterials
);


toggleMaterial();

</script>

@endsection