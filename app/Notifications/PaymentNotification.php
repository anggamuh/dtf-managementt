<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
class PaymentNotification extends Notification {public function __construct(public string $title,public string $message,public string $url,public ?int $orderId=null){}public function via(object $n):array{return ['database'];}public function toArray(object $n):array{return ['type'=>'payment','title'=>$this->title,'message'=>$this->message,'url'=>$this->url,'order_id'=>$this->orderId];}}
