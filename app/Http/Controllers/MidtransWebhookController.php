<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransService $midtrans, PaymentService $payments)
    {
        $p = $request->all();
        abort_unless($midtrans->validSignature($p), 403, 'Invalid signature');
        $order = Order::where('payment_reference', $p['order_id'] ?? '')->firstOrFail();
        abort_unless($order->isCustomerCustom() && $order->payment_method === 'payment_gateway' && $order->payment_gateway === 'midtrans', 422, 'Invalid payment provider');
        abort_unless(($p['currency'] ?? 'IDR') === 'IDR' && $this->moneyInCents($p['gross_amount'] ?? null) === $this->moneyInCents($order->total), 422, 'Payment mismatch');
        $status = $p['transaction_status'] ?? '';
        $audit = Arr::only($p, ['order_id', 'status_code', 'gross_amount', 'transaction_status', 'transaction_id', 'payment_type', 'fraud_status', 'currency', 'transaction_time', 'settlement_time']);
        $successful = in_array($status, ['settlement', 'capture'], true)
            && (string) ($p['status_code'] ?? '') === '200'
            && (! array_key_exists('fraud_status', $p) || $p['fraud_status'] === 'accept')
            && filled($p['transaction_id'] ?? null);
        if ($successful) {
            $payments->markPaid($order, (string) $p['transaction_id'], $audit, "Pembayaran pesanan {$order->order_number} berhasil.");
        } elseif (! in_array($order->payment_status, ['paid', 'refunded'], true)) {
            DB::transaction(function () use ($order, $status, $audit, $p, $payments) {
                $locked = Order::lockForUpdate()->findOrFail($order->id);
                if (in_array($locked->payment_status, ['paid', 'refunded'], true)) {
                    return;
                }$mapped = match ($status) {
                    'pending' => 'pending','expire' => 'expired','deny','cancel','failure' => 'failed',default => $locked->payment_status
                };
                $locked->update(['payment_status' => $mapped, 'gateway_transaction_id' => $p['transaction_id'] ?? $locked->gateway_transaction_id, 'gateway_payload' => $audit]);
                $payments->recordTransaction($locked, $mapped, $p['transaction_id'] ?? null, $audit);
            });
        }

        return response()->json(['received' => true]);
    }

    private function moneyInCents(mixed $amount): int
    {
        abort_unless(is_numeric($amount), 422, 'Invalid payment amount');

        return (int) round((float) $amount * 100);
    }
}
