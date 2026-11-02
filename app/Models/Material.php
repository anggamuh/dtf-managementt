<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'branch_id',
        'machine_id',
        'name',
        'unit',
        'stock',
        'minimum_stock',
        'price',
        'price_pet_1',
        'price_pet_2',
        'price_pet_3',
        'price_pet_4',
        'supplier',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'price' => 'decimal:2',
        'price_pet_1' => 'decimal:2',
        'price_pet_2' => 'decimal:2',
        'price_pet_3' => 'decimal:2',
        'price_pet_4' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->machine
            ? "{$this->name} ({$this->machine->head_count} Head)"
            : $this->name;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function closingMaterials(): HasMany
    {
        return $this->hasMany(ClosingMaterial::class);
    }
}
