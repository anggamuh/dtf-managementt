<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <script>
        (function(){
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') document.documentElement.classList.add('dark');
                if (theme === 'light') document.documentElement.classList.remove('dark');
            } catch (e) {}
        })();
    </script>
    <title>@yield('title', 'DTF Management')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased dark:bg-[#080F19] dark:text-[#F8FAFC]">
    <div class="min-h-screen flex bg-[#F8FAFC] dark:bg-[#080F19]">
        <div id="sidebarOverlay" onclick="closeSidebar()" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden hidden"></div>

        @include('components.sidebar')

        <main class="flex-1 min-w-0">
            <div class="hidden lg:block">
                @include('components.navbar')
            </div>

            <header class="lg:hidden sticky top-0 z-40 border-b border-[#E2E8F0] bg-white/90 px-4 py-3 shadow-sm backdrop-blur-xl dark:border-[#253247] dark:bg-[#0B1220]/90 dark:text-slate-100">
                <div class="flex items-center justify-between gap-4">
                    <button onclick="openSidebar()" aria-label="Buka menu" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#E2E8F0] bg-white text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-[#253247] dark:bg-[#111827] dark:text-slate-200 dark:hover:bg-[#172033]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="text-base font-semibold text-slate-900 dark:text-slate-100">DTF Management</div>
                    <div class="h-10 w-10"></div>
                </div>
            </header>

            <div class="app-container">
                @if(session('message'))
                    <script>window.showSuccessAlert('{{ session('message') }}');</script>
                @endif
                @if(session('error'))
                    <script>window.showErrorAlert('{{ session('error') }}');</script>
                @endif
                @if($errors->any())
                    <script>window.showErrorAlert('Error', '{{ $errors->first() }}');</script>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
    <script>
        // expose selected branch id for frontend UX checks
        window.globalSelectedBranchId = {{ $globalSelectedBranchId ?? 0 }};
        function showBranchRequiredAlert() {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih cabang terlebih dahulu',
                    text: 'Aksi ini hanya tersedia ketika Anda memilih cabang tertentu, bukan "Semua Cabang".',
                    confirmButtonColor: '#2563eb'
                });
                return;
            }
            alert('Pilih cabang terlebih dahulu');
        }
    </script>
    <script>
        // Disable UI actions that require a specific branch when 'Semua Cabang' is selected
        (function () {
            function updateBranchRequiredUI() {
                const isAll = Number(window.globalSelectedBranchId) === 0;
                document.querySelectorAll('.branch-required').forEach(el => {
                    if (el.tagName === 'FORM') {
                        el.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
                            btn.__origDisabled = btn.disabled;
                            if (isAll) {
                                btn.disabled = true;
                                btn.classList.add('opacity-50','cursor-not-allowed');
                                btn.setAttribute('title', btn.getAttribute('data-branch-title') || 'Pilih cabang terlebih dahulu');
                            } else {
                                btn.disabled = btn.__origDisabled || false;
                                btn.classList.remove('opacity-50','cursor-not-allowed');
                            }
                        });
                    } else {
                        if (isAll) {
                            el.__origDisabled = el.disabled || false;
                            el.disabled = true;
                            el.classList.add('opacity-50','cursor-not-allowed');
                            el.addEventListener('click', showBranchRequiredAlert, { once: true });
                        } else {
                            if (typeof el.__origDisabled !== 'undefined') el.disabled = el.__origDisabled;
                            el.classList.remove('opacity-50','cursor-not-allowed');
                        }
                    }
                });
            }
            document.addEventListener('DOMContentLoaded', updateBranchRequiredUI);
            window.addEventListener('storage', e => { if (e.key === 'selected_branch_id' || e.key === 'theme' ) updateBranchRequiredUI(); });
        })();
    </script>
    <script>
        (function() {
            const lightIcon = document.getElementById('theme-icon-light');
            const darkIcon = document.getElementById('theme-icon-dark');
            const updateIcons = () => {
                const isDark = document.documentElement.classList.contains('dark');
                if (lightIcon) lightIcon.classList.toggle('hidden', isDark);
                if (darkIcon) darkIcon.classList.toggle('hidden', !isDark);
            };
            updateIcons();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', updateIcons);
            window.addEventListener('storage', e => { if (e.key === 'theme') updateIcons(); });
        })();
        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            try {
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            } catch (e) {}
            const ev = new Event('storage');
            window.dispatchEvent(ev);
        }
    </script>
</body>
</html>