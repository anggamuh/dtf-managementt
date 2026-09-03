<div class="relative" wire:poll.60s>
    <button type="button" wire:click="$toggle('open')" aria-label="Notifikasi"
        class="app-icon-btn relative h-11 w-11" title="Notifikasi terbaru">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h11zm0 0v1a3 3 0 11-6 0v-1h6z" />
        </svg>
        @if($unreadCount)
            <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-red-500 px-1 text-center text-xs font-bold leading-5 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    @if($open)
        <div class="fixed left-4 right-4 top-[4.5rem] z-50 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-96">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <span class="font-semibold">Notifikasi</span>
                @if($unreadCount)
                    <button wire:click="markAllAsRead" class="text-xs font-medium text-blue-600 hover:underline">Tandai semua dibaca</button>
                @endif
            </div>
            <div class="max-h-[min(28rem,calc(100vh-10rem))] overflow-y-auto">
                @forelse($notifications as $notification)
                    <button wire:key="notification-{{ $notification->id }}" wire:click="visit('{{ $notification->id }}')"
                        class="block min-h-16 w-full border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800 {{ $notification->read_at ? '' : 'bg-blue-50 dark:bg-blue-950/30' }}">
                        <div class="flex items-start gap-2"><span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-300' : 'bg-blue-600' }}"></span><div class="min-w-0"><div class="truncate text-sm font-semibold">{{ $notification->data['title'] ?? 'Notifikasi' }}</div>
                        <div class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">{{ $notification->data['message'] ?? '' }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</div></div></div>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">Belum ada notifikasi.</div>
                @endforelse
            </div>
            <a href="{{ auth()->user()->hasRole('Customer') ? route('customer.notifications') : route('notifications.index') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-blue-600 dark:border-slate-800">Lihat semua notifikasi</a>
        </div>
    @endif
</div>
