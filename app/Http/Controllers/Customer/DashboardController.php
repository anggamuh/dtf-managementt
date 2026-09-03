<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $customer = $request->user();
        $orders = $customer->orders()->where('order_type','customer_custom');

        return view('customer.dashboard', [
            'totalOrders' => (clone $orders)->count(),
            'pendingOrders' => (clone $orders)->whereIn('status',['waiting_payment','pending'])->count(),
            'processingOrders' => (clone $orders)->whereIn('status',['confirmed','processing'])->count(),
            'completedOrders' => (clone $orders)->where('status', 'completed')->count(),
            'recentOrders' => (clone $orders)->with('branch')->latest()->take(5)->get(),
            'latestNotifications' => $customer->notifications()->latest()->take(5)->get(),
        ]);
    }

    public function notifications(Request $request): View { return view('customer.notifications',['notifications'=>$request->user()->notifications()->latest()->paginate(20)]); }
    public function profile(): View { return view('customer.profile'); }
}
