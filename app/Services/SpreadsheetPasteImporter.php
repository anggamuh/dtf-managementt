<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

    public function __construct(MaterialPurchaseService $purchases)
    {
        $this->purchases = $purchases;
    }

    public function importIncome(string $text, int $branchId): int
    {
        $rows = $this->rows($text, 7)->filter(fn ($row) => $this->date($row[0] ?? null) && filled($row[1] ?? null) && filled($row[2] ?? null));
        if ($rows->isEmpty()) throw ValidationException::withMessages(['paste_data' => 'Tidak ditemukan baris pemasukan yang valid. Paste tabel Excel, termasuk kolom tanggal hingga keterangan.']);
        return DB::transaction(function () use ($rows, $branchId) {
            $count = 0;
            $batch = now()->format('YmdHis');
            $invoiceSeq = 0;
            $orderSeq = 0;
            $rows->groupBy(fn ($row) => trim($row[1]).'|'.$this->date($row[0])->format('o-W'))->each(function (Collection $group) use ($branchId, &$count, $batch, &$invoiceSeq, &$orderSeq) {
                $first = $group->first();
                $customer = Customer::firstOrCreate(['branch_id' => $branchId, 'name' => trim($first[1])], ['active' => true]);
                $items = $group->map(function ($row) use ($branchId, $customer) {
                    $date = $this->date($row[0]);
                    $qty = $this->number($row[3] ?? '0'); $price = $this->money($row[4] ?? '0'); $total = $this->money($row[5] ?? '0');
                    if ($qty <= 0 || $total <= 0) throw ValidationException::withMessages(['paste_data' => "Baris {$row[0]} / {$row[1]} memiliki qty atau total tidak valid."]);
                    $product = Product::firstOrCreate(['branch_id' => $branchId, 'name' => trim($row[2])], ['price' => $price, 'active' => true]);
                    return compact('qty', 'price', 'total', 'product', 'row', 'date');
                });
                $invoiceDate = $items->min('date');
                $periodStart = $items->min('date');
                $periodEnd = $items->max('date');
                $invoiceSeq++;
                $invoice = Invoice::create(['branch_id' => $branchId, 'customer_id' => $customer->id, 'invoice_number' => 'IMP-'.$batch.'-'.str_pad($invoiceSeq, 4, '0', STR_PAD_LEFT), 'date' => $invoiceDate, 'period_start' => $periodStart, 'period_end' => $periodEnd, 'subtotal' => $items->sum('total'), 'discount' => 0, 'shipping' => 0, 'total' => $items->sum('total'), 'paid' => $items->sum('total'), 'remaining' => 0, 'status' => 'paid']);
                foreach ($items as $item) { $orderSeq++; $order = Order::create(['branch_id'=>$branchId,'customer_id'=>$customer->id,'order_number'=>'IMP-ORD-'.$batch.'-'.str_pad($orderSeq, 4, '0', STR_PAD_LEFT),'date'=>$item['date'],'product_id'=>$item['product']->id,'product_name'=>$item['product']->name,'qty'=>$item['qty'],'price'=>$item['price'],'subtotal'=>$item['total'],'discount'=>0,'total'=>$item['total'],'status'=>'completed','note'=>trim($item['row'][6] ?? ''),'invoice_id'=>$invoice->id]); InvoiceItem::create(['invoice_id'=>$invoice->id,'product_id'=>$item['product']->id,'description'=>$item['product']->name,'qty'=>$item['qty'],'price'=>$item['price'],'subtotal'=>$item['total']]); }
                $count++;
            });
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
        if ($rows->isEmpty()) throw ValidationException::withMessages(['paste_data' => 'Tidak ditemukan baris pengeluaran yang valid.']);

        return DB::transaction(function () use ($rows, $branchId) {
            $count = 0;

            foreach ($rows as $row) {
                $category = $this->category($row[2] ?? '');
                $materialId = null;
                $quantity = null;

                // Untuk "Bahan Baku": cari atau buat material berdasarkan deskripsi
                if ($category === 'Bahan Baku') {
                    $materialName = trim($row[3]);
                    $material = Material::where('branch_id', $branchId)
                        ->where('name', $materialName)
                        ->first();

                    if (! $material) {
                        $material = Material::create([
                            'branch_id' => $branchId,
                            'name' => $materialName,
                            'unit' => 'kg',
                            'stock' => 0,
                            'minimum_stock' => 0,
                            'price' => 0,
                        ]);
                    }

                    $materialId = $material->id;
                    $quantity = $this->number($row[4] ?? '1');
                }

                $expense = Expense::create([
                    'branch_id' => $branchId,
                    'material_id' => $materialId,
                    'date' => $this->date($row[0]),
                    'category' => $category,
                    'description' => trim($row[3]),
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
        if (str_contains($value, ',') && !str_contains($value, '.')) {
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
            default => in_array(trim($value), ['Bahan Baku','Operasional','Transport','ATK','Gaji','Internet'])
                ? trim($value)
                : 'Lainnya'
        };
    }
}
