<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = ['order_id','old_status','new_status','note','customer_visible','changed_by'];
    protected $casts = ['customer_visible'=>'boolean'];
    public function order(){return $this->belongsTo(Order::class);}
    public function actor(){return $this->belongsTo(User::class,'changed_by');}
}
