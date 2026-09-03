<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    public const STATUSES = ['waiting', 'pending', 'confirmed', 'processing', 'printed', 'completed', 'cancelled'];
    public const CUSTOMER_STATUSES = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

    protected $fillable = ['branch_id','customer_id','user_id','order_type','design_type','payment_method','payment_status','payment_reference','payment_gateway','gateway_transaction_id','snap_token','gateway_payload','paid_at','payment_expired_at','payment_rejection_reason','request_text','design_file_path','design_file_original_name','order_number','date','time','product_id','product_name','size','qty','price','subtotal','discount','total','status','production_queue_number','estimated_completion_at','note','customer_note','internal_note','fulfillment_method','recipient_name','recipient_whatsapp','shipping_address','shipping_city','shipping_district','shipping_postal_code','courier','shipping_cost','tracking_number','shipped_at','delivered_at','invoice_id'];
    protected $casts = ['date'=>'date','time'=>'datetime:H:i','qty'=>'decimal:2','price'=>'decimal:2','subtotal'=>'decimal:2','discount'=>'decimal:2','total'=>'decimal:2','shipping_cost'=>'decimal:2','gateway_payload'=>'array','paid_at'=>'datetime','payment_expired_at'=>'datetime','estimated_completion_at'=>'datetime','shipped_at'=>'datetime','delivered_at'=>'datetime'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function paymentConfirmations(){return $this->hasMany(PaymentConfirmation::class)->latest('id');}
    public function designFiles(){return $this->hasMany(OrderDesignFile::class)->latest('version');}
    public function designReviews(){return $this->hasMany(OrderDesignReview::class)->latest('id');}
    public function statusHistories(){return $this->hasMany(OrderStatusHistory::class)->latest('id');}
    public function paymentTransactions(){return $this->hasMany(PaymentTransaction::class)->latest('id');}
    public function refunds(){return $this->hasMany(PaymentRefund::class)->latest('id');}
    public function customerInvoice(){return $this->hasOne(CustomerInvoice::class);}

    public function isCustomerCustom(): bool { return $this->order_type === 'customer_custom'; }
    public function scopeProductionReady($query){return $query->where(fn($q)=>$q->where('order_type','!=','customer_custom')->orWhereNull('order_type')->orWhere('payment_status','paid'));}
}
