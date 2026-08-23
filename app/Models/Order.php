<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public const STATUSES = ['waiting', 'processing', 'printed', 'completed', 'cancelled'];

    protected $fillable = ['branch_id', 'customer_id', 'order_number', 'date', 'time', 'product_id', 'product_name', 'size', 'qty', 'price', 'subtotal', 'discount', 'total', 'status', 'note', 'invoice_id'];
    protected $casts = ['date' => 'date', 'time' => 'datetime:H:i', 'qty' => 'decimal:2', 'price' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
