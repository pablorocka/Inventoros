<?php

namespace App\Exports;

use App\Models\Order\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Full, round-trippable export of orders with their items and payments (abonos).
 *
 * Produces a single .xlsx workbook with three sheets linked by `order_number`:
 *   - Orders   : one row per order (header fields)
 *   - Items    : one row per order line item
 *   - Payments : one row per payment / abono
 *
 * The headings are snake_case on purpose so the file round-trips cleanly through
 * OrdersImport (which reads them as WithHeadingRow keys).
 */
class OrdersFullExport implements WithMultipleSheets
{
    protected $organizationId;
    protected array $filters;

    public function __construct($organizationId, array $filters = [])
    {
        $this->organizationId = $organizationId;
        $this->filters = $filters;
    }

    /**
     * Load the filtered orders once (with items + payments) and hand the same
     * collection to each sheet so the three sheets stay consistent.
     */
    public function sheets(): array
    {
        $query = Order::query()
            ->with(['items', 'payments.creator'])
            ->forOrganization($this->organizationId);

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('order_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('order_date', '<=', $this->filters['date_to']);
        }

        $orders = $query->orderBy('order_date', 'desc')->get();

        return [
            new OrdersHeaderSheet($orders),
            new OrderItemsSheet($orders),
            new OrderPaymentsSheet($orders),
        ];
    }
}

/**
 * Sheet 1 — order header rows.
 */
class OrdersHeaderSheet implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    public function __construct(protected Collection $orders) {}

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function title(): string
    {
        return 'Orders';
    }

    public function headings(): array
    {
        return [
            'order_number',
            'source',
            'external_id',
            'customer_name',
            'customer_email',
            'customer_address',
            'status',
            'approval_status',
            'subtotal',
            'discount_type',
            'discount_value',
            'discount_amount',
            'tax',
            'shipping',
            'total',
            'amount_paid',
            'payment_status',
            'currency',
            'order_date',
            'shipped_at',
            'delivered_at',
            'notes',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            $order->source,
            $order->external_id,
            $order->customer_name,
            $order->customer_email,
            $order->customer_address,
            $order->status,
            $order->approval_status,
            $order->subtotal,
            $order->discount_type,
            $order->discount_value,
            $order->discount_amount,
            $order->tax,
            $order->shipping,
            $order->total,
            $order->amount_paid,
            $order->payment_status,
            $order->currency ?? 'USD',
            optional($order->order_date)->format('Y-m-d H:i:s'),
            optional($order->shipped_at)->format('Y-m-d H:i:s'),
            optional($order->delivered_at)->format('Y-m-d H:i:s'),
            $order->notes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

/**
 * Sheet 2 — order line items (flattened, keyed back to the order by order_number).
 */
class OrderItemsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(protected Collection $orders) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->orders as $order) {
            foreach ($order->items as $item) {
                $rows->push([
                    $order->order_number,
                    $item->sku,
                    $item->product_name,
                    $item->quantity,
                    $item->unit_price,
                    $item->subtotal,
                    $item->tax,
                    $item->total,
                ]);
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Items';
    }

    public function headings(): array
    {
        return [
            'order_number',
            'sku',
            'product_name',
            'quantity',
            'unit_price',
            'subtotal',
            'tax',
            'total',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

/**
 * Sheet 3 — payments / abonos (flattened, keyed back to the order by order_number).
 */
class OrderPaymentsSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(protected Collection $orders) {}

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->orders as $order) {
            foreach ($order->payments as $payment) {
                $rows->push([
                    $order->order_number,
                    $payment->amount,
                    $payment->method,
                    $payment->reference,
                    optional($payment->paid_at)->format('Y-m-d H:i:s'),
                    $payment->notes,
                    $payment->creator?->email,
                ]);
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Payments';
    }

    public function headings(): array
    {
        return [
            'order_number',
            'amount',
            'method',
            'reference',
            'paid_at',
            'notes',
            'registered_by_email',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
