@extends('layouts.app')

@section('title', 'Sum Harian Pesanan')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#0F172A] dark:text-[#F8FAFC]">Sum Harian Pesanan</h1>
            <p class="mt-1 text-sm text-[#64748B] dark:text-[#94A3B8]">Pilih customer untuk melihat detail serta total Qty pesanan hariannya.</p>
        </div>
        <a href="{{ route('orders.weekly-closing', ['branch_id' => $branchId]) }}" class="rounded-xl border border-[#E2E8F0] bg-white px-4 py-2 text-sm font-semibold text-[#0F172A] shadow-sm transition hover:bg-[#F8FAFC] dark:border-[#253247] dark:bg-[#111827] dark:text-[#F8FAFC] dark:hover:bg-[#172033]">Closing Mingguan</a>
    </div>

    <form class="mt-5 flex flex-wrap items-end gap-3 rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Tanggal</label>
            <input type="date" name="date" value="{{ $date }}" class="rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm dark:border-[#253247] dark:bg-[#0B1220] dark:text-[#F8FAFC]">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#64748B] dark:text-[#94A3B8]">Customer</label>
            <select name="customer_id" required class="min-w-52 rounded-xl border border-[#E2E8F0] bg-white px-3 py-2 text-sm dark:border-[#253247] dark:bg-[#0B1220] dark:text-[#F8FAFC]">
                <option value="">Pilih customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected($customerId === $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-xl bg-[#2563EB] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#1D4ED8] dark:bg-[#3B82F6]">Tampilkan</button>
        <a href="{{ route('orders.daily-summary', ['branch_id' => $branchId, 'date' => \Carbon\Carbon::parse($date)->subDay()->toDateString()]) }}" class="rounded-xl border border-[#E2E8F0] px-4 py-2 text-sm font-medium text-[#64748B] hover:bg-[#F8FAFC] dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-[#172033]">← Previous Day</a>
        <a href="{{ route('orders.daily-summary', ['branch_id' => $branchId, 'date' => \Carbon\Carbon::parse($date)->addDay()->toDateString()]) }}" class="rounded-xl border border-[#E2E8F0] px-4 py-2 text-sm font-medium text-[#64748B] hover:bg-[#F8FAFC] dark:border-[#253247] dark:text-[#94A3B8] dark:hover:bg-[#172033]">Next Day →</a>
    </form>

    @if($selectedCustomer)
    <div class="mt-5 overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm dark:border-[#253247] dark:bg-[#111827]">
        <div class="border-b border-[#E2E8F0] px-5 py-4 dark:border-[#253247]"><p class="font-semibold text-[#0F172A] dark:text-[#F8FAFC]">{{ $selectedCustomer->name }} · {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-[#F8FAFC] text-left dark:bg-[#0B1220]"><tr><th class="p-4 text-[#64748B]">Nomor</th><th class="p-4 text-[#64748B]">Tanggal Pesanan</th><th class="p-4 text-[#64748B]">Customer</th><th class="p-4 text-[#64748B]">Produk</th><th class="p-4 text-right text-[#64748B]">Qty</th><th class="p-4 text-right text-[#64748B]">Total</th><th class="p-4 text-center text-[#64748B]">Status</th></tr></thead><tbody>
            @forelse($details as $order)
                <tr class="border-t border-[#E2E8F0] dark:border-[#253247]"><td class="p-4 font-medium text-[#0F172A] dark:text-[#F8FAFC]">{{ $order->order_number }}</td><td class="p-4 text-[#64748B] dark:text-[#94A3B8]">{{ $order->date->format('d/m/Y') }}</td><td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $order->customer->name }}</td><td class="p-4 text-[#0F172A] dark:text-[#F8FAFC]">{{ $order->product_name }}</td><td class="p-4 text-right text-[#0F172A] dark:text-[#F8FAFC]">{{ number_format($order->qty, 2, ',', '.') }}</td><td class="p-4 text-right font-semibold text-[#0F172A] dark:text-[#F8FAFC]">Rp {{ number_format($order->total, 0, ',', '.') }}</td><td class="p-4 text-center"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">{{ $order->status === 'completed' ? 'Selesai' : ucfirst($order->status) }}</span></td></tr>
            @empty
                <tr><td colspan="7" class="p-10 text-center text-[#94A3B8]">Tidak ada pesanan aktif untuk customer ini pada tanggal tersebut.</td></tr>
            @endforelse
        </tbody><tfoot class="border-t-2 border-[#0F172A] dark:border-[#F8FAFC]"><tr><td colspan="4" class="p-4 font-bold text-[#0F172A] dark:text-[#F8FAFC]">Total {{ $selectedCustomer->name }}</td><td class="p-4 text-right font-bold text-[#2563EB]">{{ number_format($grandQty, 2, ',', '.') }}</td><td class="p-4 text-right font-bold text-[#2563EB]">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td><td></td></tr></tfoot></table></div>
    </div>
    @else
        <div class="mt-5 rounded-2xl border border-dashed border-[#CBD5E1] bg-white p-10 text-center text-[#64748B] shadow-sm dark:border-[#334155] dark:bg-[#111827] dark:text-[#94A3B8]">Pilih customer, lalu tekan <strong>Tampilkan</strong>.</div>
    @endif
@endsection
