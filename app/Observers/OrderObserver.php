<?php

namespace App\Observers;

use App\Models\Order;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderStatusChangedNotification;

class OrderObserver
{
    public function created(Order $order): void
    {
        if ($order->isCustomerCustom()) {
            $order->statusHistories()->create(['old_status'=>null,'new_status'=>$order->status,'note'=>'Pesanan dibuat.','customer_visible'=>true,'changed_by'=>auth()->id()]);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        if ($order->isCustomerCustom()) {
            $order->statusHistories()->create(['old_status'=>(string)$order->getOriginal('status'),'new_status'=>$order->status,'customer_visible'=>true,'changed_by'=>auth()->id()]);
        }

        $customerNotifiableStatuses = [
            'revision_required',
            'processing',
            'printing',
            'quality_control',
            'ready_pickup',
            'shipped',
            'completed',
            'cancelled',
        ];

        if ($order->isCustomerCustom() && $order->user && in_array($order->status, $customerNotifiableStatuses, true)) {
            $order->user->notify(new OrderStatusChangedNotification($order, (string) $order->getOriginal('status')));
        }
    }
}
