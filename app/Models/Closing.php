<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Closing extends Model
{
    protected $fillable = [
        'branch_id',
        'month',
        'year',
        'period_start',
        'period_end',
        'income',
        'expense',
        'hpp',
        'profit',
        'remaining_material',
        'saldo_tahanan',
        'saldo_realtime',
        'gaji_karyawan',
        'operasional',
        'lain_lain',
        'teknisi_mesin',
        'hpp_per_meter',
        'is_locked',
        'locked_at',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'income' => 'decimal:2',
        'expense' => 'decimal:2',
        'hpp' => 'decimal:2',
        'profit' => 'decimal:2',
        'remaining_material' => 'decimal:2',
        'saldo_tahanan' => 'decimal:2',
        'saldo_realtime' => 'decimal:2',
        'gaji_karyawan' => 'decimal:2',
        'operasional' => 'decimal:2',
        'lain_lain' => 'decimal:2',
        'teknisi_mesin' => 'decimal:2',
        'hpp_per_meter' => 'decimal:2',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    // ========================================================================
    // RELATIONS
    // ========================================================================

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ClosingMaterial::class);
    }

    // ========================================================================
    // SCOPES
    // ========================================================================

    public function scopeForMonth($query, int $month, int $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    // ========================================================================
    // ENTITY METHODS
    // ========================================================================

    /**
     * Kunci closing:
     * 1. Dorong stock_akhir tiap komponen material ke stok Material sebenarnya.
     * 2. Tandai closing sebagai locked.
     * 3. Stock_akhir closing ini otomatis jadi stock_awal closing bulan depan
     *    lewat syncMaterialComponents() di ClosingService.
     */
    public function lock(): void
    {
        abort_if($this->is_locked, 403, 'Closing bulan ini sudah dikunci.');

        DB::transaction(function () {
            $this->loadMissing('materials.material');

            foreach ($this->materials as $component) {
                $material = $component->material;
                $diff = (float) $component->stock_akhir - (float) $material->stock;

                if ($diff !== 0.0) {
                    $material->movements()->create([
                        'date' => now()->toDateString(),
                        'type' => 'opname',
                        'quantity' => $diff,
                        'note' => "Closing {$this->month}/{$this->year}",
                    ]);
                }

                $material->update(['stock' => $component->stock_akhir]);
            }

            $this->update(['is_locked' => true, 'locked_at' => now()]);
        });
    }
}
