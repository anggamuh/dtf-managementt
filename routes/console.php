<?php

use App\Models\Order;
use App\Notifications\PaymentNotification;
use App\Services\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('customer-orders:expire-payments', function () {
    $expired = 0;

    Order::query()
        ->where('order_type', 'customer_custom')
        ->whereIn('payment_status', ['unpaid', 'pending'])
        ->whereNotNull('payment_reference')
        ->whereNotNull('payment_expired_at')
        ->where('payment_expired_at', '<=', now())
        ->select('id')
        ->chunkById(100, function ($orders) use (&$expired) {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order, &$expired) {
                    $locked = Order::with('user')->lockForUpdate()->find($order->id);
                    if (! $locked || ! in_array($locked->payment_status, ['unpaid', 'pending'], true) || $locked->payment_expired_at?->isFuture()) {
                        return;
                    }

                    $locked->update(['payment_status' => 'expired']);
                    app(PaymentService::class)->recordTransaction($locked, 'expired');
                    $locked->user?->notify(new PaymentNotification(
                        'Pembayaran kedaluwarsa',
                        'Waktu pembayaran pesanan '.$locked->order_number.' telah berakhir.',
                        route('customer.orders.show', $locked),
                        $locked->id,
                    ));
                    $expired++;
                });
            }
        });

    $this->info($expired.' pembayaran ditandai kedaluwarsa.');
})->purpose('Menandai pembayaran customer yang melewati batas waktu sebagai kedaluwarsa');

Schedule::command('customer-orders:expire-payments')->everyFiveMinutes()->withoutOverlapping();
