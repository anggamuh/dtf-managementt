<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        (function(){
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') document.documentElement.classList.add('dark');
                if (theme === 'light') document.documentElement.classList.remove('dark');
            } catch (e) {}
        })();
    </script>
    <title>Masuk · DTF Management</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Login-specific: keep underline inputs transparent */
        .login-input {
            background-color: transparent !important;
            border: 0 !important;
            border-bottom: 2px solid var(--border-color) !important;
            box-shadow: none !important;
            outline: none !important;
            border-radius: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        .login-input:focus {
            border-color: var(--primary) !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased dark:bg-[#080F19] dark:text-[#F8FAFC]">
    <main class="mx-auto grid min-h-screen max-w-[1440px] lg:grid-cols-[1.1fr_.9fr]">
        {{-- Left Branding --}}
        <section class="hidden bg-[#050B14] p-14 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-xl font-black text-white shadow-lg">
                    D
                </div>
                <div>
                    <b class="block text-lg text-white">DTF MANAGEMENT</b>
                    <span class="text-xs tracking-widest text-slate-500">PRINTING OPERATIONS</span>
                </div>
            </div>

            <div class="max-w-xl">
                <div class="mb-7 h-1 w-16 bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full"></div>
                <h1 class="text-5xl font-bold leading-[1.1] text-white">Satu tempat untuk mengendalikan operasional printing.</h1>
                <p class="mt-6 text-lg leading-8 text-slate-400">Pesanan, invoice, bahan baku, pengeluaran, dan laporan cabang dalam satu alur kerja yang rapi.</p>
            </div>

            <div class="border-t border-white/10 pt-5 text-sm text-slate-500">
                Internal system · Authorized users only
            </div>
        </section>

        {{-- Right Login Form --}}
        <section class="flex items-center justify-center bg-white dark:bg-[#111827] px-6 py-12 sm:px-12">
            <div class="w-full max-w-sm">
                <div class="mb-10 lg:hidden">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-emerald-600 text-lg font-black text-white shadow-lg">
                        D
                    </div>
                    <b class="text-lg text-[#0F172A] dark:text-[#F8FAFC]">DTF MANAGEMENT</b>
                </div>

                <p class="text-xs font-bold tracking-[.18em] text-[#2563EB] uppercase dark:text-[#3B82F6]">Authentication</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Masuk ke akun Anda</h2>
                <p class="mt-3 text-sm leading-6 text-[#64748B] dark:text-[#94A3B8]">Gunakan email dan password yang terdaftar.</p>

                <form method="POST" action="{{ route('login') }}" class="mt-9 space-y-5">
                    @csrf
                    <label class="block text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">
                        Email
                        <input type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="email"
                               placeholder="nama@perusahaan.com"
                               class="login-input mt-2 w-full px-0 py-3 text-sm outline-none placeholder:text-[#94A3B8] dark:placeholder:text-[#64748B] transition-colors {{ $errors->has('email') ? '!border-rose-500' : '' }}">
                    </label>
                    @error('email')
                        <p class="-mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror

                    <label class="block text-sm font-semibold text-[#0F172A] dark:text-[#F8FAFC]">
                        Password
                        <input type="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               placeholder="Masukkan password"
                               class="login-input mt-2 w-full px-0 py-3 text-sm outline-none placeholder:text-[#94A3B8] dark:placeholder:text-[#64748B] transition-colors {{ $errors->has('password') ? '!border-rose-500' : '' }}">
                    </label>
                    @error('password')
                        <p class="-mt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror

                    <label class="flex items-center gap-2 text-sm text-[#64748B] dark:text-[#94A3B8] cursor-pointer">
                        <input type="checkbox"
                               name="remember"
                               value="1"
                               @checked(old('remember'))
                               class="rounded border-[#CBD5E1] text-[#2563EB] focus:ring-[#2563EB] dark:border-[#334155] dark:bg-[#111827]">
                        Ingat saya
                    </label>

                    <button type="submit"
                            class="w-full rounded-xl bg-[#2563EB] py-3.5 text-sm font-bold text-white transition-all duration-200 hover:bg-[#1D4ED8] shadow-sm dark:bg-[#3B82F6] dark:hover:bg-[#60A5FA]">
                        MASUK
                    </button>
                </form>

                <p class="mt-10 text-center text-xs text-[#94A3B8] dark:text-[#64748B]">DTF Management System</p>
            </div>
        </section>
    </main>
</body>
</html>