<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SupplierWisePurchasingExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
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
                isset($row['purchase_type']) ? ucfirst($row['purchase_type']) : '-',
                (float) ($row['purchase_amount'] ?? 0),
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
            'Purchase Amount',
            'VAT',
            'Invoice Value',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function title(): string
    {
        return 'Supplier Wise Purchasing';
    }
}
