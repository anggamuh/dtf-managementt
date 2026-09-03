<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDesignFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrderDesignFileService
{
    public function store(Order $order, UploadedFile $upload, string $kind, ?User $uploader, ?string $note=null, ?string $existingPath=null): OrderDesignFile
    {
        $path=$existingPath ?: $upload->store('customer-designs/'.now()->format('Y/m'),'local');
        $absolute=Storage::disk('local')->path($path);
        $dimensions=@getimagesize($absolute) ?: [null,null];
        $version=(int)$order->designFiles()->lockForUpdate()->max('version')+1;

        return $order->designFiles()->create([
            'version'=>$version,'kind'=>$kind,'path'=>$path,'original_name'=>$upload->getClientOriginalName(),
            'mime'=>$upload->getMimeType() ?: 'application/octet-stream','size_bytes'=>filesize($absolute),
            'width'=>$dimensions[0],'height'=>$dimensions[1],'sha256'=>hash_file('sha256',$absolute),
            'uploaded_by'=>$uploader?->id,'note'=>$note,
        ]);
    }
}
