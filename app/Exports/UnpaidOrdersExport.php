<?php

namespace App\Exports;

use App\Models\Order\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export for the "Unpaid Orders" report — orders that are not cancelled
 * and not fully paid (outstanding balance > 0). Honours the same filters as the
 * on-screen report (date range, staff user who created the order, customer name).
 */
class UnpaidOrdersExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $organizationId;
    protected array $filters;

    public function __construct($organizationId, array $filters = [])
    {
        $this->organizationId = $organizationId;
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Order::query()
            ->with('creator')
            ->forOrganization($this->organizationId)
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', '!=', 'paid');

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('order_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('order_date', '<=', $this->filters['date_to']);
        }
        if (!empty($this->filters['user_id'])) {
            $query->where('created_by', $this->filters['user_id']);
        }
        if (!empty($this->filters['customer'])) {
            $query->where('customer_name', 'like', '%' . $this->filters['customer'] . '%');
        }

        return $query->orderBy('order_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'Order Date',
            'Customer',
            'Created By',
            'Status',
            'Total',
            'Amount Paid',
            'Balance Due',
            'Payment Status',
            'Currency',
        ];
    }

    public function map($order): array
    {
        return [
            $order->order_number,
            optional($order->order_date)->format('Y-m-d'),
            $order->customer_name,
            $order->creator?->name,
            $order->status,
            $order->total,
            $order->amount_paid,
            $order->balance_due,
            $order->payment_status,
            $order->currency ?? 'USD',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
