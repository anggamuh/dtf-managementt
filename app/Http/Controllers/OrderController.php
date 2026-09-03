<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use ResolvesBranch;

    private const PER_PAGE_OPTIONS = [25, 50, 100, 250, 500];

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $perPage = $this->resolvePerPage($request);

        $orders = Order::with(['customer','user','branch'])
            ->productionReady()
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

    // Pesanan baru pakai form draft (orders.create) — bisa isi beberapa pesanan
    // lalu simpan sekaligus lewat storeBulk(). Edit tetap pakai orders.form (single-save).
    public function create(Request $request) { $branchId=$this->branchId($request); return view('orders.create',['branchId'=>$branchId,'branches'=>$this->branches(),'customers'=>Customer::where('branch_id',$branchId)->where('active',true)->orderBy('name')->get(),'products'=>Product::where('branch_id',$branchId)->where('active',true)->orderBy('name')->get()]); }
    public function store(Request $request) { $branchId=$this->branchId($request); $data=$this->validated($request, $branchId); $data['branch_id']=$branchId; $data['order_number']='ORD-'.now()->format('YmdHis').'-'.random_int(100,999); $data=$this->totals($data); Order::create($data); return redirect()->route('orders.index',['branch_id'=>$data['branch_id']])->with('message','Pesanan berhasil disimpan.'); }
    public function edit(Request $request,Order $order) {
        $this->guard($order);
        return view('orders.form',[
            'order'=>$order,
            'branches'=>$this->branches(),
            'customers'=>Customer::where('active',true)->where(fn ($query) => $query
                ->where('branch_id', $order->branch_id)
                ->orWhereKey($order->customer_id))->orderBy('name')->get(),
            'products'=>Product::where('branch_id',$order->branch_id)->where('active',true)->orderBy('name')->get(),
            'returnUrl'=>url()->previous(), // URL index (lengkap dengan filter) sebelum masuk ke halaman edit
        ]);
    }

    public function update(Request $request,Order $order) {
        $this->guard($order);
        $data=$this->totals($this->validated($request, (int) $order->branch_id));
        $order->update($data);

        $returnUrl = $request->input('return_url');
        // dicek harus URL milik app sendiri, biar tidak bisa dipakai buat open-redirect
        if ($returnUrl && str_starts_with($returnUrl, url('/'))) {
            return redirect()->to($returnUrl)->with('message', 'Pesanan berhasil diperbarui.');
        }

        return redirect()->route('orders.index',['branch_id'=>$order->branch_id])->with('message','Pesanan berhasil diperbarui.');
    }
    public function destroy(Order $order)
    {
        $this->guard($order);

        if ($order->isCustomerCustom() || $order->payment_status === 'paid') {
            return back()->with('error', 'Pesanan customer atau pesanan yang sudah dibayar tidak dapat dihapus. Gunakan pembatalan yang tercatat.');
        }

        if ($order->invoice_id) {
            return back()->with('error', 'Pesanan ini sudah ditagihkan dan tidak dapat dihapus.');
        }

        $order->delete();

        return back()->with('message', 'Pesanan berhasil dihapus.');
    }

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

    public function customerDetail(Order $order) { $this->authorize('view',$order); abort_unless($order->isCustomerCustom(),404); $order->load(['designFiles.uploader','designReviews.user','statusHistories.actor','refunds.processor','paymentTransactions']); return view('orders.customer-detail',compact('order')); }
    public function updateCustomerStatus(Request $request,Order $order) {
        $this->authorize('update',$order);
        abort_unless($order->isCustomerCustom(),404);
        $data=$request->validate(['status'=>'required|in:pending,confirmed,processing,file_review,revision_required,awaiting_design_approval,production_queue,printing,quality_control,ready_pickup,shipped,completed,cancelled','customer_note'=>'nullable|string|max:2000','internal_note'=>'nullable|string|max:5000','estimated_completion_at'=>'nullable|date']);
        DB::transaction(function()use($order,$data){
            $locked=Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->payment_status==='paid',422,'Pesanan belum dibayar dan belum boleh diproses.');
            $transitions=[
                'pending'=>['pending','file_review','cancelled'],
                'confirmed'=>['confirmed','processing','cancelled'],
                'processing'=>['processing','completed','cancelled'],
                'file_review'=>['file_review','revision_required','cancelled'],
                'revision_required'=>['revision_required','file_review','cancelled'],
                'awaiting_design_approval'=>['awaiting_design_approval','revision_required','cancelled'],
                'production_queue'=>['production_queue','printing','cancelled'],
                'printing'=>['printing','quality_control','cancelled'],
                'quality_control'=>['quality_control','ready_pickup','shipped','cancelled'],
                'ready_pickup'=>['ready_pickup','completed'],
                'shipped'=>['shipped','completed'],
                'completed'=>['completed'],
                'cancelled'=>['cancelled'],
            ];
            abort_unless(in_array($data['status'],$transitions[$locked->status]??[],true),422,'Perubahan status pesanan tidak diizinkan.');
            $locked->update($data);
        });
        return back()->with('message','Status pesanan diperbarui.');
    }
    public function downloadDesign(Order $order) { $this->authorize('downloadDesign',$order); abort_unless($order->design_file_path&&Storage::disk('local')->exists($order->design_file_path),404); return Storage::disk('local')->download($order->design_file_path,$order->design_file_original_name); }

    public function confirmComplete(Order $order)
    {
        $this->guard($order);
        return response()->json(['message' => 'Tandai pesanan ' . $order->order_number . ' sebagai selesai?']);
    }

    /**
     * Simpan beberapa draft pesanan sekaligus (dari panel draft di form pesanan baru).
     * orders_json berisi array pesanan yang sudah dikumpulkan di sisi client
     * sebelum dikirim ke server dalam satu request.
     */
    public function storeBulk(Request $request)
    {
        $branchId = $this->branchId($request);

        $payload = $request->validate([
            'orders_json' => 'required|string',
        ]);

        $rows = json_decode($payload['orders_json'], true);
        abort_if(!is_array($rows) || count($rows) === 0, 422, 'Tidak ada draft pesanan untuk disimpan.');

        $rules = [
            'customer_id' => ['required', Rule::exists('customers','id')->where('branch_id',$branchId)],
            'date' => 'required|date',
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('branch_id', $branchId)],
            'product_name' => 'required|string|max:255',
            'size' => 'nullable|string|max:100',
            'qty' => 'required|numeric|gt:0',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'status' => 'required|in:waiting,processing,printed,completed,cancelled',
            'note' => 'nullable|string',
        ];

        foreach ($rows as $row) {
            validator($row, $rules)->validate();
        }

        DB::transaction(function () use ($rows, $branchId) {
            foreach ($rows as $i => $row) {
                $data = $this->totals($row);
                $data['branch_id'] = $branchId;
                // suffix pakai index + random biar order_number tetap unik walau
                // beberapa draft disimpan dalam detik yang sama
                $data['order_number'] = 'ORD-'.now()->format('YmdHis').'-'.str_pad($i, 2, '0', STR_PAD_LEFT).'-'.random_int(10, 99);
                Order::create($data);
            }
        });

        return redirect()->route('orders.index', ['branch_id' => $branchId])
            ->with('message', count($rows).' pesanan berhasil disimpan.');
    }

    private function validated(Request $r, int $branchId):array { return $r->validate(['branch_id'=>'nullable|exists:branches,id','customer_id'=>['required',Rule::exists('customers','id')->where('branch_id',$branchId)],'date'=>'required|date','product_id'=>['nullable', Rule::exists('products', 'id')->where('branch_id', $branchId)],'product_name'=>'required|string|max:255','size'=>'nullable|string|max:100','qty'=>'required|numeric|gt:0','price'=>'required|numeric|min:0','discount'=>'nullable|numeric|min:0','status'=>'required|in:waiting,processing,printed,completed,cancelled','note'=>'nullable|string']); }
    private function totals(array $d):array { $d['subtotal']=$d['qty']*$d['price'];$d['discount']=$d['discount']??0;$d['total']=max(0,$d['subtotal']-$d['discount']);return $d; }

    public function bulkDestroy(Request $request)
    {
        $branchId = $this->branchId($request);
        $ids = $request->input('ids', []);
        $requestedCount = count($ids);
        $orders = Order::where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->whereNull('invoice_id')
            ->where(fn($query)=>$query->whereNull('order_type')->orWhere('order_type','!=','customer_custom'))
            ->where(fn($query)=>$query->whereNull('payment_status')->orWhere('payment_status','!=','paid'))
            ->get();

        if ($orders->count() !== $requestedCount) {
            return back()->with('error', 'Pesanan tidak dapat dihapus karena sudah ditagihkan, sudah dibayar, atau merupakan pesanan customer.');
        }

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
