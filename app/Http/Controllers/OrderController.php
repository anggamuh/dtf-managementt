<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ResolvesBranch;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 250, 500];

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $perPage = $this->resolvePerPage($request);

        $orders = Order::with('customer')
            ->when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->search, fn($q, $s) => $q->where(fn($q) => $q->where('order_number', 'like', "%{$s}%")->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->date_start, fn($q, $s) => $q->whereDate('date', '>=', $s))
            ->when($request->date_end, fn($q, $s) => $q->whereDate('date', '<=', $s))
            ->latest('date')
            ->paginate($perPage)
            ->withQueryString();

        return view('orders.index', compact('orders', 'branchId', 'perPage') + [
            'branches' => $this->branches(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function create(Request $request) { $branchId=$this->branchId($request); return view('orders.form',['order'=>new Order(['date'=>now()->toDateString(),'branch_id'=>$branchId,'qty'=>1]),'branches'=>$this->branches(),'customers'=>Customer::where('branch_id',$branchId)->where('active',true)->orderBy('name')->get(),'products'=>Product::where('branch_id',$branchId)->where('active',true)->orderBy('name')->get()]); }
    public function store(Request $request) { $data=$this->validated($request); $data['branch_id']=$this->branchId($request); $data['order_number']='ORD-'.now()->format('YmdHis').'-'.random_int(100,999); $data=$this->totals($data); Order::create($data); return redirect()->route('orders.index',['branch_id'=>$data['branch_id']])->with('message','Pesanan berhasil disimpan.'); }
    public function edit(Request $request,Order $order) { $this->guard($order); return view('orders.form',['order'=>$order,'branches'=>$this->branches(),'customers'=>Customer::where('branch_id',$order->branch_id)->where('active',true)->orderBy('name')->get(),'products'=>Product::where('branch_id',$order->branch_id)->where('active',true)->orderBy('name')->get()]); }
    public function update(Request $request,Order $order) { $this->guard($order); $data=$this->totals($this->validated($request)); $order->update($data); return redirect()->route('orders.index',['branch_id'=>$order->branch_id])->with('message','Pesanan berhasil diperbarui.'); }
    public function destroy(Order $order) { $this->guard($order); abort_if($order->invoice_id,422,'Pesanan yang sudah ditagihkan tidak dapat dihapus.'); $order->delete(); return back()->with('message','Pesanan berhasil dihapus.'); }
    
    public function complete(Request $request, Order $order)
    {
        $this->guard($order);
        
        if ($order->status === 'completed') {
            return back()->with('error', 'Pesanan ini sudah diselesaikan.');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'completed']);
        });

        return back()->with('message', 'Pesanan berhasil diselesaikan.');
    }
    
    public function confirmComplete(Order $order)
    {
        $this->guard($order);
        return response()->json(['message' => 'Tandai pesanan ' . $order->order_number . ' sebagai selesai?']);
    }
    private function validated(Request $r):array { return $r->validate(['branch_id'=>'nullable|exists:branches,id','customer_id'=>'required|exists:customers,id','date'=>'required|date','product_id'=>'nullable|exists:products,id','product_name'=>'required|string|max:255','size'=>'nullable|string|max:100','qty'=>'required|numeric|gt:0','price'=>'required|numeric|min:0','discount'=>'nullable|numeric|min:0','status'=>'required|in:waiting,processing,printed,completed,cancelled','note'=>'nullable|string']); }
    private function totals(array $d):array { $d['subtotal']=$d['qty']*$d['price'];$d['discount']=$d['discount']??0;$d['total']=max(0,$d['subtotal']-$d['discount']);return $d; }

    public function bulkDestroy(Request $request)
    {
        $branchId = $this->branchId($request);
        $ids = $request->input('ids', []);
        $orders = Order::where('branch_id', $branchId)->whereIn('id', $ids)->whereNull('invoice_id')->get();
        foreach ($orders as $order) {
            $order->delete();
        }
        return back()->with('message', count($orders).' pesanan berhasil dihapus.');
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', self::PER_PAGE_OPTIONS[0]);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_OPTIONS[0];
    }

    private function guard(Order $o):void { abort_unless($this->canAccessAllBranches() || $o->branch_id===auth()->user()->branch_id,403); }
}