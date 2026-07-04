<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ItemWisePurchasingExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
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
            $typeLabel = isset($row['purchase_type'])
                ? ($row['purchase_type'] === 'cash' ? 'Cash' : ($row['purchase_type'] === 'credit' ? 'Credit' : ucfirst($row['purchase_type'])))
                : '-';

            $formatted[] = [
                $row['date'],
                $row['location'],
                $row['supplier'],
                $row['grn_number'],
                $row['invoice_number'] ?? '',
                $typeLabel,
                $row['prod_code'],
                $row['prod_name'],
                (float) ($row['qty'] ?? 0),
                (float) ($row['rate'] ?? 0),
                (float) ($row['amount'] ?? 0),
                (float) ($row['vat'] ?? 0),
                (float) ($row['invoice_value'] ?? 0),
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
            'Supplier Invoice No',
            'Purchase Type',
            'Code',
            'Product Name',
            'Purchase Qty',
            'Unit Purchase Price',
            'Purchase Amount',
            'VAT',
            'Invoice Value',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'I' => '#,##0',
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function title(): string
    {
        return 'Item Wise Purchasing';
    }
}
