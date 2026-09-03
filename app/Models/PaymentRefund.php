<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = ['order_id','payment_transaction_id','reference','provider_reference','amount','reason','status','requested_by','processed_by','processed_at'];
    protected $casts = ['amount'=>'decimal:2','processed_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);}
    public function transaction(){return $this->belongsTo(PaymentTransaction::class,'payment_transaction_id');}
    public function requester(){return $this->belongsTo(User::class,'requested_by');}
    public function processor(){return $this->belongsTo(User::class,'processed_by');}
}
