<?php

namespace App\Http\Controllers;

use App\Services\NotificationUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function visit(Request $request, string $notificationId, NotificationUrlService $urls): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        $fallback = $request->user()->hasRole('Customer')
            ? route('customer.dashboard')
            : route('dashboard');

        return redirect()->to($urls->safeUrl($notification->data['url'] ?? null, $fallback));
    }
}
