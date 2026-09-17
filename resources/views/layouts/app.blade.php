<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    {{-- Apply theme before page render to prevent flash --}}
    <script>
        (() => {
            try {
                const theme = localStorage.getItem('theme');

                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (theme === 'light') {
                    document.documentElement.classList.remove('dark');
                }
            } catch (error) {
                console.warn('Theme preference could not be loaded.');
            }
        })();
    </script>

    <title>@yield('title', 'DTF Management')</title>

    {{-- Favicon --}}
    <link
        rel="icon"
        type="image/png"
        href="{{ asset('images/awe-logo.png') }}"
    >

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    {{-- App Assets --}}
    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    @stack('styles')
</head>

<body
    class="min-h-screen bg-[#F8FAFC] text-[#0F172A] antialiased
           dark:bg-[#080F19] dark:text-[#F8FAFC]"
>

    <div
        class="app-shell min-h-screen flex
               bg-[#F8FAFC] dark:bg-[#080F19]"
    >

        {{-- Mobile Sidebar Overlay --}}
        <div
            id="sidebarOverlay"
            onclick="closeSidebar()"
            class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm lg:hidden"
        ></div>

        {{-- Sidebar --}}
        @include('components.sidebar')

        {{-- Main Content --}}
        <main class="app-main min-w-0 flex-1">

            {{-- Desktop Navbar --}}
            <div class="hidden lg:block">
                @include('components.navbar')
            </div>

            {{-- Mobile Navbar --}}
            <header
                class="sticky top-0 z-40 border-b border-[#E2E8F0]
                       bg-white/90 px-4 py-3 shadow-sm backdrop-blur-xl
                       lg:hidden
                       dark:border-[#253247]
                       dark:bg-[#0B1220]/90
                       dark:text-slate-100"
            >
                <div class="flex items-center justify-between">

                    {{-- Menu Button --}}
                    <button
                        type="button"
                        onclick="openSidebar()"
                        aria-label="Buka menu"
                        class="inline-flex h-10 w-10 items-center justify-center
                               rounded-xl border border-[#E2E8F0]
                               bg-white text-slate-700 shadow-sm
                               transition hover:bg-slate-50
                               dark:border-[#253247]
                               dark:bg-[#111827]
                               dark:text-slate-200
                               dark:hover:bg-[#172033]"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    {{-- Mobile Brand --}}
                    <div class="flex items-center gap-2">

                        <img
                            src="{{ asset('images/awe-logo.png') }}"
                            alt="Awe Print"
                            class="h-8 w-8 object-contain"
                        >

                        <div class="leading-tight">
                            <div
                                class="text-sm font-semibold
                                       text-slate-900 dark:text-slate-100"
                            >
                                Awe Print
                            </div>

                            <div
                                class="text-[9px] font-medium uppercase
                                       tracking-widest text-slate-500
                                       dark:text-slate-400"
                            >
                                DTF Management
                            </div>
                        </div>

                    </div>

                    {{-- Spacer --}}
                    <div class="h-10 w-10"></div>

                </div>
            </header>

            {{-- Page Container --}}
            <div class="app-container">

                {{-- Session / Validation Alerts --}}
                @if(
                    session('message') ||
                    session('error') ||
                    $errors->any()
                )
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {

                            @if(session('message'))
                                window.showSuccessAlert?.(
                                    'Berhasil',
                                    @json(session('message'))
                                );
                            @endif

                            @if(session('error'))
                                window.showErrorAlert?.(
                                    'Terjadi Kesalahan',
                                    @json(session('error'))
                                );
                            @endif

                            @if($errors->any())
                                window.showErrorAlert?.(
                                    'Error',
                                    @json($errors->first())
                                );
                            @endif

                        });
                    </script>
                @endif

                @yield('content')

            </div>

        </main>

    </div>

    {{-- Page-specific scripts --}}
    @stack('scripts')

    <script>
        (() => {

            /*
            |--------------------------------------------------------------------------
            | Global Branch
            |--------------------------------------------------------------------------
            */

            window.globalSelectedBranchId = Number(
                @json($globalSelectedBranchId ?? 0)
            );


            /*
            |--------------------------------------------------------------------------
            | Branch Required Alert
            |--------------------------------------------------------------------------
            */

            window.showBranchRequiredAlert = function () {

                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pilih cabang terlebih dahulu',
                        text: 'Aksi ini hanya tersedia ketika Anda memilih cabang tertentu, bukan "Semua Cabang".',
                        confirmButtonColor: '#2563eb',
                    });

                    return;
                }

                alert('Pilih cabang terlebih dahulu');
            };


            /*
            |--------------------------------------------------------------------------
            | Branch Required UI
            |--------------------------------------------------------------------------
            */

            function updateBranchRequiredUI() {

                const isAllBranch =
                    Number(window.globalSelectedBranchId) === 0;

                document
                    .querySelectorAll('.branch-required')
                    .forEach((element) => {

                        /*
                        |----------------------------------------------------------
                        | Form
                        |----------------------------------------------------------
                        */

                        if (element.tagName === 'FORM') {

                            const buttons = element.querySelectorAll(
                                'button[type="submit"], input[type="submit"]'
                            );

                            buttons.forEach((button) => {

                                if (
                                    typeof button.dataset.originalDisabled ===
                                    'undefined'
                                ) {
                                    button.dataset.originalDisabled =
                                        button.disabled ? '1' : '0';
                                }

                                if (isAllBranch) {

                                    button.disabled = true;

                                    button.classList.add(
                                        'opacity-50',
                                        'cursor-not-allowed'
                                    );

                                    button.setAttribute(
                                        'title',
                                        button.dataset.branchTitle ||
                                        'Pilih cabang terlebih dahulu'
                                    );

                                } else {

                                    button.disabled =
                                        button.dataset.originalDisabled === '1';

                                    button.classList.remove(
                                        'opacity-50',
                                        'cursor-not-allowed'
                                    );

                                    button.removeAttribute('title');
                                }

                            });

                            return;
                        }


                        /*
                        |----------------------------------------------------------
                        | Button / Element
                        |----------------------------------------------------------
                        */

                        if (
                            typeof element.dataset.originalDisabled ===
                            'undefined'
                        ) {
                            element.dataset.originalDisabled =
                                element.disabled ? '1' : '0';
                        }

                        if (isAllBranch) {

                            element.disabled = true;

                            element.classList.add(
                                'opacity-50',
                                'cursor-not-allowed'
                            );

                        } else {

                            if ('disabled' in element) {
                                element.disabled =
                                    element.dataset.originalDisabled === '1';
                            }

                            element.classList.remove(
                                'opacity-50',
                                'cursor-not-allowed'
                            );

                        }

                    });
            }


            /*
            |--------------------------------------------------------------------------
            | Theme Icons
            |--------------------------------------------------------------------------
            */

            function updateThemeIcons() {

                const isDark =
                    document.documentElement.classList.contains('dark');

                const lightIcon =
                    document.getElementById('theme-icon-light');

                const darkIcon =
                    document.getElementById('theme-icon-dark');

                lightIcon?.classList.toggle('hidden', isDark);
                darkIcon?.classList.toggle('hidden', !isDark);
            }


            /*
            |--------------------------------------------------------------------------
            | Toggle Theme
            |--------------------------------------------------------------------------
            */

            window.toggleTheme = function () {

                const root = document.documentElement;

                const isDark = root.classList.toggle('dark');

                try {
                    localStorage.setItem(
                        'theme',
                        isDark ? 'dark' : 'light'
                    );
                } catch (error) {
                    console.warn('Theme preference could not be saved.');
                }

                updateThemeIcons();

                window.dispatchEvent(
                    new CustomEvent('app:theme-changed', {
                        detail: {
                            theme: isDark ? 'dark' : 'light',
                        },
                    })
                );
            };


            /*
            |--------------------------------------------------------------------------
            | DOM Ready
            |--------------------------------------------------------------------------
            */

            document.addEventListener('DOMContentLoaded', () => {
                updateBranchRequiredUI();
                updateThemeIcons();
            });


            /*
            |--------------------------------------------------------------------------
            | Cross-tab Storage Sync
            |--------------------------------------------------------------------------
            */

            window.addEventListener('storage', (event) => {

                if (event.key === 'theme') {

                    if (event.newValue === 'dark') {
                        document.documentElement.classList.add('dark');
                    }

                    if (event.newValue === 'light') {
                        document.documentElement.classList.remove('dark');
                    }

                    updateThemeIcons();
                }

                if (event.key === 'selected_branch_id') {

                    window.globalSelectedBranchId =
                        Number(event.newValue || 0);

                    updateBranchRequiredUI();
                }

            });


            /*
            |--------------------------------------------------------------------------
            | System Theme Change
            |--------------------------------------------------------------------------
            */

            window
                .matchMedia('(prefers-color-scheme: dark)')
                .addEventListener('change', updateThemeIcons);


            /*
            |--------------------------------------------------------------------------
            | Custom Application Events
            |--------------------------------------------------------------------------
            */

            window.addEventListener(
                'app:branch-changed',
                (event) => {

                    window.globalSelectedBranchId =
                        Number(event.detail?.branchId || 0);

                    updateBranchRequiredUI();
                }
            );

        })();
    </script>

</body>
</html>