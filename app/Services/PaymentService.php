<?php
namespace App\Services;
use App\Models\Order;use App\Notifications\PaymentNotification;use Illuminate\Support\Facades\DB;
class PaymentService {
 public function recordTransaction(Order $order,string $status,?string $transactionId=null,array $payload=[],?int $processedBy=null):void
 {
  $order->paymentTransactions()->updateOrCreate(['reference'=>$order->payment_reference],[
   'provider'=>$order->payment_gateway?:($order->payment_method==='bank_transfer'?'manual_transfer':null),
   'external_transaction_id'=>$transactionId?:$order->gateway_transaction_id,
   'method'=>$order->payment_method,'status'=>$status,'amount'=>$order->total,'payload'=>$payload,
   'processed_at'=>now(),'processed_by'=>$processedBy,
  ]);
 }

 public function markPaid(Order $order,?string $transactionId,array $payload,string $customerMessage):bool{return DB::transaction(function()use($order,$transactionId,$payload,$customerMessage){
  $locked=Order::lockForUpdate()->findOrFail($order->id);
  if(in_array($locked->payment_status,['paid','refunded'],true))return false;
  abort_if($locked->status==='cancelled'||$locked->payment_status==='refunded',422,'Pesanan ini tidak dapat dibayar.');
  abort_unless($locked->isCustomerCustom()&&$locked->user_id,422,'Pembayaran hanya berlaku untuk pesanan customer.');
  $locked->update(['payment_status'=>'paid','status'=>'file_review','paid_at'=>now(),'gateway_transaction_id'=>$transactionId?:$locked->gateway_transaction_id,'gateway_payload'=>$payload]);
  $this->recordTransaction($locked,'paid',$transactionId,$payload,auth()->id());
  \App\Models\CustomerInvoice::firstOrCreate(['order_id'=>$locked->id],[
   'user_id'=>$locked->user_id,'branch_id'=>$locked->branch_id,
   'invoice_number'=>'CINV-'.now()->format('YmdHis').'-'.random_int(100,999),
   'product_total'=>$locked->total,'shipping_cost'=>0,'total'=>$locked->total,'paid_at'=>now(),
  ]);
  $locked->user->notify(new PaymentNotification('Pembayaran Berhasil',$customerMessage,route('customer.orders.show',$locked),$locked->id));
  app(BranchNotificationService::class)->send($locked,'Pesanan baru siap diproses',"Pesanan {$locked->order_number} telah dibayar dan siap diproses.");
  return true;
 });}
}
