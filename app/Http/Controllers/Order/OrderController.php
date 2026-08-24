<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Order\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $orders = Order::with(['items'])
            ->forOrganization($organizationId)
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%")
                      ->orWhere('customer_email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('status'), function ($query, $status) {
                $query->byStatus($status);
            })
            ->when($request->input('source'), function ($query, $source) {
                $query->bySource($source);
            })
            ->when($request->input('payment_status'), function ($query, $paymentStatus) {
                $query->byPaymentStatus($paymentStatus);
            })
            ->latest('order_date')
            ->paginate(config('limits.pagination.default'))
            ->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'source', 'payment_status']),
            'statuses' => ['pending', 'processing', 'shipped', 'delivered', 'cancelled'],
            'paymentStatuses' => ['pending', 'partial', 'paid'],
            'sources' => ['manual', 'ebay', 'shopify', 'amazon'],
            'pluginComponents' => [
                'header' => get_page_components('orders.index', 'header'),
                'beforeTable' => get_page_components('orders.index', 'before-table'),
                'footer' => get_page_components('orders.index', 'footer'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $products = Product::forOrganization($organizationId)
            ->active()
            ->with(['category', 'location'])
            ->get(['id', 'name', 'sku', 'price', 'stock', 'category_id', 'location_id']);

        return Inertia::render('Orders/Create', [
            'products' => $products,
        ]);
    }

    /**
     * Compute the global discount amount for an order.
     * Validates that the discount does not exceed the subtotal.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function computeDiscountAmount(array $validated, float $subtotal): float
    {
        $type = $validated['discount_type'] ?? null;
        $value = (float) ($validated['discount_value'] ?? 0);

        if (!$type || $value <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            if ($value > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'discount_value' => 'Percentage discount cannot exceed 100%.',
                ]);
            }

            return round($subtotal * $value / 100, 2);
        }

        // fixed
        if ($value > $subtotal + 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_value' => 'Discount cannot exceed the order subtotal.',
            ]);
        }

        return round(min($value, $subtotal), 2);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'order_date' => 'required|date',
            'shipping' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0|required_with:discount_type',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by'] = $request->user()->id;
        $validated['order_number'] = Order::generateOrderNumber();
        $validated['source'] = 'manual';
        $validated['approval_status'] = 'pending';

        // Use transaction with locking to prevent race conditions on stock updates
        try {
            $order = DB::transaction(function () use ($validated) {
                $subtotal = 0;
                $orderItems = [];

                foreach ($validated['items'] as $item) {
                    // Lock the product row for update to prevent concurrent modifications
                    $product = Product::where('id', $item['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \Exception("Product not found: {$item['product_id']}");
                    }

                    // Check if sufficient stock is available
                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock}, Requested: {$item['quantity']}");
                    }

                    $itemSubtotal = $item['quantity'] * $item['unit_price'];
                    $subtotal += $itemSubtotal;

                    $orderItems[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $itemSubtotal,
                        'tax' => 0,
                        'total' => $itemSubtotal,
                    ];

                    // Reduce product stock (within transaction with lock)
                    $product->decrement('stock', $item['quantity']);
                }

                $validated['subtotal'] = $subtotal;
                $validated['tax'] = $validated['tax'] ?? 0;
                $validated['shipping'] = $validated['shipping'] ?? 0;
                $validated['discount_value'] = $validated['discount_value'] ?? 0;
                $validated['discount_amount'] = $this->computeDiscountAmount($validated, $subtotal);
                $validated['total'] = round($subtotal - $validated['discount_amount'] + $validated['tax'] + $validated['shipping'], 2);

                $order = Order::create($validated);
                $order->items()->createMany($orderItems);

                return $order;
            });

            return redirect()->route('orders.index')
                ->with('success', 'Order created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order): Response
    {
        $order->load(['items.product', 'organization', 'creator', 'approver', 'payments.creator']);

        // Ensure user can only view orders from their organization
        if ($order->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        // Check if user can approve orders
        $canApprove = auth()->user()->hasPermission('approve_orders');

        // Check if user can register/delete payments
        $canManagePayments = auth()->user()->hasPermission('manage_order_payments');

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'canApprove' => $canApprove,
            'canManagePayments' => $canManagePayments,
            'pluginComponents' => [
                'header' => get_page_components('orders.show', 'header'),
                'sidebar' => get_page_components('orders.show', 'sidebar'),
                'tabs' => get_page_components('orders.show', 'tabs'),
                'footer' => get_page_components('orders.show', 'footer'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Order $order): Response
    {
        // Ensure user can only edit orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $organizationId = $request->user()->organization_id;

        $products = Product::forOrganization($organizationId)
            ->active()
            ->with(['category', 'location'])
            ->get(['id', 'name', 'sku', 'price', 'stock', 'category_id', 'location_id']);

        $order->load('items');

        return Inertia::render('Orders/Edit', [
            'order' => $order,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        // Ensure user can only update orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'order_date' => 'required|date',
            'shipping' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0|required_with:discount_type',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Load existing items
        $order->load('items');
        $existingItems = $order->items->keyBy('id');

        // Track which items to keep
        $itemIdsToKeep = [];

        // Calculate new totals
        $subtotal = 0;
        $updatedItems = [];

        foreach ($validated['items'] as $itemData) {
            $product = Product::find($itemData['product_id']);
            $itemSubtotal = $itemData['quantity'] * $itemData['unit_price'];
            $subtotal += $itemSubtotal;

            if (!empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                // Update existing item
                $existingItem = $existingItems->get($itemData['id']);
                $quantityDiff = $itemData['quantity'] - $existingItem->quantity;

                // Adjust stock based on quantity change
                if ($quantityDiff != 0) {
                    $product->decrement('stock', $quantityDiff);
                }

                $existingItem->update([
                    'product_id' => $itemData['product_id'],
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                ]);

                $itemIdsToKeep[] = $itemData['id'];
            } else {
                // New item
                $updatedItems[] = [
                    'product_id' => $itemData['product_id'],
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemSubtotal,
                    'tax' => 0,
                    'total' => $itemSubtotal,
                ];

                // Reduce stock for new items
                $product->decrement('stock', $itemData['quantity']);
            }
        }

        // Delete removed items and restore their stock
        $itemsToDelete = $existingItems->filter(function ($item) use ($itemIdsToKeep) {
            return !in_array($item->id, $itemIdsToKeep);
        });

        foreach ($itemsToDelete as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
            $item->delete();
        }

        // Create new items
        if (!empty($updatedItems)) {
            $order->items()->createMany($updatedItems);
        }

        // Update order totals and metadata
        $validated['subtotal'] = $subtotal;
        $validated['tax'] = $validated['tax'] ?? 0;
        $validated['shipping'] = $validated['shipping'] ?? 0;
        $validated['discount_value'] = $validated['discount_value'] ?? 0;
        $validated['discount_amount'] = $this->computeDiscountAmount($validated, $subtotal);
        $validated['total'] = round($subtotal - $validated['discount_amount'] + $validated['tax'] + $validated['shipping'], 2);

        // The new total cannot be lower than what has already been paid
        if ((float) $order->amount_paid > $validated['total'] + 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'total' => 'The new order total ($' . number_format($validated['total'], 2) . ') cannot be lower than the amount already paid ($' . number_format((float) $order->amount_paid, 2) . '). Remove payments first.',
            ]);
        }

        // Update order timestamps based on status
        if ($validated['status'] === 'shipped' && !$order->shipped_at) {
            $validated['shipped_at'] = now();
        } elseif ($validated['status'] === 'delivered' && !$order->delivered_at) {
            $validated['delivered_at'] = now();
        }

        $order->update($validated);

        // Total may have changed: refresh accumulated payment status
        $order->recalculatePaymentStatus();

        return redirect()->route('orders.index')
            ->with('success', 'Order updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Order $order)
    {
        // Ensure user can only delete orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        // Restore stock for all items
        foreach ($order->items as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }

        $order->delete();

        return redirect()->route('orders.index')
            ->with('success', 'Order deleted successfully.');
    }

    /**
     * Approve an order.
     */
    public function approve(Request $request, Order $order)
    {
        // Ensure user can only approve orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        // Check if order is pending approval
        if (!$order->isPendingApproval()) {
            return redirect()->back()->with('error', 'Order has already been processed.');
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $order->update([
            'approval_status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['notes'] ?? null,
        ]);

        // Load the approver relationship for notification
        $order->load('approver');

        // Send notification to order creator
        NotificationService::createOrderApprovalNotification($order);

        return redirect()->back()->with('success', 'Order approved successfully.');
    }

    /**
     * Reject an order.
     */
    public function reject(Request $request, Order $order)
    {
        // Ensure user can only reject orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        // Check if order is pending approval
        if (!$order->isPendingApproval()) {
            return redirect()->back()->with('error', 'Order has already been processed.');
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $order->update([
            'approval_status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $validated['notes'],
        ]);

        // Load the approver relationship for notification
        $order->load('approver');

        // Send notification to order creator
        NotificationService::createOrderApprovalNotification($order);

        return redirect()->back()->with('success', 'Order rejected.');
    }
}
