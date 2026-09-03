<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDesignReview extends Model
{
    protected $fillable = ['order_id','order_design_file_id','action','comment','user_id'];
    public function order(){return $this->belongsTo(Order::class);}
    public function file(){return $this->belongsTo(OrderDesignFile::class,'order_design_file_id');}
    public function user(){return $this->belongsTo(User::class);}
}
