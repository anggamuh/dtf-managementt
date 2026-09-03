<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentConfirmation extends Model {protected $fillable=['order_id','payment_account_id','sender_name','sender_bank','transferred_at','amount','proof_path','proof_original_name','note','status','rejection_reason','verified_by','verified_at'];protected $casts=['transferred_at'=>'date','amount'=>'decimal:2','verified_at'=>'datetime'];public function order(){return $this->belongsTo(Order::class);}public function account(){return $this->belongsTo(PaymentAccount::class,'payment_account_id');}public function verifier(){return $this->belongsTo(User::class,'verified_by');}}
