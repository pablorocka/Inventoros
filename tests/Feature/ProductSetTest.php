<?php

namespace Tests\Feature;

use App\Models\Auth\Organization;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductLocation;
use App\Models\Inventory\ProductSet;
use App\Models\Order\Order;
use App\Models\System\SystemSetting;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Organization $organization;
    protected ProductLocation $store;
    protected ProductLocation $house;

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

        $this->store = ProductLocation::create(['organization_id' => $this->organization->id, 'name' => 'Store', 'code' => 'TI']);
        $this->house = ProductLocation::create(['organization_id' => $this->organization->id, 'name' => 'House', 'code' => 'MA']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $this->organization->id,
            'role' => 'admin',
        ]);
    }

    /**
     * Create a product once per location, as this inventory does.
     *
     * @return array{0: Product, 1: Product} store instance, house instance
     */
    private function duplicated(string $name, int $storeStock, int $houseStock, float $price = 10): array
    {
        $make = fn (ProductLocation $location, int $stock) => Product::create([
            'organization_id' => $this->organization->id,
            'sku' => strtoupper(substr(md5($name), 0, 8)) . '-' . $location->code,
            'name' => $name,
            'price' => $price,
            'currency' => 'USD',
            'stock' => $stock,
            'min_stock' => 0,
            'is_active' => true,
            'location_id' => $location->id,
        ]);

        return [$make($this->store, $storeStock), $make($this->house, $houseStock)];
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $items  product instance and units per set
     */
    private function createSet(array $items, string $name = 'Routine'): ProductSet
    {
        $this->actingAs($this->admin)->post(route('sets.store'), [
            'name' => $name,
            'description' => 'Short',
            'instructions' => '<p><strong>Use</strong> daily</p>',
            'items' => array_map(fn ($item) => ['product_id' => $item[0]->id, 'quantity' => $item[1]], $items),
        ])->assertSessionHasNoErrors();

        return ProductSet::where('name', $name)->with('items.activeProducts')->firstOrFail();
    }

    private function orderPayload(array $sets, array $items = []): array
    {
        return [
            'customer_name' => 'Customer',
            'status' => 'pending',
            'order_date' => '2026-10-30',
            'items' => $items,
            'sets' => $sets,
        ];
    }

    private function selection(ProductSet $set, Product ...$products): array
    {
        return collect($products)->map(function (Product $product) use ($set) {
            $item = $set->items->first(fn ($item) => $item->activeProducts->contains('id', $product->id));

            return ['set_item_id' => $item->id, 'product_id' => $product->id];
        })->all();
    }

    public function test_a_set_item_links_every_instance_of_the_product(): void
    {
        [$store, $house] = $this->duplicated('Serum', 5, 5);
        $this->duplicated('Other', 5, 5);

        $set = $this->createSet([[$store, 2]]);

        $this->assertCount(1, $set->items);
        $this->assertSame(2, $set->items[0]->quantity);
        $this->assertEqualsCanonicalizing([$store->id, $house->id], $set->items[0]->activeProducts->pluck('id')->all());
    }

    public function test_product_options_are_unique_by_name_and_know_every_instance(): void
    {
        [$store, $house] = $this->duplicated('Serum', 2, 3);

        $options = app(\App\Services\ProductSetService::class)->productOptions($this->organization->id);

        $this->assertCount(1, $options);
        $this->assertSame(5, $options[0]['stock']);
        $this->assertEqualsCanonicalizing([$store->id, $house->id], $options[0]['instance_ids']->all());
    }

    public function test_the_same_product_cannot_be_added_twice_through_its_other_instance(): void
    {
        [$store, $house] = $this->duplicated('Serum', 5, 5);

        $this->actingAs($this->admin)->post(route('sets.store'), [
            'name' => 'Dup',
            'items' => [['product_id' => $store->id, 'quantity' => 1], ['product_id' => $house->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.1.product_id');

        $this->assertDatabaseMissing('product_sets', ['name' => 'Dup']);
    }

    public function test_available_quantity_is_the_minimum_across_products_regardless_of_location(): void
    {
        [$a] = $this->duplicated('A', 4, 6);   // 10
        [$b] = $this->duplicated('B', 6, 0);   // 6
        [$c] = $this->duplicated('C', 7, 7);   // 14

        $this->assertSame(6, $this->createSet([[$a, 1], [$b, 1], [$c, 1]], 'One each')->availableQuantity());
        $this->assertSame(3, $this->createSet([[$a, 1], [$b, 2], [$c, 1]], 'Two of B')->availableQuantity());
    }

    public function test_inactive_and_deleted_instances_do_not_count_as_available(): void
    {
        [$store, $house] = $this->duplicated('A', 4, 6);
        $set = $this->createSet([[$store, 1]]);

        $house->update(['is_active' => false]);
        $this->assertSame(4, $set->fresh('items.activeProducts')->availableQuantity());

        $store->delete();
        $this->assertSame(0, $set->fresh('items.activeProducts')->availableQuantity());
    }

    public function test_ordering_a_set_takes_stock_from_the_selected_instances(): void
    {
        [$aStore, $aHouse] = $this->duplicated('A', 10, 10, 5);
        [$bStore, $bHouse] = $this->duplicated('B', 10, 10, 7);
        $set = $this->createSet([[$aStore, 2], [$bStore, 1]]);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id,
            'quantity' => 3,
            'selections' => $this->selection($set, $aHouse, $bStore),
        ]]))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(10, $aStore->fresh()->stock);
        $this->assertSame(4, $aHouse->fresh()->stock);
        $this->assertSame(7, $bStore->fresh()->stock);
        $this->assertSame(10, $bHouse->fresh()->stock);

        $order = Order::with('items')->firstOrFail();
        $this->assertEquals(6 * 5 + 3 * 7, (float) $order->total);
        $this->assertCount(2, $order->items);

        $item = $order->items->firstWhere('product_id', $aHouse->id);
        $this->assertSame(6, $item->quantity);
        $this->assertSame('Routine', $item->metadata['set']['name']);
        $this->assertSame(3, $item->metadata['set']['quantity']);
        $this->assertSame('House', $item->metadata['location']);
    }

    public function test_the_server_uses_product_prices_and_rejects_foreign_or_missing_selections(): void
    {
        [$aStore] = $this->duplicated('A', 10, 10);
        [$bStore] = $this->duplicated('B', 10, 10);
        [$other] = $this->duplicated('Not in set', 10, 10);
        $set = $this->createSet([[$aStore, 1], [$bStore, 1]]);
        $itemId = $set->items->first()->id;

        // Missing selection for the second product
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 1,
            'selections' => [['set_item_id' => $itemId, 'product_id' => $aStore->id]],
        ]]))->assertSessionHas('error');

        // Product that is not part of the set
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 1,
            'selections' => [
                ['set_item_id' => $itemId, 'product_id' => $other->id],
                ['set_item_id' => $set->items->last()->id, 'product_id' => $bStore->id],
            ],
        ]]))->assertSessionHas('error');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $aStore->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_the_whole_order(): void
    {
        [$aStore] = $this->duplicated('A', 10, 0);
        [$bStore] = $this->duplicated('B', 1, 0);
        $set = $this->createSet([[$aStore, 1], [$bStore, 1]]);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 2,
            'selections' => $this->selection($set, $aStore, $bStore),
        ]]))->assertSessionHas('error');

        $this->assertSame(10, $aStore->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_stock_is_checked_across_a_product_added_individually_and_in_a_set(): void
    {
        [$aStore] = $this->duplicated('A', 2, 0);
        $set = $this->createSet([[$aStore, 2]]);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload(
            [['product_set_id' => $set->id, 'quantity' => 1, 'selections' => $this->selection($set, $aStore)]],
            [['product_id' => $aStore->id, 'quantity' => 1, 'unit_price' => 10]],
        ))->assertSessionHas('error');

        $this->assertSame(2, $aStore->fresh()->stock);
    }

    public function test_an_order_needs_a_product_or_a_set(): void
    {
        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([]))
            ->assertSessionHasErrors('items');
    }

    public function test_editing_resizes_removes_and_adds_set_lines(): void
    {
        [$aStore, $aHouse] = $this->duplicated('A', 10, 10, 5);
        $set = $this->createSet([[$aStore, 2]]);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 2,
            'selections' => $this->selection($set, $aStore),
        ]]));

        $order = Order::with('items')->firstOrFail();
        $lineId = $order->items[0]->metadata['set']['line_id'];
        $this->assertSame(6, $aStore->fresh()->stock);

        // Grow the saved line up to all stock it can reach (6 left + 4 held = 5 sets)
        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            ['line_id' => $lineId, 'quantity' => 5],
        ]))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(0, $aStore->fresh()->stock);
        $this->assertEquals(50, (float) $order->fresh()->total);
        $this->assertSame(5, $order->items()->first()->metadata['set']['quantity']);

        // Beyond stock: nothing changes
        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            ['line_id' => $lineId, 'quantity' => 6],
        ]))->assertSessionHas('error');
        $this->assertSame(0, $aStore->fresh()->stock);

        // Replace the line with one from the other location: stock moves back
        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 1,
            'selections' => $this->selection($set, $aHouse),
        ]]))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(10, $aStore->fresh()->stock);
        $this->assertSame(8, $aHouse->fresh()->stock);
        $this->assertCount(1, $order->items()->get());

        // Deleting the order gives the stock back
        $this->actingAs($this->admin)->delete(route('orders.destroy', $order->fresh()));
        $this->assertSame(10, $aHouse->fresh()->stock);
    }

    public function test_saved_lines_survive_changes_to_the_set(): void
    {
        [$aStore] = $this->duplicated('A', 10, 0, 5);
        $set = $this->createSet([[$aStore, 1]]);

        $this->actingAs($this->admin)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 1,
            'selections' => $this->selection($set, $aStore),
        ]]));

        $order = Order::with('items')->firstOrFail();
        $set->delete();

        $this->actingAs($this->admin)->put(route('orders.update', $order), $this->orderPayload([
            ['line_id' => $order->items[0]->metadata['set']['line_id'], 'quantity' => 2],
        ]))->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame(8, $aStore->fresh()->stock);
    }

    public function test_sets_of_other_organizations_are_out_of_reach(): void
    {
        [$a] = $this->duplicated('A', 10, 10);
        $set = $this->createSet([[$a, 1]]);

        $other = Organization::create(['name' => 'Other', 'email' => 'o@o.com', 'currency' => 'USD', 'timezone' => 'UTC']);
        $outsider = User::create([
            'name' => 'Outsider', 'email' => 'out@test.com', 'password' => bcrypt('password'),
            'organization_id' => $other->id, 'role' => 'admin',
        ]);

        $this->actingAs($outsider)->get(route('sets.show', $set))->assertForbidden();
        $this->actingAs($outsider)->delete(route('sets.destroy', $set))->assertForbidden();
        $this->actingAs($outsider)->post(route('orders.store'), $this->orderPayload([[
            'product_set_id' => $set->id, 'quantity' => 1,
            'selections' => $this->selection($set, $a),
        ]]))->assertSessionHas('error');
        $this->assertSame(10, $a->fresh()->stock);
    }

    public function test_instructions_keep_only_the_editor_formatting(): void
    {
        [$a] = $this->duplicated('A', 1, 1);

        $this->actingAs($this->admin)->post(route('sets.store'), [
            'name' => 'Clean',
            'instructions' => '<p onclick="x()"><b>Bold</b> <em>Ñandú</em><script>alert(1)</script></p>'
                . '<ul class="a"><li>One</li></ul><ol><li>Two</li></ol><a href="javascript:x()">link</a><img src=x onerror=y>',
            'items' => [['product_id' => $a->id, 'quantity' => 1]],
        ]);

        $this->assertSame(
            '<p><b>Bold</b> <em>Ñandú</em></p><ul><li>One</li></ul><ol><li>Two</li></ol>link',
            ProductSet::where('name', 'Clean')->value('instructions')
        );

        $this->assertNull(HtmlSanitizer::clean('<p></p>'));
        $this->assertNull(HtmlSanitizer::clean('  '));
    }
}
