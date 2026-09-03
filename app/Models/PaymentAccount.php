<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentAccount extends Model {protected $fillable=['branch_id','bank_name','account_number','account_holder','instructions','is_active'];protected $casts=['is_active'=>'boolean'];public function branch(){return $this->belongsTo(Branch::class);}public function confirmations(){return $this->hasMany(PaymentConfirmation::class);}}
