<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PaymentNotification;
use App\Services\PaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_redirects_by_role_and_separates_dashboards(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect(route('customer.dashboard'));
        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->actingAs($customer)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('customer.dashboard'))->assertForbidden();
    }

    public function test_login_does_not_reuse_an_intended_url_from_another_role_area(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');

        $this->withSession(['url.intended' => route('dashboard')])
            ->post('/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_registration_always_assigns_customer_role(): void
    {
        $this->post(route('register.store'), ['name' => 'C', 'whatsapp' => '0812', 'email' => 'c@test.dev', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'Super Admin'])->assertRedirect(route('customer.dashboard'));
        $this->assertTrue(User::whereEmail('c@test.dev')->first()->hasRole('Customer'));
    }

    public function test_order_accepts_description_or_png_and_rejects_missing_or_invalid_input(): void
    {
        Storage::fake('local');
        $branch = Branch::create(['name' => 'EPUL', 'code' => 'EPUL', 'active' => true]);
        $user = User::factory()->create();
        $user->assignRole('Customer');
        $base = ['branch_id' => $branch->id, 'size' => 'A3', 'quantity' => 1, 'payment_method' => 'bank_transfer'];
        $this->actingAs($user)->post(route('customer.orders.store'), $base + ['request_text' => 'Desain merah'])->assertRedirect();
        $this->actingAs($user)->post(route('customer.orders.store'), $base + ['design_file' => UploadedFile::fake()->image('design.png')])->assertRedirect();
        $this->actingAs($user)->post(route('customer.orders.store'), $base)->assertSessionHasErrors('request_text');
        $this->actingAs($user)->post(route('customer.orders.store'), $base + ['design_file' => UploadedFile::fake()->create('x.jpg', 10, 'image/jpeg')])->assertSessionHasErrors('design_file');
        $this->actingAs($user)->post(route('customer.orders.store'), $base + ['design_file' => UploadedFile::fake()->create('x.png', 10241, 'image/png')])->assertSessionHasErrors('design_file');
    }

    public function test_customer_and_employee_are_scoped_and_design_download_is_authorized(): void
    {
        Storage::fake('local');
        $a = Branch::create(['name' => 'A', 'code' => 'A', 'active' => true]);
        $b = Branch::create(['name' => 'B', 'code' => 'B', 'active' => true]);
        $owner = User::factory()->create();
        $owner->assignRole('Customer');
        $other = User::factory()->create();
        $other->assignRole('Customer');
        $employee = User::factory()->create(['branch_id' => $b->id]);
        $employee->assignRole('Produksi');
        Storage::disk('local')->put('customer-designs/a.png', 'png');
        $order = $this->order($owner, $a, ['design_file_path' => 'customer-designs/a.png', 'design_file_original_name' => 'a.png']);
        $this->actingAs($other)->get(route('customer.orders.show', $order))->assertForbidden();
        $this->actingAs($other)->get(route('customer.orders.design', $order))->assertForbidden();
        $this->actingAs($employee)->get(route('orders.customer-detail', $order))->assertForbidden();
    }

    public function test_notifications_only_reach_destination_branch_and_status_reaches_customer(): void
    {
        $a = Branch::create(['name' => 'A', 'code' => 'A', 'active' => true]);
        $b = Branch::create(['name' => 'B', 'code' => 'B', 'active' => true]);
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $ea = User::factory()->create(['branch_id' => $a->id]);
        $ea->assignRole('Produksi');
        $eb = User::factory()->create(['branch_id' => $b->id]);
        $eb->assignRole('Produksi');
        $order = $this->order($customer, $a);
        $this->assertCount(0, $ea->fresh()->notifications);
        app(PaymentService::class)->markPaid($order, null, [], 'Berhasil');
        $this->assertCount(1, $ea->fresh()->notifications);
        $this->assertCount(0, $eb->fresh()->notifications);
        $before = $customer->fresh()->notifications()->count();
        $order->update(['status' => 'processing']);
        $this->assertGreaterThan($before, $customer->fresh()->notifications()->count());
    }

    public function test_legacy_order_can_still_be_created(): void
    {
        $a = Branch::create(['name' => 'A', 'code' => 'A', 'active' => true]);
        $this->assertNotNull(Order::create(['branch_id' => $a->id, 'order_number' => 'LEGACY', 'date' => now(), 'product_name' => 'DTF', 'qty' => 1, 'price' => 1, 'subtotal' => 1, 'discount' => 0, 'total' => 1, 'status' => 'waiting']));
    }

    public function test_admin_dashboard_renders_customer_custom_order_without_legacy_customer_relation(): void
    {
        $branch = Branch::create(['name' => 'A', 'code' => 'A', 'active' => true]);
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $this->order($customer, $branch);
        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole('Produksi');
        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertDontSee($customer->name);
    }

    public function test_notification_visit_is_scoped_marks_read_and_rejects_unsafe_urls(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $other = User::factory()->create();
        $other->assignRole('Customer');
        $customer->notify(new PaymentNotification('Unsafe', 'Unsafe URL', 'javascript:alert(1)'));
        $notification = $customer->notifications()->firstOrFail();

        $this->actingAs($other)->get(route('notifications.visit', $notification->id))->assertNotFound();
        $this->actingAs($customer)->get(route('notifications.visit', $notification->id))->assertRedirect(route('customer.dashboard'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function order(User $user, Branch $branch, array $extra = []): Order
    {
        return Order::create(array_merge(['branch_id' => $branch->id, 'user_id' => $user->id, 'order_type' => 'customer_custom', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'payment_reference' => uniqid('PAY-'), 'order_number' => uniqid('ORD-'), 'date' => now(), 'product_name' => 'Pesanan Custom', 'size' => 'A3', 'qty' => 1, 'price' => 25000, 'subtotal' => 25000, 'discount' => 0, 'total' => 25000, 'status' => 'waiting_payment'], $extra));
    }
}
