<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

class MaterialPurchaseService
{
    public function sync(Expense $expense): void
    {
        if (! $expense->isMaterialPurchase()) {
            $this->remove($expense);

            return;
        }

        DB::transaction(function () use ($expense) {
            $material = Material::query()
                ->whereKey($expense->material_id)
                ->where('branch_id', $expense->branch_id)
                ->lockForUpdate()
                ->firstOrFail();

            $movement = $material->movements()
                ->where('expense_id', $expense->id)
                ->first();

            $previousQuantity = (float) ($movement?->quantity ?? 0);

            // Kembalikan stok ke kondisi sebelum pembelian ini
            $stockBeforePurchase = (float) $material->stock - $previousQuantity;

            $incomingQuantity = (float) $expense->quantity;

            // Catat/update pergerakan pembelian
            $material->movements()->updateOrCreate(
                ['expense_id' => $expense->id],
                [
                    'date' => $expense->date,
                    'type' => 'purchase',
                    'quantity' => $incomingQuantity,
                    'note' => $expense->description,
                ],
            );

            // Harga material TIDAK diubah.
            // Material.price adalah harga standar/master.

            $newStock = $stockBeforePurchase + $incomingQuantity;

            $material->update([
                'stock' => $newStock,
            ]);
        });
    }

    public function remove(Expense $expense): void
    {
        $movement = $expense->material?->movements()
            ->where('expense_id', $expense->id)
            ->first();

        if (! $movement) {
            return;
        }

        DB::transaction(function () use ($movement) {
            $material = Material::query()
                ->lockForUpdate()
                ->findOrFail($movement->material_id);

            $material->update([
                'stock' => max(
                    0,
                    (float) $material->stock - (float) $movement->quantity
                ),
            ]);

            $movement->delete();
        });
    }
}