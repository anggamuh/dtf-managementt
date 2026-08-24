@php
    $nav = [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'o-home'],
        'orders.index' => ['label' => 'Pesanan', 'icon' => 'o-shopping-cart'],
        'orders.daily-summary' => ['label' => 'Sum Harian', 'icon' => 'o-chart-bar'],
        'orders.weekly-closing' => ['label' => 'Closing Mingguan', 'icon' => 'o-clipboard-document-check'],
        'invoices.index' => ['label' => 'Invoice', 'icon' => 'o-document-text'],
        'expenses.index' => ['label' => 'Pengeluaran', 'icon' => 'o-arrow-trending-down'],
        'materials.index' => ['label' => 'Material', 'icon' => 'o-cube'],
        'closing.index' => ['label' => 'Closing', 'icon' => 'o-calculator'],
    ];
    $currentRoute = request()->route()->getName() ?? '';
    $user = auth()->user();
    $userRole = $user?->roles->first()?->name ?? '';
@endphp

{{-- Sidebar --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-50 w-[220px] transform -translate-x-full transition-all duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 lg:w-[220px] lg:flex lg:flex-col">
    <div class="flex h-full flex-col lg:h-full">

        {{-- Header --}}
        <div class="sidebar-header flex items-center justify-between px-4 py-4 h-16 flex-shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 overflow-hidden min-w-0">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 text-white font-bold text-sm shadow-lg shadow-blue-500/20">
                    D
                </div>
                <div class="leading-tight whitespace-nowrap min-w-0 sidebar-logo-wrap">
                    <div class="sidebar-logo-text text-sm font-bold tracking-tight truncate">DTF Management</div>
                    <div class="sidebar-logo-sub text-[10px] font-medium uppercase tracking-widest truncate">Printing Ops</div>
                </div>
            </a>
            <button onclick="closeSidebar()" aria-label="Tutup menu" class="sidebar-close-btn rounded-lg p-1.5 transition lg:hidden flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 space-y-1 px-3 py-4 overflow-y-auto overflow-x-hidden">
            <div class="sidebar-section-label px-3 pb-2 text-[10px] font-semibold uppercase tracking-widest whitespace-nowrap">Menu Utama</div>
            @foreach($nav as $route => $item)
                @php
                    $isActive = match($route) {
                        'dashboard' => $currentRoute === 'dashboard',
                        'orders.index' => in_array($currentRoute, ['orders.index', 'orders.create', 'orders.edit', 'orders.store', 'orders.update'], true),
                        'orders.daily-summary' => $currentRoute === 'orders.daily-summary',
                        'orders.weekly-closing' => $currentRoute === 'orders.weekly-closing',
                        'invoices.index' => str_starts_with($currentRoute, 'invoices.'),
                        'expenses.index' => str_starts_with($currentRoute, 'expenses.'),
                        'materials.index' => str_starts_with($currentRoute, 'materials.'),
                        'closing.index' => str_starts_with($currentRoute, 'closing.'),
                        default => false,
                    };
                    $iconPath = match($item['icon']) {
                        'o-home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                        'o-shopping-cart' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z',
                        'o-document-text' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'o-arrow-trending-down' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6',
                        'o-cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'o-calculator' => 'M9 7h6m-6 4h6m-6 4h6m-3-12v16m-7-4h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
                        'o-chart-bar' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                        'o-clipboard-document-check' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5a3 3 0 016 0m-6 9l2 2 4-4',
                        'o-user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                    };
                @endphp
                <a href="{{ route($route) }}"
                   onclick="if(window.innerWidth < 1024) closeSidebar();"
                   class="sidebar-tooltip-group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {{ $isActive ? 'sidebar-active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
                    </svg>
                    <span class="whitespace-nowrap sidebar-label">{{ $item['label'] }}</span>
                    <span class="sidebar-tooltip">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- User Profile --}}
        <div class="sidebar-footer p-3 flex-shrink-0">
            <div class="sidebar-profile flex items-center gap-3 rounded-xl p-2.5">
                <div class="h-9 w-9 flex-shrink-0 rounded-full bg-gradient-to-br from-blue-500 to-emerald-500 flex items-center justify-center text-white font-bold text-sm">
                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0 overflow-hidden sidebar-profile-wrap">
                    <div class="sidebar-profile-name text-sm font-semibold truncate">{{ $user->name ?? 'User' }}</div>
                    <div class="sidebar-profile-role text-xs truncate">{{ $userRole ?: ($user->email ?? '') }}</div>
                </div>
            </div>
        </div>
    </div>
</aside>

<script>
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.remove('hidden');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebarOverlay').classList.add('hidden');
    }
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });

    // Sidebar collapse toggle (desktop only)
    (function() {
        const sidebar = document.getElementById('sidebar');
        const COLLAPSE_KEY = 'sidebar_collapsed';

        function applyCollapse(collapsed) {
            if (!sidebar) return;
            const isDesktop = window.innerWidth >= 1024;
            if (!isDesktop) return;

            sidebar.classList.toggle('sidebar-collapsed', collapsed);

            if (collapsed) {
                sidebar.classList.add('lg:w-[68px]');
                sidebar.classList.remove('lg:w-[220px]');
            } else {
                sidebar.classList.remove('lg:w-[68px]');
                sidebar.classList.add('lg:w-[220px]');
            }
        }

        window.toggleSidebarCollapse = function() {
            const isCollapsed = localStorage.getItem(COLLAPSE_KEY) === '1';
            const next = !isCollapsed;
            localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0');
            applyCollapse(next);
        };

        document.addEventListener('DOMContentLoaded', function() {
            const isCollapsed = localStorage.getItem(COLLAPSE_KEY) === '1';
            applyCollapse(isCollapsed);
        });

        window.addEventListener('resize', function() {
            const isCollapsed = localStorage.getItem(COLLAPSE_KEY) === '1';
            applyCollapse(isCollapsed);
        });
    })();
</script>
