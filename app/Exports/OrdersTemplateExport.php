<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Empty (headers + one example row) template for the orders import.
 * Mirrors exactly the sheet/column layout produced by OrdersFullExport.
 */
class OrdersTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new OrdersTemplateSheet(
                'Orders',
                [
                    'order_number', 'source', 'external_id', 'customer_name', 'customer_email',
                    'customer_address', 'status', 'approval_status', 'subtotal', 'discount_type',
                    'discount_value', 'discount_amount', 'tax', 'shipping', 'total', 'amount_paid',
                    'payment_status', 'currency', 'order_date', 'shipped_at', 'delivered_at', 'notes',
                ],
                [
                    'ORD-20260715-0001', 'manual', '', 'John Doe', 'john@example.com',
                    '123 Main St', 'delivered', 'approved', '100.00', 'percent',
                    '10', '10.00', '5.00', '0.00', '95.00', '0.00',
                    'pending', 'USD', '2026-07-15 10:00:00', '', '', 'Example order',
                ]
            ),
            new OrdersTemplateSheet(
                'Items',
                ['order_number', 'sku', 'product_name', 'quantity', 'unit_price', 'subtotal', 'tax', 'total'],
                ['ORD-20260715-0001', 'SKU-001', 'Example Product', '2', '50.00', '100.00', '0.00', '100.00']
            ),
            new OrdersTemplateSheet(
                'Payments',
                ['order_number', 'amount', 'method', 'reference', 'paid_at', 'notes', 'registered_by_email'],
                ['ORD-20260715-0001', '50.00', 'cash', 'REF-001', '2026-07-15 12:00:00', 'First abono', '']
            ),
        ];
    }
}

class OrdersTemplateSheet implements FromArray, WithHeadings, WithTitle, WithStyles
{
    public function __construct(
        protected string $sheetTitle,
        protected array $headings,
        protected array $example
    ) {}

    public function array(): array
    {
        return [$this->example];
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
