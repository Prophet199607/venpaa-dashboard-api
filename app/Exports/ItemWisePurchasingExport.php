<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ItemWisePurchasingExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
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
                $row['grn_number'],
                $row['prod_code'],
                $row['prod_name'],
                $row['supplier'],
                number_format((float) ($row['qty'] ?? 0), 3),
                number_format((float) ($row['rate'] ?? 0), 2),
                number_format((float) ($row['amount'] ?? 0), 2),
            ];
        }
        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Location',
            'GRN No',
            'Product Code',
            'Product Name',
            'Supplier',
            'Qty',
            'Rate',
            'Amount',
        ];
    }

    public function title(): string
    {
        return 'Item Wise Purchasing';
    }
}
