<?php
namespace App\Http\Controllers\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Services\OrderDesignFileService;
class OrderController extends Controller {
    public function index(Request $request): View { $orders=$request->user()->orders()->where('order_type','customer_custom')->with('branch')->latest('id')->paginate(15); return view('customer.orders.index',compact('orders')); }
    public function create(): View { return view('customer.orders.create',['branches'=>Branch::where('active',true)->orderBy('name')->get()]); }
    public function store(StoreCustomerOrderRequest $request,OrderDesignFileService $designFiles) {
        $data=$request->validated(); $path=$request->file('design_file')?->store('customer-designs/'.now()->format('Y/m'),'local');
        $order=null;try { $order=DB::transaction(function() use($request,$data,$path,$designFiles){$unitPrice=(int)config('customer_order.unit_price');$subtotal=$unitPrice*(int)$data['quantity']; $order=Order::create([
            'branch_id'=>$data['branch_id'],'user_id'=>$request->user()->id,'customer_id'=>null,'order_type'=>'customer_custom','design_type'=>$data['design_type'],'request_text'=>$data['request_text']??null,
            'design_file_path'=>$path,'design_file_original_name'=>$request->file('design_file')?->getClientOriginalName(),'order_number'=>'ORD-C-'.now()->format('YmdHis').'-'.random_int(100,999),
            'date'=>now()->toDateString(),'time'=>now()->format('H:i:s'),'product_id'=>null,'product_name'=>'Pesanan Custom','size'=>$data['size'],'qty'=>$data['quantity'],
            'price'=>$unitPrice,'subtotal'=>$subtotal,'discount'=>0,'total'=>$subtotal,'status'=>'waiting_payment','payment_method'=>$data['payment_method'],'payment_status'=>'unpaid','payment_gateway'=>$data['payment_method']==='payment_gateway'?'midtrans':null,'payment_reference'=>'PAY-'.str_replace(['~','.'],'-',uniqid('',true)),'payment_expired_at'=>now()->addHours(config('customer_order.payment_expiry_hours')),'note'=>$data['notes']??null,
        ]); if($path)$designFiles->store($order,$request->file('design_file'),'customer_source',$request->user(),null,$path); return $order; }); } catch(\Throwable $e) { if($path) Storage::disk('local')->delete($path); throw $e; }
        return redirect()->route('customer.orders.show',$order)->with('message','Pesanan dibuat. Silakan selesaikan pembayaran.');
    }
    public function show(Order $order): View { $this->authorize('view',$order);$order->load(['branch','customerInvoice','designFiles.uploader','designReviews.user','statusHistories.actor','refunds','paymentConfirmations'=>fn($q)=>$q->latest(),'paymentConfirmations.account']);$accounts=\App\Models\PaymentAccount::where('branch_id',$order->branch_id)->where('is_active',true)->get();return view('customer.orders.show',compact('order','accounts')); }
    public function download(Request $request,Order $order) { $this->authorize('downloadDesign',$order); abort_unless($order->design_file_path&&Storage::disk('local')->exists($order->design_file_path),404);return $request->boolean('inline')?response()->file(Storage::disk('local')->path($order->design_file_path),['Content-Type'=>'image/png']):Storage::disk('local')->download($order->design_file_path,$order->design_file_original_name); }
    public function cancel(Order $order){
        $this->authorize('view',$order);
        DB::transaction(function()use($order){
            $locked=Order::lockForUpdate()->findOrFail($order->id);
            abort_if(in_array($locked->payment_status,['paid','pending_verification','pending'],true),422,'Pesanan dengan pembayaran aktif hanya dapat dibatalkan melalui admin.');
            abort_if($locked->status==='cancelled',422,'Pesanan sudah dibatalkan.');
            $locked->update(['status'=>'cancelled']);
        });
        return back()->with('message','Pesanan dibatalkan.');
    }
}
