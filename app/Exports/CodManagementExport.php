<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CodManagementExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithColumnFormatting
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
                $row['doc_no'] ?? '-',
                $row['customer'] ?? '-',
                $row['loca_name'] ?? $row['location'] ?? '-',
                explode(' ', $row['transaction_date'] ?? '')[0] ?? '-',
                (float) ($row['transaction_amount'] ?? 0),
                $row['status'] ?? 'Pending',
                (float) ($row['received_amount'] ?? 0),
                (float) (($row['transaction_amount'] ?? 0) - ($row['received_amount'] ?? 0)),
            ];
        }
        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Doc No',
            'Customer',
            'Location',
            'Transaction Date',
            'Transaction Amount',
            'Status',
            'Received Amount',
            'Balance Amount',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function title(): string
    {
        return 'COD Management';
    }
}
