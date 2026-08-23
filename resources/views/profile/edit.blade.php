@extends('layouts.app')

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-800 dark:text-slate-100">Profile</h1>

    <div class="max-w-xl rounded-2xl border border-slate-200 bg-white shadow-sm p-6 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Nama</span>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Email</span>
                    <input type="email"
                           name="email"
                           value="{{ old('email', $user->email) }}"
                           class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                </label>

                <div class="border-t border-slate-100 pt-5">
                    <p class="text-sm font-medium text-slate-700 mb-4 dark:text-slate-200">Ubah Password</p>
                    <div class="space-y-4">
                        <label class="block">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Password baru</span>
                            <input type="password"
                                name="password"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Konfirmasi password</span>
                            <input type="password"
                                name="password_confirmation"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-white text-sm font-medium hover:bg-blue-700 transition-all duration-200 shadow-sm hover:shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Simpan
                </button>
            </div>
        </form>
    </div>
@endsection