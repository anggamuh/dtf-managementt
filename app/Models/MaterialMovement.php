<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialMovement extends Model
{
    protected $fillable = ['material_id', 'expense_id', 'date', 'type', 'quantity', 'note'];
    protected $casts = ['date' => 'date', 'quantity' => 'decimal:2'];
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
}
