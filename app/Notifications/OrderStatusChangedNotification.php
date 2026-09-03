<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public Order $order, public string $oldStatus) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'title' => 'Status pesanan diperbarui',
            'message' => "Status pesanan {$this->order->order_number} berubah dari {$this->label($this->oldStatus)} menjadi {$this->label($this->order->status)}.",
            'order_id' => $this->order->id,
            'old_status' => $this->oldStatus,
            'status' => $this->order->status,
            'url' => route('customer.orders.show',$this->order),
        ];
    }

    private function label(string $status): string
    {
        return match ($status) {
            'waiting', 'pending' => 'Menunggu',
            'waiting_payment' => 'Menunggu pembayaran',
            'confirmed' => 'Dikonfirmasi',
            'file_review' => 'Pemeriksaan file',
            'revision_required' => 'File perlu direvisi',
            'awaiting_design_approval' => 'Menunggu persetujuan desain',
            'production_queue' => 'Antrean produksi',
            'processing' => 'Diproses',
            'printing', 'printed' => 'Sedang dicetak',
            'quality_control' => 'Quality control',
            'ready_pickup' => 'Siap diambil',
            'shipped' => 'Dikirim',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
