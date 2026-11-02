<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingMaterial extends Model
{
    protected $fillable = [
        'closing_id',
        'material_id',
        'stock_awal',
        'harga_komponen',
        'harga_persediaan',
        'persediaan_lapisan',
        'hpp_pemakaian',
        'harga_pet_1',
        'harga_pet_2',
        'harga_pet_3',
        'harga_pet_4',
        'qty',
        'pembelian',
        'stock_akhir',
    ];

    protected $casts = [
        'stock_awal' => 'decimal:2',
        'harga_komponen' => 'decimal:2',
        'harga_persediaan' => 'decimal:2',
        'persediaan_lapisan' => 'array',
        'hpp_pemakaian' => 'decimal:2',
        'harga_pet_1' => 'decimal:2',
        'harga_pet_2' => 'decimal:2',
        'harga_pet_3' => 'decimal:2',
        'harga_pet_4' => 'decimal:2',
        'qty' => 'decimal:2',
        'pembelian' => 'decimal:2',
        'stock_akhir' => 'decimal:2',
    ];

    public function closing(): BelongsTo
    {
        return $this->belongsTo(Closing::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Pemakaian = Stock Awal + Barang Masuk (qty) - Stock Akhir
     * Merepresentasikan material yang terpakai dalam periode ini.
     */
    public function getPemakaianAttribute(): float
    {
        return (float) $this->stock_awal + (float) $this->qty - (float) $this->stock_akhir;
    }

    /**
     * Total Harga Material = Pemakaian × Harga Komponen (weighted average)
     * Ini adalah biaya material yang masuk ke HPP.
     */
    public function getTotalHargaAttribute(): float
    {
        if ($this->hpp_pemakaian !== null) {
            return (float) $this->hpp_pemakaian;
        }

        return $this->pemakaian * (float) ($this->harga_persediaan ?? $this->harga_komponen);
    }
}
