<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_order',
            'title' => 'Pesanan Customer Baru',
            'message' => "{$this->order->user->name} membuat {$this->order->order_number} untuk {$this->order->branch->name}, ukuran {$this->order->size}, jumlah {$this->order->qty}.",
            'order_id' => $this->order->id,
            'branch_id' => $this->order->branch_id,
            'url' => route('orders.customer-detail', $this->order),
        ];
    }
}
