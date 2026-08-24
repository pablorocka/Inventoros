<?php

namespace App\Exports;

use App\Models\Order\OrderPayment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export for the "Order Payments" report — one row per payment, meant
 * for bank reconciliation. Shows who registered each payment. Honours the same
 * filters as the on-screen report (date range, staff user, customer, method).
 */
class PaymentsReportExport implements FromQuery, WithHeadings, WithMapping, WithStyles
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
        $query = OrderPayment::query()
            ->with(['order', 'creator'])
            ->forOrganization($this->organizationId);

        if (!empty($this->filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $this->filters['date_to']);
        }
        if (!empty($this->filters['user_id'])) {
            $query->where('created_by', $this->filters['user_id']);
        }
        if (!empty($this->filters['method'])) {
            $query->where('method', $this->filters['method']);
        }
        if (!empty($this->filters['customer'])) {
            $customer = $this->filters['customer'];
            $query->whereHas('order', function ($q) use ($customer) {
                $q->where('customer_name', 'like', '%' . $customer . '%');
            });
        }

        return $query->orderBy('paid_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Paid At',
            'Order Number',
            'Customer',
            'Amount',
            'Method',
            'Reference',
            'Registered By',
            'Notes',
        ];
    }

    public function map($payment): array
    {
        return [
            optional($payment->paid_at)->format('Y-m-d H:i:s'),
            $payment->order?->order_number,
            $payment->order?->customer_name,
            $payment->amount,
            $payment->method,
            $payment->reference,
            $payment->creator?->name,
            $payment->notes,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
