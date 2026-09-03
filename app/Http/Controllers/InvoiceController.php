<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    use ResolvesBranch;

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        $invoices = Invoice::with('customer', 'orders')
            ->when($branchId !== 0, fn($q) => $q->where('branch_id', $branchId))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('invoice_number', 'like', "%{$s}%")->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_start, fn ($q, $s) => $q->whereDate('period_end', '>=', $s))
            ->when($request->date_end, fn ($q, $s) => $q->whereDate('period_start', '<=', $s))
            ->latest('date')
            ->paginate(15)
            ->withQueryString();

        return view('invoices.index', compact('invoices', 'branchId') + ['branches' => $this->branches()]);
    }

    public function create(Request $request)
    {
        $branchId = $this->branchId($request);
        $customerId = $request->customer_id;
        // Default to "this week so far" since invoicing runs on a weekly cadence per customer.
        $dateStart = $request->date_start ?: now()->startOfWeek()->toDateString();
        $dateEnd = $request->date_end ?: now()->toDateString();

        $orders = collect();
        if ($customerId) {
            $orders = Order::with('customer')
                ->where('branch_id', $branchId)
                ->where('customer_id', $customerId)
                ->whereNull('invoice_id')
                ->where('status', 'completed')
                ->whereBetween('date', [$dateStart, $dateEnd])
                ->orderBy('date')
                ->get();
        }

        return view('invoices.create', [
            'orders' => $orders,
            'branchId' => $branchId,
            'branches' => $this->branches(),
            'customers' => Customer::where('branch_id', $branchId)->where('active', true)->orderBy('name')->get(),
            'customerId' => $customerId,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ]);
    }

    public function store(Request $request)
    {
        $branchId = $this->branchId($request);

        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'discount' => 'nullable|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $branchId, &$invoice) {
            $orders = Order::where('branch_id', $branchId)
                ->where('customer_id', $data['customer_id'])
                ->whereNull('invoice_id')
                ->whereIn('id', $data['order_ids'])
                ->lockForUpdate()
                ->get();

            abort_unless(
                $orders->isNotEmpty() && $orders->count() === count($data['order_ids']),
                422,
                'Pilih pesanan yang belum ditagihkan dan milik customer yang sama.'
            );

            $subtotal = $orders->sum('total');
            $discount = $data['discount'] ?? 0;
            $total = max(0, $subtotal - $discount);
            $paid = min($data['paid'] ?? 0, $total);

            // subtotal/total/paid/remaining/status di bawah ini tetap disimpan
            // sebagai catatan historis (mis. untuk laporan), tapi index & print
            // TIDAK lagi membaca kolom ini — keduanya pakai accessor live_*
            // di Invoice model yang selalu menghitung ulang dari orders saat ini.
            $invoice = Invoice::create([
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'],
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'date' => now()->toDateString(),
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping' => 0,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $total - $paid,
                'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'draft'),
            ]);

            foreach ($orders as $order) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'order_id' => $order->id,
                    'product_id' => $order->product_id,
                    'description' => $order->product_name,
                    'size' => $order->size,
                    'qty' => $order->qty,
                    'price' => $order->price,
                    'subtotal' => $order->subtotal,
                ]);
                $order->update(['invoice_id' => $invoice->id]);
            }
        });

        return redirect()->route('invoices.index', ['branch_id' => $branchId])->with('message', 'Invoice berhasil dibuat.');
    }

    public function edit(Request $request, Invoice $invoice)
    {
        $this->guard($invoice);
        $invoice->load(['items.order', 'orders', 'customer']);

        $dateStart = $request->date_start ?: optional($invoice->period_start)->toDateString();
        $dateEnd = $request->date_end ?: optional($invoice->period_end)->toDateString();

        // Other unbilled, completed orders for this same customer that could
        // still be folded into this invoice (e.g. one was missed originally).
        $availableOrders = Order::where('branch_id', $invoice->branch_id)
            ->where('customer_id', $invoice->customer_id)
            ->whereNull('invoice_id')
            ->where('status', 'completed')
            ->when($dateStart && $dateEnd, fn ($q) => $q->whereBetween('date', [$dateStart, $dateEnd]))
            ->orderBy('date')
            ->get();

        return view('invoices.edit', [
            'invoice' => $invoice,
            'availableOrders' => $availableOrders,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        $this->guard($invoice);

        $data = $request->validate([
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'discount' => 'nullable|numeric|min:0',
            'paid' => 'nullable|numeric|min:0',
            'remove_item_ids' => 'nullable|array',
            'remove_item_ids.*' => 'exists:invoice_items,id',
            'add_order_ids' => 'nullable|array',
            'add_order_ids.*' => 'exists:orders,id',
        ]);

        DB::transaction(function () use ($data, $invoice) {
            if (! empty($data['remove_item_ids'])) {
                $items = $invoice->items()->whereIn('id', $data['remove_item_ids'])->get();
                foreach ($items as $item) {
                    if ($item->order_id) {
                        Order::where('id', $item->order_id)->update(['invoice_id' => null]);
                    }
                    $item->delete();
                }
            }

            if (! empty($data['add_order_ids'])) {
                $orders = Order::where('branch_id', $invoice->branch_id)
                    ->where('customer_id', $invoice->customer_id)
                    ->whereNull('invoice_id')
                    ->whereIn('id', $data['add_order_ids'])
                    ->lockForUpdate()
                    ->get();

                foreach ($orders as $order) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'order_id' => $order->id,
                        'product_id' => $order->product_id,
                        'description' => $order->product_name,
                        'size' => $order->size,
                        'qty' => $order->qty,
                        'price' => $order->price,
                        'subtotal' => $order->subtotal,
                    ]);
                    $order->update(['invoice_id' => $invoice->id]);
                }
            }

            $invoice->refresh();
            abort_if($invoice->items()->count() === 0, 422, 'Invoice harus memiliki minimal satu item.');

            // Kolom-kolom ini tetap di-update sebagai catatan/histori, tapi
            // tampilan (index & print) tidak bergantung lagi pada nilai ini.
            $subtotal = $invoice->items()->sum('subtotal');
            $discount = $data['discount'] ?? $invoice->discount;
            $total = max(0, $subtotal - $discount);
            $paid = min($data['paid'] ?? $invoice->paid, $total);

            $invoice->update([
                'period_start' => $data['period_start'] ?? $invoice->period_start,
                'period_end' => $data['period_end'] ?? $invoice->period_end,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $total - $paid,
                'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'draft'),
            ]);
        });

        return redirect()->route('invoices.index', ['branch_id' => $invoice->branch_id])->with('message', 'Invoice berhasil diperbarui.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->guard($invoice);
        DB::transaction(function () use ($invoice) {
            $invoice->orders()->update(['invoice_id' => null]);
            $invoice->delete();
        });

        return back()->with('message', 'Invoice berhasil dihapus.');
    }

    public function print(Invoice $invoice)
    {
        $this->guard($invoice);
        $invoice->load('customer', 'orders', 'branch');

        return view('invoices.print', compact('invoice'));
    }

    public function downloadPdf(Invoice $invoice)
    {
        $this->guard($invoice);
        $invoice->load('customer', 'orders', 'branch');

        $data = [
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'branch' => $invoice->branch,
        ];

        $pdf = Pdf::loadView('invoices.print', $data)->setPaper('a4', 'portrait');

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function bulkDestroy(Request $request)
    {
        $branchId = $this->branchId($request);
        $ids = $request->input('ids', []);
        $invoices = Invoice::where('branch_id', $branchId)->whereIn('id', $ids)->get();
        foreach ($invoices as $invoice) {
            $invoice->orders()->update(['invoice_id' => null]);
            $invoice->delete();
        }
        return back()->with('message', count($invoices).' invoice berhasil dihapus.');
    }

    private function guard(Invoice $invoice): void
    {
        abort_unless($this->canAccessAllBranches() || $invoice->branch_id === auth()->user()->branch_id, 403);
    }
}