<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerPeriodBalance extends Model
{
    protected $fillable = [
        'branch_id',
        'period_start',
        'period_end',
        'saldo_realtime',
        'locked_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'saldo_realtime' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
