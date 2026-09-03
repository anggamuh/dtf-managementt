<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;use App\Http\Requests\StorePaymentConfirmationRequest;use App\Models\Order;use App\Models\PaymentAccount;use App\Models\PaymentConfirmation;use App\Services\BranchNotificationService;use App\Services\MidtransService;use App\Services\PaymentService;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Storage;
class PaymentController extends Controller {
 public function snap(Order $order,MidtransService $midtrans){
  $this->authorize('view',$order);
  $token=DB::transaction(function()use($order,$midtrans){
   $locked=Order::with('user')->lockForUpdate()->findOrFail($order->id);
   abort_unless($locked->payment_method==='payment_gateway'&&$locked->payment_gateway==='midtrans',422,'Metode pembayaran tidak valid.');
   abort_if($locked->payment_status==='paid',422,'Pesanan sudah dibayar.');
   abort_if($locked->status==='cancelled'||$locked->payment_status==='refunded',422,'Pesanan ini tidak dapat dibayar.');
   abort_if($locked->payment_expired_at?->isPast(),422,'Pembayaran kedaluwarsa.');
   if(!$locked->snap_token){
    $result=$midtrans->createSnap($locked);
    $token=$result['token']??null;
    abort_unless(is_string($token)&&$token!=='',502,'Gateway tidak mengembalikan token pembayaran.');
    $locked->update(['snap_token'=>$token]);
   }
   return $locked->snap_token;
  });
  return response()->json(['token'=>$token,'client_key'=>config('services.midtrans.client_key'),'production'=>(bool)config('services.midtrans.is_production')]);
 }

 public function confirm(StorePaymentConfirmationRequest $request,Order $order,PaymentService $payments){
  $this->authorize('view',$order);
  abort_unless($order->payment_method==='bank_transfer',422,'Metode pembayaran tidak valid.');
  $account=PaymentAccount::whereKey($request->payment_account_id)->where('branch_id',$order->branch_id)->where('is_active',true)->firstOrFail();
  abort_unless($this->moneyInCents($request->input('amount'))===$this->moneyInCents($order->total),422,'Nominal transfer harus sama dengan total pesanan.');
  $path=$request->file('proof')->store('payment-proofs/'.now()->format('Y/m'),'local');
  try{
   DB::transaction(function()use($request,$order,$account,$path,$payments){
    $locked=Order::lockForUpdate()->findOrFail($order->id);
    abort_if($locked->payment_status==='paid',422,'Pesanan sudah dibayar.');
    abort_if($locked->status==='cancelled'||$locked->payment_status==='refunded',422,'Pesanan ini tidak dapat dibayar.');
    abort_if($locked->payment_status==='pending_verification'||$locked->paymentConfirmations()->where('status','pending')->exists(),422,'Bukti pembayaran sedang diverifikasi.');
    abort_if($locked->payment_expired_at?->isPast(),422,'Pembayaran kedaluwarsa.');
    abort_unless($this->moneyInCents($request->input('amount'))===$this->moneyInCents($locked->total),422,'Nominal transfer harus sama dengan total pesanan.');
    PaymentConfirmation::create($request->safe()->except('proof')+['order_id'=>$locked->id,'payment_account_id'=>$account->id,'proof_path'=>$path,'proof_original_name'=>$request->file('proof')->getClientOriginalName(),'status'=>'pending']);
    $locked->update(['payment_status'=>'pending_verification','payment_rejection_reason'=>null]);
    $payments->recordTransaction($locked,'pending_verification',null,[],auth()->id());
    app(BranchNotificationService::class)->send($locked,'Bukti pembayaran baru','Bukti pembayaran '.$locked->order_number.' menunggu verifikasi.');
   });
  }catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
  return back()->with('message','Bukti pembayaran dikirim dan menunggu verifikasi.');
 }

 private function moneyInCents(mixed $amount):int
 {
  abort_unless(is_numeric($amount),422,'Nominal pembayaran tidak valid.');
  return (int) round((float)$amount*100);
 }
}
