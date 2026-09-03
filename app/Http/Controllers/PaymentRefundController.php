<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRefundRequest;
use App\Models\Order;
use App\Notifications\PaymentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRefundController extends Controller
{
    public function store(StorePaymentRefundRequest $request, Order $order)
    {
        $this->authorize('update', $order);
        abort_unless($order->isCustomerCustom(), 404);

        DB::transaction(function () use ($request, $order) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($locked->payment_status, ['paid', 'refunded'], true), 422, 'Hanya pembayaran yang sudah lunas yang dapat direfund.');

            $refundedCents = $this->moneyInCents($locked->refunds()->where('status', 'completed')->sum('amount'));
            $amountCents = $this->moneyInCents($request->input('amount'));
            $totalCents = $this->moneyInCents($locked->total);
            abort_if($amountCents > $totalCents - $refundedCents, 422, 'Nominal refund melebihi sisa pembayaran.');

            $transaction = $locked->paymentTransactions()->where('status', 'paid')->latest('id')->first();
            $locked->refunds()->create([
                'payment_transaction_id' => $transaction?->id,
                'reference' => 'RFND-'.Str::uuid(),
                'provider_reference' => $request->input('provider_reference'),
                'amount' => $request->input('amount'),
                'reason' => $request->string('reason')->toString(),
                'status' => 'completed',
                'requested_by' => $request->user()->id,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);

            $isFullRefund = $refundedCents + $amountCents === $totalCents;
            if ($isFullRefund) {
                $changes = ['payment_status' => 'refunded'];
                if ($locked->status !== 'completed') {
                    $changes['status'] = 'cancelled';
                }
                $locked->update($changes);
            }

            $locked->user?->notify(new PaymentNotification(
                $isFullRefund ? 'Pembayaran dikembalikan' : 'Refund sebagian dicatat',
                'Refund Rp '.number_format($amountCents / 100, 0, ',', '.').' untuk pesanan '.$locked->order_number.' telah dicatat.',
                route('customer.orders.show', $locked),
                $locked->id,
            ));
        });

        return back()->with('message', 'Refund berhasil dicatat.');
    }

    private function moneyInCents(mixed $amount): int
    {
        abort_unless(is_numeric($amount), 422, 'Nominal refund tidak valid.');

        return (int) round((float) $amount * 100);
    }
}
