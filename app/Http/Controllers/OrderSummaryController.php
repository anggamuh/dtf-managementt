<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBranch;
use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderSummaryController extends Controller
{
    use ResolvesBranch;

    public function daily(Request $request)
    {
        $branchId = $this->branchId($request);
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $customerId = $request->integer('customer_id') ?: null;

        $customers = Customer::query()
            ->when($branchId !== 0, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $selectedCustomer = $customerId ? $customers->firstWhere('id', $customerId) : null;
        abort_if($customerId && ! $selectedCustomer, 403, 'Customer tidak dapat diakses pada cabang ini.');

        $details = Order::with('customer')
            ->when($branchId !== 0, fn ($query) => $query->where('branch_id', $branchId))
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->orderBy('order_number')
            ->get();

        return view('orders.daily-summary', [
            'branchId' => $branchId,
            'branches' => $this->branches(),
            'date' => $date,
            'customers' => $customers,
            'customerId' => $customerId,
            'selectedCustomer' => $selectedCustomer,
            'details' => $details,
            'grandQty' => $details->sum('qty'),
            'grandTotal' => $details->sum('total'),
        ]);
    }

    public function weekly(Request $request)
    {
        $branchId = $this->branchId($request);
        $startDate = Carbon::parse($request->input('start_date', now()->startOfWeek()->toDateString()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfWeek()->toDateString()))->toDateString();
        abort_if($endDate < $startDate, 422, 'Tanggal akhir harus sama atau setelah tanggal mulai.');

        $rows = Order::query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->when($branchId !== 0, fn ($query) => $query->where('orders.branch_id', $branchId))
            ->whereBetween('orders.date', [$startDate, $endDate])
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('orders.customer_id', 'customers.name', 'orders.date')
            ->orderBy('customers.name')
            ->orderBy('orders.date')
            ->get([
                'orders.customer_id',
                'customers.name as customer_name',
                'orders.date',
                DB::raw('SUM(orders.qty) as total_qty'),
                DB::raw('SUM(orders.total) as total'),
            ])
            ->groupBy('customer_id');

        return view('orders.weekly-closing', compact('branchId', 'startDate', 'endDate', 'rows') + [
            'branches' => $this->branches(),
        ]);
    }
}
