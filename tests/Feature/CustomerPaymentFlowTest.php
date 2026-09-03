<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentConfirmation;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['customer_order.unit_price' => 25000, 'services.midtrans.server_key' => 'SB-test', 'services.midtrans.client_key' => 'client']);
        $this->branch = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
        $this->customer = User::factory()->create();
        $this->customer->assignRole('Customer');
    }

    public function test_backend_calculates_fixed_price_and_ignores_manipulated_values_for_both_methods(): void
    {
        foreach (['payment_gateway', 'bank_transfer'] as $method) {
            $this->actingAs($this->customer)->post(route('customer.orders.store'), ['branch_id' => $this->branch->id, 'request_text' => 'x', 'size' => 'A3', 'quantity' => 5, 'payment_method' => $method, 'price' => 1, 'subtotal' => 1, 'total' => 1])->assertRedirect();
        }$orders = Order::latest()->take(2)->get();
        foreach ($orders as $o) {
            $this->assertSame('25000.00', $o->price);
            $this->assertSame('125000.00', $o->subtotal);
            $this->assertSame('125000.00', $o->total);
        }$this->actingAs($this->customer)->post(route('customer.orders.store'), ['branch_id' => $this->branch->id, 'request_text' => 'x', 'size' => 'A3', 'quantity' => 0, 'payment_method' => 'bank_transfer'])->assertSessionHasErrors('quantity');
    }

    public function test_unpaid_custom_order_is_hidden_from_production_until_paid(): void
    {
        $order = $this->order();
        $staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $staff->assignRole('Produksi');
        $this->actingAs($staff)->get(route('orders.index'))->assertDontSee($order->order_number);
        app(PaymentService::class)->markPaid($order, null, [], 'ok');
        $this->actingAs($staff)->get(route('orders.index'))->assertSee($order->order_number);
    }

    public function test_snap_is_created_backend_only_and_paid_order_cannot_pay_twice(): void
    {
        Http::fake(['*' => Http::response(['token' => 'snap-token'], 201)]);
        $order = $this->order(['payment_method' => 'payment_gateway', 'payment_gateway' => 'midtrans']);
        $this->actingAs($this->customer)->post(route('customer.orders.snap', $order))->assertOk()->assertJsonPath('token', 'snap-token');
        $order->update(['payment_status' => 'paid']);
        $this->actingAs($this->customer)->post(route('customer.orders.snap', $order))->assertStatus(422);
    }

    public function test_midtrans_webhook_verifies_signature_amount_and_is_idempotent(): void
    {
        $order = $this->order(['payment_method' => 'payment_gateway', 'payment_gateway' => 'midtrans']);
        $payload = ['order_id' => $order->payment_reference, 'status_code' => '200', 'gross_amount' => '25000.00', 'currency' => 'IDR', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1', 'fraud_status' => 'accept'];
        $payload['signature_key'] = $this->signature($payload);
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $count = $this->customer->fresh()->notifications()->count();
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame($count, $this->customer->fresh()->notifications()->count());
        $this->assertDatabaseHas('payment_transactions',['order_id'=>$order->id,'status'=>'paid','amount'=>25000]);
        $this->assertDatabaseHas('customer_invoices',['order_id'=>$order->id,'user_id'=>$this->customer->id,'total'=>25000]);
        $payload['signature_key'] = 'invalid';
        $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();
    }

    public function test_manual_proof_can_be_rejected_reuploaded_and_approved_only_by_correct_branch(): void
    {
        Storage::fake('local');
        $account = PaymentAccount::create(['branch_id' => $this->branch->id, 'bank_name' => 'BCA', 'account_number' => '1', 'account_holder' => 'DTF', 'is_active' => true]);
        $order = $this->order();
        $data = ['payment_account_id' => $account->id, 'sender_name' => 'Budi', 'sender_bank' => 'BRI', 'transferred_at' => now()->toDateString(), 'amount' => 25000, 'proof' => UploadedFile::fake()->image('proof.jpg')];
        $this->actingAs($this->customer)->post(route('customer.orders.payment-confirmation', $order), $data)->assertRedirect();
        $confirmation = $order->paymentConfirmations()->first();
        $this->assertSame('pending_verification', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'status' => 'pending_verification']);
        $other = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $wrong = User::factory()->create(['branch_id' => $other->id]);
        $wrong->assignRole('Admin RAPLY');
        $this->actingAs($wrong)->get(route('payment-confirmations.proof', $confirmation))->assertForbidden();
        $admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $admin->assignRole('Admin EPUL');
        $this->actingAs($admin)->patch(route('payment-confirmations.reject', $confirmation), ['rejection_reason' => 'Nominal tidak jelas'])->assertRedirect();
        $this->assertSame('rejected', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'status' => 'rejected']);
        $this->actingAs($this->customer)->post(route('customer.orders.payment-confirmation', $order), $data)->assertRedirect();
        $second = $order->paymentConfirmations()->latest()->first();
        $this->actingAs($admin)->patch(route('payment-confirmations.approve', $second))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('file_review', $order->fresh()->status);
        $this->assertCount(2, $order->paymentConfirmations);
    }

    public function test_customer_cannot_access_another_customers_payment_order(): void
    {
        $other = User::factory()->create();
        $other->assignRole('Customer');
        $this->actingAs($other)->get(route('customer.orders.show', $this->order()))->assertForbidden();
    }

    public function test_manual_transfer_amount_must_match_and_only_one_proof_can_be_pending(): void
    {
        Storage::fake('local');
        $account = PaymentAccount::create(['branch_id' => $this->branch->id, 'bank_name' => 'BCA', 'account_number' => '1', 'account_holder' => 'DTF', 'is_active' => true]);
        $order = $this->order();
        $base = ['payment_account_id' => $account->id, 'sender_name' => 'Budi', 'sender_bank' => 'BRI', 'transferred_at' => now()->toDateString()];

        $this->actingAs($this->customer)->post(route('customer.orders.payment-confirmation', $order), $base + ['amount' => 1, 'proof' => UploadedFile::fake()->image('wrong.jpg')])->assertStatus(422);
        $this->assertDatabaseCount('payment_confirmations', 0);

        $this->actingAs($this->customer)->post(route('customer.orders.payment-confirmation', $order), $base + ['amount' => 25000, 'proof' => UploadedFile::fake()->image('first.jpg')])->assertRedirect();
        $this->actingAs($this->customer)->post(route('customer.orders.payment-confirmation', $order), $base + ['amount' => 25000, 'proof' => UploadedFile::fake()->image('second.jpg')])->assertStatus(422);
        $this->assertDatabaseCount('payment_confirmations', 1);
        $this->actingAs($this->customer)->patch(route('customer.orders.cancel', $order))->assertStatus(422);
    }

    public function test_paid_payment_is_terminal_and_cannot_be_rejected_by_another_confirmation(): void
    {
        Storage::fake('local');
        $account = PaymentAccount::create(['branch_id' => $this->branch->id, 'bank_name' => 'BCA', 'account_number' => '1', 'account_holder' => 'DTF', 'is_active' => true]);
        $order = $this->order(['payment_status' => 'pending_verification']);
        $proofs = collect([1, 2])->map(fn ($id) => PaymentConfirmation::create(['order_id' => $order->id, 'payment_account_id' => $account->id, 'sender_name' => 'Budi', 'sender_bank' => 'BRI', 'transferred_at' => now(), 'amount' => 25000, 'proof_path' => "proof-{$id}.jpg", 'proof_original_name' => "proof-{$id}.jpg", 'status' => 'pending']));
        $admin = User::factory()->create(['branch_id' => $this->branch->id]);
        $admin->assignRole('Admin EPUL');

        $this->actingAs($admin)->patch(route('payment-confirmations.approve', $proofs[0]))->assertRedirect();
        $this->actingAs($admin)->patch(route('payment-confirmations.reject', $proofs[1]), ['rejection_reason' => 'Duplikat'])->assertStatus(422);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pending', $proofs[1]->fresh()->status);
    }

    public function test_webhook_fails_closed_and_requires_success_status_and_midtrans_order(): void
    {
        $order = $this->order(['payment_method' => 'payment_gateway', 'payment_gateway' => 'midtrans']);
        $payload = ['order_id' => $order->payment_reference, 'status_code' => '201', 'gross_amount' => '25000.00', 'currency' => 'IDR', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-not-success', 'fraud_status' => 'accept'];
        $payload['signature_key'] = $this->signature($payload);
        $this->postJson(route('webhooks.midtrans'), $payload)->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        config(['services.midtrans.server_key' => '']);
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount']);
        $this->postJson(route('webhooks.midtrans'), $payload)->assertForbidden();
    }

    public function test_unpaid_customer_order_cannot_enter_production_or_be_hard_deleted(): void
    {
        $order = $this->order();
        $staff = User::factory()->create(['branch_id' => $this->branch->id]);
        $staff->assignRole('Produksi');

        $this->actingAs($staff)->patch(route('orders.customer-status', $order), ['status' => 'processing'])->assertStatus(422);
        $this->actingAs($staff)->delete(route('orders.destroy', $order))->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'waiting_payment']);
    }

    public function test_design_preview_revision_approval_and_production_queue_workflow(): void
    {
        Storage::fake('local');
        $order=$this->order();
        app(PaymentService::class)->markPaid($order,null,[],'ok');
        $admin=User::factory()->create(['branch_id'=>$this->branch->id]);
        $admin->assignRole('Admin EPUL');

        $this->actingAs($admin)->get(route('orders.customer-detail',$order))->assertOk()->assertSee('Upload preview desain');
        $this->actingAs($admin)->post(route('orders.design-preview',$order),['preview'=>UploadedFile::fake()->image('preview-1.png'),'note'=>'Periksa ukuran'])->assertRedirect();
        $this->assertSame('awaiting_design_approval',$order->fresh()->status);
        $this->actingAs($this->customer)->get(route('customer.orders.show',$order))->assertOk()->assertSee('Setujui Desain');
        $this->actingAs($this->customer)->patch(route('customer.orders.design-request-revision',$order),['comment'=>'Logo diperbesar'])->assertRedirect();
        $this->assertSame('revision_required',$order->fresh()->status);
        $this->actingAs($this->customer)->post(route('customer.orders.design-revision',$order),['design_file'=>UploadedFile::fake()->image('revision.png'),'note'=>'Sudah diperbesar'])->assertRedirect();
        $this->assertSame('file_review',$order->fresh()->status);
        $this->actingAs($admin)->post(route('orders.design-preview',$order),['preview'=>UploadedFile::fake()->image('preview-2.png')])->assertRedirect();
        $this->actingAs($this->customer)->patch(route('customer.orders.design-approve',$order))->assertRedirect();
        $this->assertSame('production_queue',$order->fresh()->status);
        $this->assertSame(1,$order->fresh()->production_queue_number);
        $this->assertDatabaseCount('order_design_files',3);
        $this->assertDatabaseHas('order_design_reviews',['order_id'=>$order->id,'action'=>'approved','user_id'=>$this->customer->id]);
    }

    public function test_refund_is_branch_scoped_capped_and_full_refund_is_terminal(): void
    {
        $order = $this->order(['payment_method' => 'payment_gateway', 'payment_gateway' => 'midtrans']);
        app(PaymentService::class)->markPaid($order, 'trx-refund', [], 'ok');
        $finance = User::factory()->create(['branch_id' => $this->branch->id]);
        $finance->assignRole('Finance');
        $otherBranch = Branch::create(['name' => 'RAPLY', 'code' => 'RAPLY', 'active' => true]);
        $otherFinance = User::factory()->create(['branch_id' => $otherBranch->id]);
        $otherFinance->assignRole('Finance');
        $payload = ['reason' => 'Kelebihan pembayaran', 'confirmed' => '1'];

        $this->actingAs($otherFinance)->post(route('orders.refunds.store', $order), $payload + ['amount' => 10000])->assertForbidden();
        $this->actingAs($finance)->post(route('orders.refunds.store', $order), $payload + ['amount' => 10000, 'provider_reference' => 'BANK-1'])->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->actingAs($finance)->get(route('orders.customer-detail', $order))->assertOk()->assertSee('Catat Refund');
        $this->actingAs($this->customer)->get(route('customer.orders.show', $order))->assertOk()->assertSee('Riwayat refund');
        $this->actingAs($finance)->post(route('orders.refunds.store', $order), $payload + ['amount' => 16000])->assertStatus(422);
        $this->actingAs($finance)->post(route('orders.refunds.store', $order), $payload + ['amount' => 15000])->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseCount('payment_refunds', 2);
        $this->assertDatabaseHas('payment_refunds', ['order_id' => $order->id, 'provider_reference' => 'BANK-1', 'status' => 'completed']);

        $callback = ['order_id' => $order->payment_reference, 'status_code' => '200', 'gross_amount' => '25000.00', 'currency' => 'IDR', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-refund', 'fraud_status' => 'accept'];
        $callback['signature_key'] = $this->signature($callback);
        $this->postJson(route('webhooks.midtrans'), $callback)->assertOk();
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_expired_payment_command_is_idempotent_and_does_not_expire_active_verification(): void
    {
        $expired = $this->order(['payment_expired_at' => now()->subMinute()]);
        $verifying = $this->order(['payment_status' => 'pending_verification', 'payment_expired_at' => now()->subMinute()]);

        Artisan::call('customer-orders:expire-payments');
        $this->assertSame('expired', $expired->fresh()->payment_status);
        $this->assertSame('pending_verification', $verifying->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $expired->id, 'status' => 'expired']);
        $notificationCount = $this->customer->fresh()->notifications()->count();

        Artisan::call('customer-orders:expire-payments');
        $this->assertSame($notificationCount, $this->customer->fresh()->notifications()->count());
    }

    private function order(array $extra = []): Order
    {
        return Order::create(array_merge(['branch_id' => $this->branch->id, 'user_id' => $this->customer->id, 'order_type' => 'customer_custom', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'payment_reference' => uniqid('PAY-'), 'payment_expired_at' => now()->addDay(), 'order_number' => uniqid('ORD-'), 'date' => now(), 'product_name' => 'Pesanan Custom', 'size' => 'A3', 'qty' => 1, 'price' => 25000, 'subtotal' => 25000, 'discount' => 0, 'total' => 25000, 'status' => 'waiting_payment'], $extra));
    }

    private function signature(array $p): string
    {
        return hash('sha512',$p['order_id'].$p['status_code'].$p['gross_amount'].config('services.midtrans.server_key'));
    }
}
