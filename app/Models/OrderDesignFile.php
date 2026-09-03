<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDesignFile extends Model
{
    protected $fillable = ['order_id','version','kind','path','original_name','mime','size_bytes','width','height','sha256','uploaded_by','note'];
    public function order(){return $this->belongsTo(Order::class);}
    public function uploader(){return $this->belongsTo(User::class,'uploaded_by');}
    public function reviews(){return $this->hasMany(OrderDesignReview::class);}
}
