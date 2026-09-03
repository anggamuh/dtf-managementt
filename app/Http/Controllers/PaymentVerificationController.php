<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Concerns\ResolvesBranch;use App\Models\Order;use App\Models\PaymentConfirmation;use App\Notifications\PaymentNotification;use App\Services\PaymentService;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Storage;
class PaymentVerificationController extends Controller {use ResolvesBranch;
 public function index(Request $r){$branch=$this->branchId($r);$orders=Order::with(['user','branch','paymentConfirmations'=>fn($q)=>$q->latest()])->where('order_type','customer_custom')->where('payment_method','bank_transfer')->where('payment_status','pending_verification')->when($branch!==0,fn($q)=>$q->where('branch_id',$branch))->latest()->paginate(20);return view('payments.verifications',compact('orders','branch'));}
 public function proof(PaymentConfirmation $confirmation){$this->authorize('view',$confirmation->order);abort_unless(Storage::disk('local')->exists($confirmation->proof_path),404);return Storage::disk('local')->download($confirmation->proof_path,$confirmation->proof_original_name);}
 public function approve(PaymentConfirmation $confirmation,PaymentService $payments){
  $this->authorize('update',$confirmation->order);
  DB::transaction(function()use($confirmation,$payments){
   $order=Order::lockForUpdate()->findOrFail($confirmation->order_id);
   $locked=PaymentConfirmation::lockForUpdate()->findOrFail($confirmation->id);
   abort_unless($locked->status==='pending',422,'Bukti sudah diverifikasi.');
   abort_if($order->payment_status==='paid',422,'Pesanan sudah dibayar.');
   abort_if($order->status==='cancelled'||$order->payment_status==='refunded',422,'Pesanan ini tidak dapat dibayar.');
   abort_unless($this->moneyInCents($locked->amount)===$this->moneyInCents($order->total),422,'Nominal bukti tidak sama dengan total pesanan.');
   $locked->update(['status'=>'approved','verified_by'=>auth()->id(),'verified_at'=>now()]);
   $payments->markPaid($order,null,[],"Pembayaran pesanan {$order->order_number} berhasil diverifikasi.");
  });
  return back()->with('message','Pembayaran disetujui.');
 }

 public function reject(Request $r,PaymentConfirmation $confirmation,PaymentService $payments){
  $this->authorize('update',$confirmation->order);
  $data=$r->validate(['rejection_reason'=>'required|string|max:2000']);
  DB::transaction(function()use($confirmation,$data,$payments){
   $order=Order::lockForUpdate()->findOrFail($confirmation->order_id);
   $locked=PaymentConfirmation::lockForUpdate()->findOrFail($confirmation->id);
   abort_unless($locked->status==='pending',422,'Bukti sudah diverifikasi.');
   abort_if($order->payment_status==='paid',422,'Pembayaran yang sudah disetujui tidak dapat ditolak.');
   $locked->update(['status'=>'rejected','rejection_reason'=>$data['rejection_reason'],'verified_by'=>auth()->id(),'verified_at'=>now()]);
   $hasOtherPending=PaymentConfirmation::where('order_id',$order->id)->where('status','pending')->where('id','!=',$locked->id)->exists();
   if(!$hasOtherPending){
    $order->update(['payment_status'=>'rejected','payment_rejection_reason'=>$data['rejection_reason']]);
    $payments->recordTransaction($order,'rejected',null,[],auth()->id());
   }
   $order->user?->notify(new PaymentNotification('Bukti pembayaran ditolak','Bukti pembayaran ditolak: '.$data['rejection_reason'],route('customer.orders.show',$order),$order->id));
  });
  return back()->with('message','Bukti pembayaran ditolak.');
 }

 private function moneyInCents(mixed $amount):int
 {
  return (int) round((float)$amount*100);
 }
}
