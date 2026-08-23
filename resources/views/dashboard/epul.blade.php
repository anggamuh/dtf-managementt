@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">

        <div>

            <h2 class="text-3xl font-bold text-slate-800">
                Dashboard EPUL
            </h2>

            <p class="text-slate-500">
                Ringkasan aktivitas bulan {{ now()->translatedFormat('F Y') }}
            </p>

        </div>

        <button
            class="rounded-xl bg-blue-600 px-5 py-3 text-white font-semibold hover:bg-blue-700 transition">

            Generate Closing

        </button>

    </div>

    {{-- Card --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

        {{-- Omset --}}
        <div class="rounded-2xl bg-white dark:bg-slate-800 shadow-sm border border-slate-200 p-6 dark:border-slate-700 dark:text-slate-200">

            <div class="flex justify-between items-center">

                <div>

                    <p class="text-sm text-slate-500">
                        Omset Bulan Ini
                    </p>

                    <h2 class="mt-2 text-3xl font-bold">

                        Rp {{ number_format($totalIncome ?? 0,0,',','.') }}

                    </h2>

                </div>

                <div class="w-14 h-14 rounded-xl bg-blue-100 flex items-center justify-center">

                    <x-heroicon-o-banknotes class="w-7 h-7 text-blue-600"/>

                </div>

            </div>

        </div>

        {{-- Pengeluaran --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">

            <div class="flex justify-between items-center">

                <div>

                    <p class="text-sm text-slate-500">
                        Pengeluaran
                    </p>

                    <h2 class="mt-2 text-3xl font-bold">

                        Rp {{ number_format($totalExpense ?? 0,0,',','.') }}

                    </h2>

                </div>

                <div class="w-14 h-14 rounded-xl bg-red-100 flex items-center justify-center">

                    <x-heroicon-o-credit-card class="w-7 h-7 text-red-600"/>

                </div>

            </div>

        </div>

        {{-- Laba --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">

            <div class="flex justify-between items-center">

                <div>

                    <p class="text-sm text-slate-500">
                        Laba Bersih
                    </p>

                    <h2 class="mt-2 text-3xl font-bold text-green-600">

                        Rp {{ number_format(($totalIncome ?? 0)-($totalExpense ?? 0),0,',','.') }}

                    </h2>

                </div>

                <div class="w-14 h-14 rounded-xl bg-green-100 flex items-center justify-center">

                    <x-heroicon-o-arrow-trending-up class="w-7 h-7 text-green-600"/>

                </div>

            </div>

        </div>

        {{-- Invoice --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200">

            <div class="flex justify-between items-center">

                <div>

                    <p class="text-sm text-slate-500">
                        Invoice
                    </p>

                    <h2 class="mt-2 text-3xl font-bold">

                        {{ $invoiceCount ?? 0 }}

                    </h2>

                </div>

                <div class="w-14 h-14 rounded-xl bg-yellow-100 flex items-center justify-center">

                    <x-heroicon-o-document-text class="w-7 h-7 text-yellow-600"/>

                </div>

            </div>

        </div>

    </div>

    {{-- Chart + Closing --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6 dark:bg-slate-800 dark:text-slate-200">

            <div class="flex justify-between">

                <div>

                    <h3 class="font-semibold text-lg">
                        Grafik Omset
                    </h3>

                    <p class="text-sm text-slate-500">
                        12 Bulan Terakhir
                    </p>

                </div>

            </div>

            <div
                id="salesChart"
                class="h-96">
            </div>

        </div>

        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6 dark:bg-slate-800 dark:text-slate-200">

            <h3 class="font-semibold mb-5">

                Closing Terakhir

            </h3>

            <div class="space-y-4">

                <div class="rounded-xl bg-slate-50 p-4">

                    <p class="text-sm text-slate-500">

                        Bulan

                    </p>

                    <h4 class="font-bold">

                        {{ now()->translatedFormat('F Y') }}

                    </h4>

                </div>

                <div class="rounded-xl bg-slate-50 p-4">

                    <p class="text-sm text-slate-500">

                        Status

                    </p>

                    <span
                        class="rounded-full bg-green-100 px-3 py-1 text-green-600 text-xs font-semibold">

                        SELESAI

                    </span>

                </div>

                <div class="rounded-xl bg-slate-50 p-4">

                    <p class="text-sm text-slate-500">

                        Laba

                    </p>

                    <h3 class="text-xl font-bold text-green-600">

                        Rp {{ number_format(($totalIncome ?? 0)-($totalExpense ?? 0),0,',','.') }}

                    </h3>

                </div>

            </div>

        </div>

    </div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {

    var options = {

        chart: {

            type: 'area',

            toolbar: {

                show:false

            }

        },

        series: [{

            name: 'Omset',

            data: [5,9,7,12,14,18,20,19,25,23,28,35]

        }],

        stroke:{

            curve:'smooth'

        },

        dataLabels:{

            enabled:false

        },

        xaxis:{

            categories:['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des']

        }

    };

    new ApexCharts(document.querySelector("#salesChart"), options).render();

});

</script>

@endsection