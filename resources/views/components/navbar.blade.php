<header class="sticky top-0 z-40 h-16 border-b border-[#E2E8F0] bg-white/90 backdrop-blur-xl dark:border-[#253247] dark:bg-[#0B1220]/90">
    <div class="flex h-full items-center justify-between px-4 lg:px-6">
        {{-- Left: Sidebar toggle + Breadcrumb --}}
        <div class="flex items-center gap-3 text-sm">
            {{-- Sidebar collapse toggle (desktop) --}}
            <button onclick="toggleSidebarCollapse()" aria-label="Collapse sidebar"
                class="hidden lg:inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-300 dark:hover:bg-[#172033] dark:hover:text-slate-100"
                title="Collapse sidebar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <nav class="flex items-center gap-2 text-slate-500 dark:text-slate-400">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-1.5 hover:text-slate-800 dark:hover:text-slate-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="hidden sm:inline">Home</span>
                </a>
                <span class="text-slate-300 dark:text-slate-600">/</span>
                <span class="font-medium text-slate-800 dark:text-slate-100">@yield('title', 'Dashboard')</span>
            </nav>

            {{-- Branch Switcher --}}
            <div>
                @include('components.branch-switcher')
            </div>
        </div>

        {{-- Right: Actions --}}
        <div class="flex items-center gap-2">
            {{-- Search (Desktop) --}}
            <div class="hidden lg:block relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    placeholder="Cari transaksi, invoice, customer..."
                    class="w-64 rounded-xl border border-[#E2E8F0] bg-white pl-10 pr-4 py-2 text-sm
                           focus:ring-2 focus:ring-[#2563EB]/20 focus:border-[#2563EB] outline-none transition-all duration-200 placeholder:text-slate-400
                           dark:border-[#253247] dark:bg-[#111827] dark:text-slate-200 dark:placeholder:text-slate-500">
            </div>

            {{-- Theme Toggle --}}
            <button onclick="toggleTheme()" aria-label="Ganti tema"
                class="relative h-10 w-10 rounded-xl border border-[#E2E8F0] bg-white hover:bg-slate-50 transition-all duration-200 flex items-center justify-center text-slate-600 hover:text-slate-800 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-200 dark:hover:bg-[#172033]"
                title="Toggle theme">
                <svg id="theme-icon-light" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <svg id="theme-icon-dark" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9 0 0012 21a9.003 9 0 008.354-5.646z"/>
                </svg>
            </button>

            <livewire:notifications.database-notifications guard="web" />

            {{-- Profile Dropdown --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" type="button" aria-label="Menu profil"
                        class="flex items-center gap-3 rounded-xl hover:bg-slate-50 p-1.5 pr-3 transition-all duration-200 dark:hover:bg-slate-800">
                    <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-blue-600 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-blue-600/20">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="hidden md:block text-left">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ auth()->user()->name }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{ auth()->user()->email }}
                        </div>
                    </div>
                    <svg class="hidden md:block w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                {{-- Dropdown Menu --}}
                <div x-show="open"
                     @click.away="open = false"
                     @keydown.escape.window="open = false"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-1"
                     class="absolute right-0 mt-2 w-56 rounded-2xl border border-[#E2E8F0] bg-white shadow-xl overflow-hidden dark:border-[#253247] dark:bg-[#111827]">
                    <div class="p-1.5">
                        <a href="{{ route('profile.edit') }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors duration-150 dark:text-slate-200 dark:hover:bg-slate-800">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Profil Saya
                        </a>
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors duration-150 dark:text-slate-200 dark:hover:bg-slate-800">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            Ubah Password
                        </a>
                        <div class="border-t border-slate-100 my-1 dark:border-slate-700"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition-colors duration-150 dark:text-red-400 dark:hover:bg-red-500/10">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
