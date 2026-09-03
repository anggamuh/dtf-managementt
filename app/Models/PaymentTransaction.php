<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = ['order_id','reference','provider','external_transaction_id','method','status','amount','gateway_fee','payload','processed_at','processed_by'];
    protected $casts = ['amount'=>'decimal:2','gateway_fee'=>'decimal:2','payload'=>'array','processed_at'=>'datetime'];
    public function order(){return $this->belongsTo(Order::class);}
    public function processor(){return $this->belongsTo(User::class,'processed_by');}
    public function refunds(){return $this->hasMany(PaymentRefund::class);}
}
