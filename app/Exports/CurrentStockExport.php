<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

class CurrentStockExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents, WithStrictNullComparison
{
    protected $reportData;
    protected $transactionDate;

    public function __construct(array $reportData, $transactionDate = null)
    {
        $this->reportData = $reportData;
        $this->transactionDate = $transactionDate;
    }

    public function array(): array
    {
        $formatted = [];
        foreach ($this->reportData as $row) {
            $rowObj = (object)$row;
            
            $stockQty = floatval($rowObj->Stock_Qty ?? 0);
            $purchasePrice = floatval($rowObj->Purchase_Price ?? 0);
            $stockAmount = $stockQty * $purchasePrice;

            $locationDisplay = (!empty($rowObj->Loca_Name)) 
                ? $rowObj->Loca_Name 
                : ($rowObj->Loca ?? '');

            $formatted[] = [
                $locationDisplay,
                $rowObj->Prod_Code ?? '',
                $rowObj->Prod_Name ?? '',
                $rowObj->Department ?? '',
                $rowObj->Category ?? '',
                $rowObj->SupplierCodes ?? '',
                $rowObj->Selling_Price ?? 0,
                $purchasePrice,
                $stockQty,
                $stockAmount,
            ];
        }
        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Location',
            'Prod Code',
            'Product Name',
            'Department',
            'Category',
            'Supplier',
            'Selling Price',
            'Purchase Price',
            'Stock Qty',
            'Stock Amount',
        ];
    }

    public function title(): string
    {
        return 'Current Stock Report';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                $sheet->insertNewRowBefore(1, 2);

                $title = 'Current Stock Report';
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $dateLabel = 'Transaction Date: ' . ($this->transactionDate ?: 'All');
                $sheet->setCellValue('A2', $dateLabel);
                $sheet->mergeCells('A2:' . $lastColumn . '2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Style the headings row (now row 3)
                $headingRow = 3;
                $sheet->getStyle('A' . $headingRow . ':' . $lastColumn . $headingRow)
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle('A' . $headingRow . ':' . $lastColumn . $headingRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
