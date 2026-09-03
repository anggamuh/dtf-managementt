<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Customer') - DTF Management</title>
    <script>try{if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100" x-data="{ mobileMenu: false }" @keydown.escape.window="mobileMenu = false">
    <div class="app-shell min-h-screen lg:flex">
        <div x-cloak x-show="mobileMenu" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden" @click="mobileMenu = false"></div>
        <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform dark:border-slate-800 dark:bg-slate-900 lg:static lg:w-64 lg:translate-x-0" :class="{ 'translate-x-0': mobileMenu }">
            <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5 dark:border-slate-800">
                <a href="{{ route('customer.dashboard') }}" class="text-lg font-bold text-blue-700 dark:text-blue-400">DTF Customer</a>
                <button type="button" class="app-icon-btn lg:hidden" aria-label="Tutup menu" @click="mobileMenu = false"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <nav class="flex-1 space-y-1 p-4" aria-label="Navigasi customer">
                @foreach([
                    ['customer.dashboard', 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0h6'],
                    ['customer.orders.create', 'Buat Pesanan', 'M12 4v16m8-8H4'],
                    ['customer.orders.index', 'Pesanan Saya', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h8l4 4v12a2 2 0 01-2 2z'],
                    ['customer.notifications', 'Notifikasi', 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 01-6 0'],
                    ['customer.profile', 'Profil', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21a8 8 0 0116 0']
                ] as [$route, $label, $icon])
                    <a href="{{ route($route) }}" @click="mobileMenu = false" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs($route) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg><span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-slate-200 p-4 dark:border-slate-800">
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="app-btn app-btn-secondary min-h-11 w-full">Keluar</button></form>
            </div>
        </aside>
        <div class="app-main min-w-0 flex-1">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3"><button type="button" class="app-icon-btn lg:hidden" aria-label="Buka menu" @click="mobileMenu = true"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg></button><div class="lg:hidden text-sm font-semibold">@yield('title', 'Customer')</div></div>
                    <div class="flex items-center gap-2 sm:gap-3">
                        <button type="button" onclick="window.toggleTheme ? toggleTheme() : (document.documentElement.classList.toggle('dark'),localStorage.setItem('theme',document.documentElement.classList.contains('dark')?'dark':'light'))" class="app-icon-btn" aria-label="Ganti tema"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36-6.36l-.7.7M6.34 17.66l-.7.7m12.72 0l-.7-.7M6.34 6.34l-.7-.7M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg></button>
                        <livewire:notifications.database-notifications guard="web" />
                        <div class="hidden max-w-40 truncate text-right text-sm sm:block"><div class="font-semibold">{{ auth()->user()->name }}</div><div class="text-xs text-slate-500">Customer</div></div>
                    </div>
                </div>
            </header>
            <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        @if(session('message'))<div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('message') }}</div>@endif
        @if($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>@endif
        @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
