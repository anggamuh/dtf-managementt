<?php

namespace App\Livewire\Notifications;

use App\Services\NotificationUrlService;
use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DatabaseNotifications extends Component
{
    public bool $open = false;
    #[Locked]
    public string $guard = 'web';

    public function markAllAsRead(): void
    {
        $this->notifiable()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function visit(string $notificationId)
    {
        $notification = $this->notifiable()?->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        $url = app(NotificationUrlService::class)->safeUrl(
            $notification->data['url'] ?? null,
            $this->fallbackUrl(),
        );
        return $this->redirect($url, navigate: false);
    }

    public function render()
    {
        $notifiable = $this->notifiable();

        return view('livewire.notifications.database-notifications', [
            'notifications' => $notifiable?->notifications()->latest()->take(8)->get() ?? collect(),
            'unreadCount' => $notifiable?->unreadNotifications()->count() ?? 0,
        ]);
    }

    private function notifiable(): ?Authenticatable
    {
        return auth($this->guard)->user();
    }

    private function fallbackUrl(): string
    {
        return auth()->user()?->hasRole('Customer') ? route('customer.dashboard') : route('dashboard');
    }
}
