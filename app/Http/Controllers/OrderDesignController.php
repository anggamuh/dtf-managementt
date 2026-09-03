<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDesignFile;
use App\Notifications\PaymentNotification;
use App\Services\BranchNotificationService;
use App\Services\OrderDesignFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderDesignController extends Controller
{
    private const PNG_RULES=['required','file','mimes:png','mimetypes:image/png','dimensions:min_width=1,min_height=1,max_width=20000,max_height=20000','max:10240'];

    public function adminPreview(Request $request,Order $order,OrderDesignFileService $files){
        $this->authorize('update',$order);abort_unless($order->isCustomerCustom()&&$order->payment_status==='paid',422);
        $data=$request->validate(['preview'=>self::PNG_RULES,'note'=>'nullable|string|max:2000']);
        DB::transaction(function()use($request,$order,$files,$data){$locked=Order::lockForUpdate()->findOrFail($order->id);abort_unless(in_array($locked->status,['file_review','revision_required'],true),422,'Preview tidak dapat diunggah pada status ini.');$file=$files->store($locked,$request->file('preview'),'admin_preview',$request->user(),$data['note']??null);$locked->designReviews()->create(['order_design_file_id'=>$file->id,'action'=>'preview_uploaded','comment'=>$data['note']??null,'user_id'=>$request->user()->id]);$locked->update(['status'=>'awaiting_design_approval']);$locked->user->notify(new PaymentNotification('Preview desain tersedia','Preview desain pesanan '.$locked->order_number.' siap diperiksa.',route('customer.orders.show',$locked),$locked->id));});
        return back()->with('message','Preview desain dikirim kepada customer.');
    }

    public function customerRevision(Request $request,Order $order,OrderDesignFileService $files){
        $this->authorize('view',$order);$data=$request->validate(['design_file'=>self::PNG_RULES,'note'=>'nullable|string|max:2000']);
        DB::transaction(function()use($request,$order,$files,$data){$locked=Order::lockForUpdate()->findOrFail($order->id);abort_unless($locked->status==='revision_required',422,'Upload revisi tidak tersedia.');$file=$files->store($locked,$request->file('design_file'),'customer_revision',$request->user(),$data['note']??null);$locked->designReviews()->create(['order_design_file_id'=>$file->id,'action'=>'customer_reuploaded','comment'=>$data['note']??null,'user_id'=>$request->user()->id]);$locked->update(['status'=>'file_review']);app(BranchNotificationService::class)->send($locked,'File revisi tersedia','Customer mengunggah revisi untuk '.$locked->order_number.'.');});
        return back()->with('message','File revisi berhasil dikirim.');
    }

    public function approve(Request $request,Order $order){
        $this->authorize('view',$order);
        DB::transaction(function()use($request,$order){$locked=Order::lockForUpdate()->findOrFail($order->id);abort_unless($locked->status==='awaiting_design_approval',422,'Desain belum siap disetujui.');$preview=$locked->designFiles()->where('kind','admin_preview')->latest('version')->firstOrFail();$locked->designReviews()->create(['order_design_file_id'=>$preview->id,'action'=>'approved','user_id'=>$request->user()->id]);$next=(int)Order::where('branch_id',$locked->branch_id)->lockForUpdate()->max('production_queue_number')+1;$locked->update(['status'=>'production_queue','production_queue_number'=>$next]);app(BranchNotificationService::class)->send($locked,'Desain disetujui','Desain '.$locked->order_number.' disetujui customer dan masuk antrean produksi.');});
        return back()->with('message','Desain disetujui dan masuk antrean produksi.');
    }

    public function requestRevision(Request $request,Order $order){
        $this->authorize('view',$order);$data=$request->validate(['comment'=>'required|string|max:2000']);
        DB::transaction(function()use($request,$order,$data){$locked=Order::lockForUpdate()->findOrFail($order->id);abort_unless($locked->status==='awaiting_design_approval',422,'Revisi tidak tersedia pada status ini.');$preview=$locked->designFiles()->where('kind','admin_preview')->latest('version')->firstOrFail();$locked->designReviews()->create(['order_design_file_id'=>$preview->id,'action'=>'revision_requested','comment'=>$data['comment'],'user_id'=>$request->user()->id]);$locked->update(['status'=>'revision_required']);app(BranchNotificationService::class)->send($locked,'Revisi desain diminta','Customer meminta revisi desain '.$locked->order_number.'.');});
        return back()->with('message','Permintaan revisi dikirim.');
    }

    public function download(Order $order,OrderDesignFile $file){$this->authorize('downloadDesign',$order);abort_unless($file->order_id===$order->id,404);abort_unless(Storage::disk('local')->exists($file->path),404);return Storage::disk('local')->download($file->path,$file->original_name);}
}
