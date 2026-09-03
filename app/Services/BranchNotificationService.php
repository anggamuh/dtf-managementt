<?php
namespace App\Services;
use App\Models\Order;use App\Models\User;use App\Notifications\PaymentNotification;use Illuminate\Support\Facades\Notification;
class BranchNotificationService {public function send(Order $order,string $title,string $message):void{$users=User::role(['Admin EPUL','Admin RAPLY','Finance','Produksi'])->where('branch_id',$order->branch_id)->get()->merge(User::role('Super Admin')->get())->unique('id');Notification::send($users,new PaymentNotification($title,$message,route('orders.customer-detail',$order),$order->id));}}
