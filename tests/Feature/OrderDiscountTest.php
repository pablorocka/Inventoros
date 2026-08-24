<?php

namespace Tests\Feature;

use App\Models\Auth\Organization;
use App\Models\Inventory\Product;
use App\Models\Order\Order;
use App\Models\Role;
use App\Models\System\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Organization $organization;
    protected Product $product;

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

        $this->product = Product::create([
            'organization_id' => $this->organization->id,
            'sku' => 'TEST-PROD-001',
            'name' => 'Test Product',
            'price' => 100.00,
            'currency' => 'USD',
            'stock' => 100,
            'min_stock' => 10,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $this->organization->id,
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
    }

    protected function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Discount Customer',
            'status' => 'pending',
            'order_date' => now()->format('Y-m-d'),
            'shipping' => 0,
            'tax' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                ],
            ],
        ], $overrides);
    }

    // ==================== CREATE WITH DISCOUNT ====================

    public function test_order_with_percent_discount_computes_total(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]));

        $response->assertRedirect(route('orders.index'));

        $order = Order::where('customer_name', 'Discount Customer')->first();
        $this->assertNotNull($order);
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals('percent', $order->discount_type);
        $this->assertEquals(10.00, (float) $order->discount_value);
        $this->assertEquals(20.00, (float) $order->discount_amount);
        $this->assertEquals(180.00, (float) $order->total);
    }

    public function test_order_with_fixed_discount_computes_total(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'fixed',
            'discount_value' => 35.50,
            'tax' => 10.00,
            'shipping' => 5.00,
        ]));

        $response->assertRedirect(route('orders.index'));

        $order = Order::where('customer_name', 'Discount Customer')->first();
        $this->assertEquals(35.50, (float) $order->discount_amount);
        // 200 - 35.50 + 10 + 5
        $this->assertEquals(179.50, (float) $order->total);
    }

    public function test_order_without_discount_is_unchanged(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'tax' => 10.00,
            'shipping' => 5.00,
        ]));

        $response->assertRedirect(route('orders.index'));

        $order = Order::where('customer_name', 'Discount Customer')->first();
        $this->assertNull($order->discount_type);
        $this->assertEquals(0.00, (float) $order->discount_amount);
        $this->assertEquals(215.00, (float) $order->total);
    }

    // ==================== VALIDATIONS ====================

    public function test_percent_discount_over_100_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 150,
        ]));

        $response->assertSessionHasErrors('discount_value');
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Discount Customer']);
    }

    public function test_fixed_discount_exceeding_subtotal_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'fixed',
            'discount_value' => 250.00,
        ]));

        $response->assertSessionHasErrors('discount_value');
        $this->assertDatabaseMissing('orders', ['customer_name' => 'Discount Customer']);
    }

    public function test_invalid_discount_type_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'coupon',
            'discount_value' => 10,
        ]));

        $response->assertSessionHasErrors('discount_type');
    }

    public function test_negative_discount_value_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'fixed',
            'discount_value' => -5,
        ]));

        $response->assertSessionHasErrors('discount_value');
    }

    // ==================== UPDATE WITH DISCOUNT ====================

    public function test_discount_can_be_added_on_update(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload());
        $order = Order::where('customer_name', 'Discount Customer')->first();

        $response = $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 25,
            'items' => [
                [
                    'id' => $order->items->first()->id,
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                ],
            ],
        ]));

        $response->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertEquals(50.00, (float) $order->discount_amount);
        $this->assertEquals(150.00, (float) $order->total);
    }

    // ==================== DISCOUNT x PAYMENTS INTERACTION ====================

    public function test_update_cannot_reduce_total_below_amount_paid(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload());
        $order = Order::where('customer_name', 'Discount Customer')->first();

        // Pay 180 of the 200 total
        $this->actingAs($this->admin)->post(route('orders.payments.store', $order), [
            'amount' => 180.00,
            'method' => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ]);

        // Try to apply a 25% discount (new total 150 < 180 paid)
        $response = $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 25,
            'items' => [
                [
                    'id' => $order->items->first()->id,
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                ],
            ],
        ]));

        $response->assertSessionHasErrors('total');

        $order->refresh();
        $this->assertEquals(200.00, (float) $order->total);
        $this->assertEquals('partial', $order->payment_status);
    }

    public function test_discount_lowering_total_to_amount_paid_marks_order_paid(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload());
        $order = Order::where('customer_name', 'Discount Customer')->first();

        // Pay 150 of the 200 total
        $this->actingAs($this->admin)->post(route('orders.payments.store', $order), [
            'amount' => 150.00,
            'method' => 'cash',
            'paid_at' => now()->format('Y-m-d'),
        ]);

        $order->refresh();
        $this->assertEquals('partial', $order->payment_status);

        // Apply 25% discount => new total 150 == paid
        $response = $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 25,
            'items' => [
                [
                    'id' => $order->items->first()->id,
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 100.00,
                ],
            ],
        ]));

        $response->assertRedirect(route('orders.index'));

        $order->refresh();
        $this->assertEquals(150.00, (float) $order->total);
        $this->assertEquals('paid', $order->payment_status);
    }

    // ==================== SHOW ====================

    public function test_order_show_exposes_discount_fields(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]));
        $order = Order::where('customer_name', 'Discount Customer')->first();

        $response = $this->actingAs($this->admin)->get(route('orders.show', $order));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->where('order.discount_type', 'percent')
            ->where('order.discount_amount', '20.00')
        );
    }
}
