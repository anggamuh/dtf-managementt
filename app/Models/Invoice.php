<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'branch_id',
        'customer_id',
        'invoice_number',
        'date',
        'period_start',
        'period_end',
        'subtotal',
        'discount',
        'shipping',
        'total',
        'paid',
        'remaining',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /*
    |--------------------------------------------------------------------
    | Live totals — dihitung langsung dari orders saat ini, BUKAN dari
    | kolom subtotal/total yang tersimpan. Ini jadi satu-satunya sumber
    | kebenaran dipakai baik di index maupun print, supaya kalau order
    | dikoreksi setelah invoice dibuat, angkanya otomatis ikut berubah
    | di mana pun invoice ini ditampilkan — tidak ada lagi snapshot yang
    | bisa basi.
    |--------------------------------------------------------------------
    */

    // Total qty semua order yang terhubung ke invoice ini, saat ini juga.
    public function getLiveQtyAttribute(): float
    {
        return (float) $this->orders->sum('qty');
    }

    // Subtotal = jumlah order->total (sudah termasuk diskon per-order),
    // dihitung ulang tiap kali diakses.
    public function getLiveSubtotalAttribute(): float
    {
        return (float) $this->orders->sum('total');
    }

    // Total akhir = subtotal live dikurangi diskon di level invoice.
    public function getLiveTotalAttribute(): float
    {
        return max(0, $this->live_subtotal - (float) $this->discount);
    }

    // Sisa tagihan mengikuti live_total, bukan kolom total yang statis.
    public function getLiveRemainingAttribute(): float
    {
        return max(0, $this->live_total - (float) $this->paid);
    }

    // Status turunan dari live_total, supaya badge "Lunas/Sebagian/Draft"
    // juga selalu akurat walau order-nya berubah setelah dibuat.
    public function getLiveStatusAttribute(): string
    {
        if ($this->paid >= $this->live_total && $this->live_total > 0) {
            return 'paid';
        }

        return $this->paid > 0 ? 'partial' : 'draft';
    }
}