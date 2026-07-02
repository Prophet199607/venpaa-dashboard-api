<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SupplierWisePurchasingExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function array(): array
    {
        $formatted = [];
        foreach ($this->reportData as $row) {
            $formatted[] = [
                $row['date'],
                $row['location'],
                $row['supplier'],
                $row['grn_number'],
                $row['invoice_number'],
                $row['purchase_type'] ? ucfirst($row['purchase_type']) : '-',
                number_format((float) ($row['purchase_amount'] ?? 0), 2),
                number_format((float) ($row['vat'] ?? 0), 2),
                number_format((float) ($row['invoice_value'] ?? 0), 2),
            ];
        }
        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Location',
            'Supplier',
            'GRN No',
            'Invoice No',
            'Purchase Type',
            'Purchase Amount',
            'VAT',
            'Invoice Value',
        ];
    }

    public function title(): string
    {
        return 'Supplier Wise Purchasing';
    }
}
