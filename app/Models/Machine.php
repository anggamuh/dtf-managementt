<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'head_count',
        'is_active',
    ];

    protected $casts = [
        'head_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
