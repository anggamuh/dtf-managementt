<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SpreadsheetPasteImporter
{
    private MaterialPurchaseService $purchases;

    private array $createdMaterials = [];

    public function __construct(MaterialPurchaseService $purchases)
    {
        $this->purchases = $purchases;
    }

    public function importIncome(string $text, int $branchId): int
    {
        $rows = $this->rows($text, 7)->filter(fn ($row) => $this->date($row[0] ?? null) && filled($row[1] ?? null) && filled($row[2] ?? null));
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['paste_data' => 'Tidak ditemukan baris pemasukan yang valid. Paste tabel Excel, termasuk kolom tanggal hingga keterangan.']);
        }

        return DB::transaction(function () use ($rows, $branchId) {
            $count = 0;
            $batch = now()->format('YmdHis');
            $orderSeq = 0;

            foreach ($rows as $row) {
                $date = $this->date($row[0]);
                $customer = Customer::firstOrCreate(
                    ['branch_id' => $branchId, 'name' => trim($row[1])],
                    ['active' => true]
                );

                $qty = $this->number($row[3] ?? '0');
                $price = $this->money($row[4] ?? '0');
                $total = $this->money($row[5] ?? '0');

                if ($qty <= 0 || $total <= 0) {
                    throw ValidationException::withMessages(['paste_data' => "Baris {$row[0]} / {$row[1]} memiliki qty atau total tidak valid."]);
                }

                $product = Product::firstOrCreate(
                    ['branch_id' => $branchId, 'name' => trim($row[2])],
                    ['price' => $price, 'active' => true]
                );

                $orderSeq++;

                // Sengaja TIDAK diisi invoice_id — order ini masuk sebagai "belum
                // ditagihkan" dan akan muncul di halaman "Buat dari Pesanan" untuk
                // ditagihkan manual, bukan otomatis dibuatkan invoice seperti sebelumnya.
                Order::create([
                    'branch_id' => $branchId,
                    'customer_id' => $customer->id,
                    'order_number' => 'IMP-ORD-'.$batch.'-'.str_pad($orderSeq, 4, '0', STR_PAD_LEFT),
                    'date' => $date,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $total,
                    'discount' => 0,
                    'total' => $total,
                    'status' => 'completed',
                    'note' => trim($row[6] ?? ''),
                ]);

                $count++;
            }

            return $count;
        });
    }

    /**
     * Import expenses from pasted Excel data.
     *
     * Untuk kategori "Bahan Baku":
     * - Mencari material berdasarkan deskripsi (nama material)
     * - Jika tidak ditemukan, membuat material baru
     * - Mengisi material_id dan quantity
     * - Memanggil MaterialPurchaseService::sync() untuk update stok & harga
     */
    public function importExpenses(string $text, int $branchId): int
    {
        $rows = $this->rows($text, 8)->filter(fn ($row) => $this->date($row[0] ?? null) && filled($row[3] ?? null) && $this->money($row[6] ?? '0') > 0);
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['paste_data' => 'Tidak ditemukan baris pengeluaran yang valid.']);
        }

        $this->createdMaterials = [];

        return DB::transaction(function () use ($rows, $branchId) {
            $count = 0;

            foreach ($rows as $row) {
                $category = $this->category($row[2] ?? '');
                $materialId = null;
                $quantity = null;
                $material = null;

                // Untuk "Bahan Baku": cari atau buat material berdasarkan deskripsi
                if ($category === 'Bahan Baku') {
                    $materialName = $this->baseMaterialName($row[3]);
                    $material = $this->findMaterialForExpense($branchId, $materialName, $row[3]);

                    if (! $material) {
                        if ($materialName === '') {
                            throw ValidationException::withMessages(['paste_data' => 'Nama dasar material tidak boleh kosong.']);
                        }
                        $material = Material::create([
                            'branch_id' => $branchId,
                            'machine_id' => null,
                            'name' => $materialName,
                            'unit' => 'kg',
                            'stock' => 0,
                            'minimum_stock' => 0,
                            'price' => 0,
                            'is_active' => true,
                        ]);
                        $material->load('machine');
                        $this->createdMaterials[] = $material->display_name;
                    }

                    $materialId = $material->id;
                    $quantity = $this->number($row[4] ?? '1');
                }

                $expense = Expense::create([
                    'branch_id' => $branchId,
                    'material_id' => $materialId,
                    'date' => $this->date($row[0]),
                    'category' => $category,
                    'description' => $material?->display_name ?? trim($row[3]),
                    'amount' => $this->money($row[6]),
                    'quantity' => $quantity,
                    'payment_method' => trim($row[7] ?? '') ?: 'Tunai',
                ]);

                // Sync material purchase untuk "Bahan Baku"
                if ($category === 'Bahan Baku') {
                    $this->purchases->sync($expense);
                }

                $count++;
            }

            return $count;
        });
    }

    public function createdMaterialNames(): array
    {
        return array_values(array_unique($this->createdMaterials));
    }

    private function baseMaterialName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $name = preg_replace('/\s*\(\s*\d+\s*head\s*\)\s*$/iu', '', $name);
        $name = preg_replace('/\s+\d+\s*head\s*$/iu', '', $name);

        return trim($name);
    }

    private function findMaterialForExpense(int $branchId, string $materialName, string $description): ?Material
    {
        $normalized = $this->normalizeMaterialName($materialName);
        $matches = Material::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->with('machine')
            ->get()
            ->filter(fn ($material) => $this->normalizeMaterialName($material->name) === $normalized);

        if ($matches->isEmpty()) {
            return null;
        }

        if (preg_match('/\b([24])\s*head\b/i', $description, $headMatch)) {
            $headCount = (int) $headMatch[1];
            $machineMatch = $matches->first(fn ($material) => (int) ($material->machine?->head_count ?? 0) === $headCount);
            if ($machineMatch) {
                return $machineMatch;
            }
        }

        return $matches->sortBy(fn ($material) => $material->machine_id === null ? 0 : 1)->first();
    }

    private function normalizeMaterialName(string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', $this->baseMaterialName($name)));
    }

    private function rows(string $text, int $columns): Collection
    {
        return collect(preg_split('/\R/', trim($text)))
            ->map(fn ($line) => array_pad(
                array_map(fn ($value) => trim(preg_replace('/\*+/', '', $value)), explode("\t", $line)),
                $columns,
                ''
            ));
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::createFromFormat('d/m/Y', trim($value)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function number(string $value): float
    {
        $value = preg_replace('/[^0-9,.-]/', '', $value);
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return (float) str_replace(',', '', $value);
    }

    private function money(string $value): float
    {
        return (float) preg_replace('/[^0-9-]/', '', $value);
    }

    private function category(string $value): string
    {
        return match (strtolower(trim($value))) {
            'pln', 'listrik' => 'Listrik',
            'lain-lain', 'lainnya' => 'Lainnya',
            default => in_array(trim($value), ['Bahan Baku', 'Operasional', 'Transport', 'ATK', 'Gaji', 'Internet'])
                ? trim($value)
                : 'Lainnya'
        };
    }
}
