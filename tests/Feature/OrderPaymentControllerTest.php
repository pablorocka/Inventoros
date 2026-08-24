<?php

namespace Tests\Feature;

use App\Models\Auth\Organization;
use App\Models\Order\Order;
use App\Models\Order\OrderPayment;
use App\Models\Role;
use App\Models\System\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $otherOrgUser;
    protected Organization $organization;
    protected Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installed', true, 'boolean');

        $this->organization = Organization::create([
            'name' => 'Test Organization',
            'email' => 'test@organization.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $this->otherOrganization = Organization::create([
            'name' => 'Other Organization',
            'email' => 'other@organization.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);

        $this->otherOrgUser = User::create([
            'name' => 'Other Org User',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $this->otherOrganization->id,
            'role' => 'admin',
        ]);

        $adminRole = Role::firstOrCreate(
            ['slug' => 'system-administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Full system access',
                'is_system' => true,
                'permissions' => [
                    'view_orders',
                    'create_orders',
                    'edit_orders',
                    'delete_orders',
                    'manage_order_payments',
                ],
            ]
        );

        $this->admin->roles()->syncWithoutDetaching([$adminRole->id]);
        $this->otherOrgUser->roles()->syncWithoutDetaching([$adminRole->id]);
    }

    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'organization_id' => $this->organization->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'source' => 'manual',
            'customer_name' => 'Test Customer',
            'status' => 'pending',
            'subtotal' => 100.00,
            'tax' => 0,
            'shipping' => 0,
            'total' => 100.00,
            'currency' => 'USD',
            'order_date' => now(),
        ], $attributes));
    }

    protected function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 50.00,
            'method' => 'cash',
            'reference' => null,
            'paid_at' => now()->format('Y-m-d'),
            'notes' => null,
        ], $overrides);
    }

    // ==================== PARTIAL PAYMENTS ====================

    public function test_partial_payment_sets_partial_status_and_accumulates(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 40]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(40.00, (float) $order->amount_paid);
        $this->assertEquals('partial', $order->payment_status);
        $this->assertEquals(60.00, $order->balance_due);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'amount' => 40,
            'method' => 'cash',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_payments_summing_total_set_paid_status(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 60]));
        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 40, 'method' => 'transfer', 'reference' => 'TX-123']));

        $order->refresh();
        $this->assertEquals(100.00, (float) $order->amount_paid);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals(0.00, $order->balance_due);
        $this->assertCount(2, $order->payments);
    }

    // ==================== VALIDATIONS ====================

    public function test_payment_exceeding_balance_is_rejected(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 70]));

        $response = $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 50]));

        $response->assertSessionHasErrors('amount');

        $order->refresh();
        $this->assertEquals(70.00, (float) $order->amount_paid);
        $this->assertEquals('partial', $order->payment_status);
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $order = $this->createOrder();

        foreach ([0, -10] as $amount) {
            $response = $this->actingAs($this->admin)
                ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => $amount]));

            $response->assertSessionHasErrors('amount');
        }

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->amount_paid);
        $this->assertEquals('pending', $order->payment_status);
    }

    public function test_invalid_method_is_rejected(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['method' => 'bitcoin']));

        $response->assertSessionHasErrors('method');
    }

    public function test_payment_on_cancelled_order_is_rejected(): void
    {
        $order = $this->createOrder(['status' => 'cancelled']);

        $response = $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload());

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_payment_on_fully_paid_order_is_rejected(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 100]));

        $response = $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 10]));

        $response->assertSessionHasErrors('amount');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertCount(1, $order->payments);
    }

    // ==================== DELETE PAYMENT ====================

    public function test_deleting_payment_recalculates_balance_and_status(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 100]));

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        $payment = $order->payments()->first();

        $response = $this->actingAs($this->admin)
            ->delete(route('orders.payments.destroy', [$order, $payment]));

        $response->assertRedirect();

        $order->refresh();
        $this->assertEquals(0.00, (float) $order->amount_paid);
        $this->assertEquals('pending', $order->payment_status);
        $this->assertSoftDeleted('order_payments', ['id' => $payment->id]);
    }

    // ==================== AUTHORIZATION ====================

    public function test_user_from_other_organization_cannot_register_payment(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->otherOrgUser)
            ->post(route('orders.payments.store', $order), $this->paymentPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('order_payments', 0);
    }

    public function test_user_from_other_organization_cannot_delete_payment(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 30]));

        $payment = $order->payments()->first();

        $response = $this->actingAs($this->otherOrgUser)
            ->delete(route('orders.payments.destroy', [$order, $payment]));

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('order_payments', ['id' => $payment->id]);
    }

    public function test_payment_must_belong_to_order(): void
    {
        $orderA = $this->createOrder();
        $orderB = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $orderA), $this->paymentPayload(['amount' => 30]));

        $payment = $orderA->payments()->first();

        $response = $this->actingAs($this->admin)
            ->delete(route('orders.payments.destroy', [$orderB, $payment]));

        $response->assertStatus(404);
        $this->assertNotSoftDeleted('order_payments', ['id' => $payment->id]);
    }

    // ==================== SHOW PAGE ====================

    public function test_order_show_includes_payment_history(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin)
            ->post(route('orders.payments.store', $order), $this->paymentPayload(['amount' => 25, 'method' => 'card']));

        $response = $this->actingAs($this->admin)
            ->get(route('orders.show', $order));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->has('order.payments', 1)
            ->where('order.payment_status', 'partial')
            ->where('canManagePayments', true)
        );
    }
}
