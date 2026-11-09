<?php

namespace App\Services;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSet;
use App\Models\Inventory\ProductSetItem;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductSetService
{
    /**
     * Products are entered once per location under the same name, so names identify them.
     */
    private function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Active products grouped into one option per unique name.
     */
    public function productOptions(int $organizationId): array
    {
        return Product::forOrganization($organizationId)
            ->active()
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'stock', 'location_id'])
            ->groupBy(fn (Product $product) => $this->nameKey($product->name))
            ->map(fn (Collection $instances) => [
                'id' => $instances->first()->id,
                'instance_ids' => $instances->pluck('id')->values(),
                'name' => trim($instances->first()->name),
                'stock' => $instances->sum('stock'),
                'locations' => $instances->map(fn (Product $product) => $product->location?->name)->filter()->unique()->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Replace the items of a set, linking each one to all instances of the chosen product.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws ValidationException
     */
    public function syncItems(ProductSet $set, array $items): void
    {
        $groups = Product::forOrganization($set->organization_id)
            ->active()
            ->get(['id', 'name'])
            ->groupBy(fn (Product $product) => $this->nameKey($product->name));

        $byId = $groups->flatten(1)->keyBy('id');
        $seen = [];

        $set->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $product = $byId->get($item['product_id']);

            if (!$product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'This product is no longer available.',
                ]);
            }

            $key = $this->nameKey($product->name);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "{$product->name} is already in this set.",
                ]);
            }

            $seen[$key] = true;

            $setItem = $set->items()->create([
                'name' => trim($product->name),
                'quantity' => $item['quantity'],
                'sort_order' => $index,
            ]);

            $setItem->products()->sync($groups->get($key)->pluck('id'));
        }
    }

    /**
     * Sets as needed by the order forms, with every linked instance and its stock.
     */
    public function orderFormSets(int $organizationId): array
    {
        return ProductSet::forOrganization($organizationId)
            ->with(['items.activeProducts.location:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (ProductSet $set) => [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'available' => $set->availableQuantity(),
                'items' => $set->items->map(fn (ProductSetItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'products' => $item->activeProducts->map(fn (Product $product) => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'price' => (float) $product->price,
                        'stock' => $product->stock,
                        'location_id' => $product->location_id,
                        'location_name' => $product->location?->name ?? 'No location',
                    ])->values(),
                ])->values(),
            ])
            ->all();
    }

    /**
     * Turn set lines into order item rows and take their stock.
     * Must run inside a transaction: product rows stay locked until it commits.
     *
     * @param  array<int, array>  $lines
     * @return array<int, array> rows for OrderItem
     *
     * @throws Exception when a selection is invalid or stock is insufficient
     */
    public function expandOrderLines(array $lines, int $organizationId): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $set = ProductSet::forOrganization($organizationId)
                ->with('items.activeProducts')
                ->find($line['product_set_id'] ?? null);

            if (!$set) {
                throw new Exception('One of the selected sets no longer exists.');
            }

            $selections = collect($line['selections'] ?? [])->pluck('product_id', 'set_item_id');
            $lineId = (string) Str::uuid();

            if ($selections->keys()->diff($set->items->pluck('id'))->isNotEmpty()) {
                throw new Exception("The set {$set->name} was changed. Remove it from the order and add it again.");
            }

            foreach ($set->items as $item) {
                $productId = $selections->get($item->id);

                if (!$productId) {
                    throw new Exception("Select a location for {$item->name} in the set {$set->name}.");
                }

                if (!$item->activeProducts->contains('id', $productId)) {
                    throw new Exception("{$item->name} is not available from the selected location.");
                }

                $product = Product::whereKey($productId)->with('location:id,name')->lockForUpdate()->first();
                $quantity = $item->quantity * $line['quantity'];

                if ($product->stock < $quantity) {
                    throw new Exception("Insufficient stock for {$product->name}. Available: {$product->stock}, Requested: {$quantity}");
                }

                $subtotal = $quantity * (float) $product->price;

                $rows[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'total' => $subtotal,
                    'metadata' => [
                        'set' => [
                            'line_id' => $lineId,
                            'product_set_id' => $set->id,
                            'name' => $set->name,
                            'quantity' => (int) $line['quantity'],
                            'per_set' => $item->quantity,
                        ],
                        'location' => $product->location?->name,
                    ],
                ];

                $product->decrement('stock', $quantity);
            }
        }

        return $rows;
    }

    /**
     * Change how many sets an existing order line holds, adjusting stock by the difference.
     * Must run inside a transaction.
     *
     * @param  Collection<int, \App\Models\Order\OrderItem>  $items  the order items of the line
     *
     * @throws Exception when stock is insufficient
     */
    public function resizeOrderLine(Collection $items, int $setQuantity): void
    {
        foreach ($items as $item) {
            $metadata = $item->metadata;
            $quantity = (int) $metadata['set']['per_set'] * $setQuantity;
            $difference = $quantity - $item->quantity;

            if ($difference !== 0 && $item->product_id) {
                $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->first();

                if ($product && $difference > 0 && $product->stock < $difference) {
                    throw new Exception("Insufficient stock for {$product->name}. Available: {$product->stock}, Requested: {$difference}");
                }

                $product?->decrement('stock', $difference);
            }

            $metadata['set']['quantity'] = $setQuantity;
            $subtotal = $quantity * (float) $item->unit_price;

            $item->update([
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'metadata' => $metadata,
            ]);
        }
    }
}
