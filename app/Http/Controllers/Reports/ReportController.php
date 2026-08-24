<?php

namespace App\Http\Controllers\Reports;

use App\Exports\PaymentsReportExport;
use App\Exports\UnpaidOrdersExport;
use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockAdjustment;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Order\OrderPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Display the reports dashboard
     */
    public function index(): Response
    {
        return Inertia::render('Reports/Index');
    }

    /**
     * Inventory Valuation Report
     */
    public function inventoryValuation(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $products = Product::forOrganization($organizationId)
            ->with(['category', 'location'])
            ->where('is_active', true)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'location' => $product->location?->name,
                    'stock' => $product->stock,
                    'price' => $product->price,
                    'purchase_price' => $product->purchase_price ?? 0,
                    'stock_value' => $product->stock * $product->price,
                    'cost_value' => $product->stock * ($product->purchase_price ?? 0),
                    'profit_potential' => $product->stock * ($product->price - ($product->purchase_price ?? 0)),
                ];
            });

        $summary = [
            'total_items' => $products->count(),
            'total_quantity' => $products->sum('stock'),
            'total_stock_value' => $products->sum('stock_value'),
            'total_cost_value' => $products->sum('cost_value'),
            'total_profit_potential' => $products->sum('profit_potential'),
        ];

        // Group by category
        $byCategory = $products->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category ?: 'Uncategorized',
                'items' => $items->count(),
                'quantity' => $items->sum('stock'),
                'value' => $items->sum('stock_value'),
            ];
        })->values();

        return Inertia::render('Reports/InventoryValuation', [
            'products' => $products,
            'summary' => $summary,
            'byCategory' => $byCategory,
        ]);
    }

    /**
     * Stock Movement Report
     */
    public function stockMovement(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $query = StockAdjustment::with(['product', 'user'])
            ->forOrganization($organizationId);

        // Date filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Product filter
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $adjustments = $query->latest()->paginate(50)->withQueryString();

        // Summary statistics
        $summary = [
            'total_adjustments' => StockAdjustment::forOrganization($organizationId)->count(),
            'total_increases' => StockAdjustment::forOrganization($organizationId)
                ->where('adjustment_quantity', '>', 0)->sum('adjustment_quantity'),
            'total_decreases' => abs(StockAdjustment::forOrganization($organizationId)
                ->where('adjustment_quantity', '<', 0)->sum('adjustment_quantity')),
            'net_change' => StockAdjustment::forOrganization($organizationId)->sum('adjustment_quantity'),
        ];

        // Get products for filter
        $products = Product::forOrganization($organizationId)
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        return Inertia::render('Reports/StockMovement', [
            'adjustments' => $adjustments,
            'summary' => $summary,
            'products' => $products,
            'filters' => $request->only(['date_from', 'date_to', 'product_id', 'type']),
        ]);
    }

    /**
     * Sales Analysis Report
     */
    public function salesAnalysis(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $query = Order::forOrganization($organizationId);

        // Date filters
        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->format('Y-m-d');

        $query->whereDate('order_date', '>=', $dateFrom)
            ->whereDate('order_date', '<=', $dateTo);

        $orders = $query->with('items.product')->get();

        // Overall summary
        $summary = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->sum('total'),
            'total_items_sold' => $orders->sum(function ($order) {
                return $order->items->sum('quantity');
            }),
            'average_order_value' => $orders->count() > 0 ? $orders->sum('total') / $orders->count() : 0,
        ];

        // Sales by status
        $byStatus = $orders->groupBy('status')->map(function ($items, $status) {
            return [
                'status' => $status,
                'count' => $items->count(),
                'revenue' => $items->sum('total'),
            ];
        })->values();

        // Top selling products
        $productSales = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $productId = $item->product_id;
                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = [
                        'product_name' => $item->product_name,
                        'sku' => $item->sku,
                        'quantity_sold' => 0,
                        'revenue' => 0,
                    ];
                }
                $productSales[$productId]['quantity_sold'] += $item->quantity;
                $productSales[$productId]['revenue'] += $item->total;
            }
        }
        $topProducts = collect($productSales)->sortByDesc('revenue')->take(10)->values();

        // Daily sales trend
        $dailySales = $orders->groupBy(function ($order) {
            return date('Y-m-d', strtotime($order->order_date));
        })->map(function ($items, $date) {
            return [
                'date' => $date,
                'orders' => $items->count(),
                'revenue' => $items->sum('total'),
            ];
        })->sortBy('date')->values();

        return Inertia::render('Reports/SalesAnalysis', [
            'summary' => $summary,
            'byStatus' => $byStatus,
            'topProducts' => $topProducts,
            'dailySales' => $dailySales,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Low Stock Report
     */
    public function lowStock(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $products = Product::forOrganization($organizationId)
            ->with(['category', 'location'])
            ->where('is_active', true)
            ->whereRaw('stock <= min_stock')
            ->orderBy('stock', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'location' => $product->location?->name,
                    'current_stock' => $product->stock,
                    'min_stock' => $product->min_stock,
                    'max_stock' => $product->max_stock,
                    'deficit' => $product->min_stock - $product->stock,
                    'status' => $product->stock <= 0 ? 'out_of_stock' : 'low_stock',
                    'price' => $product->price,
                    'reorder_cost' => ($product->max_stock - $product->stock) * ($product->purchase_price ?? $product->price),
                ];
            });

        $summary = [
            'total_low_stock' => $products->count(),
            'out_of_stock' => $products->where('status', 'out_of_stock')->count(),
            'low_stock' => $products->where('status', 'low_stock')->count(),
            'total_reorder_cost' => $products->sum('reorder_cost'),
        ];

        return Inertia::render('Reports/LowStock', [
            'products' => $products,
            'summary' => $summary,
        ]);
    }

    /**
     * Category Performance Report
     */
    public function categoryPerformance(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        // Get all products grouped by category
        $products = Product::forOrganization($organizationId)
            ->with('category')
            ->where('is_active', true)
            ->get()
            ->groupBy('category_id');

        $categoryStats = $products->map(function ($items, $categoryId) {
            $category = $items->first()->category;
            return [
                'category_id' => $categoryId,
                'category_name' => $category?->name ?? 'Uncategorized',
                'product_count' => $items->count(),
                'total_stock' => $items->sum('stock'),
                'total_value' => $items->sum(function ($p) {
                    return $p->stock * $p->price;
                }),
                'low_stock_items' => $items->filter(function ($p) {
                    return $p->stock <= $p->min_stock;
                })->count(),
            ];
        })->values()->sortByDesc('total_value');

        return Inertia::render('Reports/CategoryPerformance', [
            'categories' => $categoryStats,
            'summary' => [
                'total_categories' => $categoryStats->count(),
                'total_products' => $categoryStats->sum('product_count'),
                'total_value' => $categoryStats->sum('total_value'),
            ],
        ]);
    }

    /**
     * Build the base query for unpaid orders (not cancelled, not fully paid),
     * applying the report filters (date range, staff user, customer name).
     */
    private function unpaidOrdersQuery(Request $request, int $organizationId)
    {
        $query = Order::query()
            ->with('creator')
            ->forOrganization($organizationId)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid');

        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }
        if ($request->filled('user_id')) {
            $query->where('created_by', $request->user_id);
        }
        if ($request->filled('customer')) {
            $query->where('customer_name', 'like', '%' . $request->customer . '%');
        }

        return $query->orderBy('order_date', 'desc');
    }

    /**
     * Unpaid Orders Report — orders not cancelled and not fully paid.
     */
    public function unpaidOrders(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $orders = $this->unpaidOrdersQuery($request, $organizationId)->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_date' => $order->order_date?->format('Y-m-d'),
                'customer_name' => $order->customer_name,
                'created_by_name' => $order->creator?->name,
                'status' => $order->status,
                'total' => (float) $order->total,
                'amount_paid' => (float) $order->amount_paid,
                'balance_due' => (float) $order->balance_due,
                'payment_status' => $order->payment_status,
                'currency' => $order->currency ?? 'USD',
            ];
        });

        $summary = [
            'total_orders' => $orders->count(),
            'total_value' => round($orders->sum('total'), 2),
            'total_paid' => round($orders->sum('amount_paid'), 2),
            'total_balance_due' => round($orders->sum('balance_due'), 2),
        ];

        return Inertia::render('Reports/UnpaidOrders', [
            'orders' => $orders,
            'summary' => $summary,
            'users' => $this->organizationUsers($organizationId),
            'filters' => $request->only(['date_from', 'date_to', 'user_id', 'customer']),
        ]);
    }

    /**
     * Export the Unpaid Orders report to Excel (honours current filters).
     */
    public function exportUnpaidOrders(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        $filters = $request->only(['date_from', 'date_to', 'user_id', 'customer']);

        return Excel::download(
            new UnpaidOrdersExport($organizationId, $filters),
            'unpaid_orders_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * Build the base query for the payments/abonos report, applying the report
     * filters (date range, staff user who registered it, customer, method).
     */
    private function paymentsQuery(Request $request, int $organizationId)
    {
        $query = OrderPayment::query()
            ->with(['order', 'creator'])
            ->forOrganization($organizationId);

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date_to);
        }
        if ($request->filled('user_id')) {
            $query->where('created_by', $request->user_id);
        }
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }
        if ($request->filled('customer')) {
            $customer = $request->customer;
            $query->whereHas('order', function ($q) use ($customer) {
                $q->where('customer_name', 'like', '%' . $customer . '%');
            });
        }

        return $query->orderBy('paid_at', 'desc');
    }

    /**
     * Order Payments Report — one row per payment, for bank reconciliation.
     */
    public function payments(Request $request): Response
    {
        $organizationId = $request->user()->organization_id;

        $payments = $this->paymentsQuery($request, $organizationId)->get()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i'),
                'order_number' => $payment->order?->order_number,
                'customer_name' => $payment->order?->customer_name,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
                'registered_by_name' => $payment->creator?->name,
                'notes' => $payment->notes,
            ];
        });

        $byMethod = $payments->groupBy('method')->map(function ($items, $method) {
            return [
                'method' => $method,
                'count' => $items->count(),
                'amount' => round($items->sum('amount'), 2),
            ];
        })->values();

        $summary = [
            'total_payments' => $payments->count(),
            'total_amount' => round($payments->sum('amount'), 2),
        ];

        return Inertia::render('Reports/Payments', [
            'payments' => $payments,
            'summary' => $summary,
            'byMethod' => $byMethod,
            'users' => $this->organizationUsers($organizationId),
            'methods' => ['cash', 'transfer', 'card', 'yappy', 'other'],
            'filters' => $request->only(['date_from', 'date_to', 'user_id', 'customer', 'method']),
        ]);
    }

    /**
     * Export the Order Payments report to Excel (honours current filters).
     */
    public function exportPayments(Request $request)
    {
        $organizationId = $request->user()->organization_id;
        $filters = $request->only(['date_from', 'date_to', 'user_id', 'customer', 'method']);

        return Excel::download(
            new PaymentsReportExport($organizationId, $filters),
            'payments_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * Staff users of the organization, for the "person" filter dropdowns.
     */
    private function organizationUsers(int $organizationId)
    {
        return User::where('organization_id', $organizationId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }
}
