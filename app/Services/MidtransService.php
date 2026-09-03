<?php
namespace App\Services;
use App\Models\Order;use Illuminate\Support\Facades\Http;use RuntimeException;
class MidtransService {
 public function createSnap(Order $order):array {
  $key=(string)config('services.midtrans.server_key');if($key==='')throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
  $base=config('services.midtrans.is_production')?'https://app.midtrans.com':'https://app.sandbox.midtrans.com';
  return Http::acceptJson()->withBasicAuth($key,'')->post($base.'/snap/v1/transactions',[
   'transaction_details'=>['order_id'=>$order->payment_reference,'gross_amount'=>(int)$order->total],
   'item_details'=>[['id'=>'CUSTOM-ORDER','price'=>(int)$order->price,'quantity'=>(int)$order->qty,'name'=>'Pesanan Custom DTF']],
   'customer_details'=>['first_name'=>$order->user->name,'email'=>$order->user->email,'phone'=>$order->user->whatsapp],
   'credit_card'=>['secure'=>(bool)config('services.midtrans.is_3ds')],
   'page_expiry'=>['duration'=>config('customer_order.payment_expiry_hours'),'unit'=>'hours'],
   'callbacks'=>['finish'=>route('customer.orders.show',$order)],
  ])->throw()->json();
 }
 public function validSignature(array $p):bool {
  $key=(string) config('services.midtrans.server_key');
  if ($key==='' || !isset($p['signature_key']) || !is_string($p['signature_key'])) return false;
  $expected=hash('sha512',($p['order_id']??'').($p['status_code']??'').($p['gross_amount']??'').$key);
  return hash_equals($expected,$p['signature_key']);
 }
}
