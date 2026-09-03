<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoice extends Model
{
    protected $fillable = ['order_id','user_id','branch_id','invoice_number','product_total','shipping_cost','total','paid_at'];
    protected $casts = ['product_total'=>'decimal:2','shipping_cost'=>'decimal:2','total'=>'decimal:2','paid_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);}
    public function user(){return $this->belongsTo(User::class);}
    public function branch(){return $this->belongsTo(Branch::class);}
}
