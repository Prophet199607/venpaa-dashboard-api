<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class InventoryMovementExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents, WithStrictNullComparison, WithColumnFormatting
{
    protected $reportData;
    protected $filters;

    public function __construct(array $reportData, array $filters = [])
    {
        $this->reportData = $reportData;
        $this->filters = $filters;
    }

    public function array(): array
    {
        $formatted = [];

        foreach ($this->reportData as $row) {
            $row = (object) $row;

            $locationDisplay = !empty($row->loca_name) ? $row->loca_name : $row->loca_code;

            $formatted[] = [
                $locationDisplay,
                $row->prod_code ?? '',
                $row->prod_name ?? '',
                $row->unit ?? '',
                $row->open_stock_qty ?? 0,
                $row->open_stock_value ?? 0,
                $row->grn_qty ?? 0,
                $row->grn_value ?? 0,
                $row->transfer_in_qty ?? 0,
                $row->transfer_in_value ?? 0,
                $row->total_in_qty ?? 0,
                $row->total_in_value ?? 0,
                $row->sale_qty ?? 0,
                $row->sale_value ?? 0,
                $row->good_return_qty ?? 0,
                $row->good_return_value ?? 0,
                $row->transfer_out_qty ?? 0,
                $row->transfer_out_value ?? 0,
                $row->product_discard_qty ?? 0,
                $row->product_discard_value ?? 0,
                $row->total_out_qty ?? 0,
                $row->total_out_value ?? 0,
                $row->adjustment_qty ?? 0,
                $row->adjustment_value ?? 0,
                $row->close_stock_qty ?? 0,
                $row->close_stock_value ?? 0,
            ];
        }

        $formatted[] = $this->totalRow();

        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Location',
            'Product Code',
            'Product Name',
            'Unit',
            'Open Stock Qty',
            'Open Stock Value',
            'GRN Qty',
            'GRN Value',
            'Transfer In Qty',
            'Transfer In Value',
            'Total In Qty',
            'Total In Value',
            'Sale Qty',
            'Sale Value',
            'Good Return Qty',
            'Good Return Value',
            'Transfer Out Qty',
            'Transfer Out Value',
            'Product Discard Qty',
            'Product Discard Value',
            'Total Out Qty',
            'Total Out Value',
            'Adjustment Qty',
            'Adjustment Value',
            'Close Stock Qty',
            'Close Stock Value',
        ];
    }

    public function title(): string
    {
        return 'Inventory Movement Report';
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'S' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'T' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'U' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'V' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'W' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'X' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'Y' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'Z' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();

                $sheet->insertNewRowBefore(1, 2);

                $sheet->setCellValue('A1', 'Inventory Movement Report');
                $sheet->mergeCells('A1:' . $lastColumn . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $filterParts = [];
                if (!empty($this->filters['location_name'])) {
                    $filterParts[] = 'Location: ' . $this->filters['location_name'];
                } else {
                    $filterParts[] = 'Location: All Locations';
                }

                $filterParts[] = 'Date From: ' . ($this->filters['dateFrom'] ?: 'All');
                $filterParts[] = 'Date To: ' . ($this->filters['dateTo'] ?: 'All');

                $sheet->setCellValue('A2', implode('    |    ', $filterParts));
                $sheet->mergeCells('A2:' . $lastColumn . '2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Headings row (shifted down to row 3 by insertNewRowBefore)
                $headingRow = 3;
                $sheet->getStyle('A' . $headingRow . ':' . $lastColumn . $headingRow)
                    ->getFont()
                    ->setBold(true);
                $sheet->getStyle('A' . $headingRow . ':' . $lastColumn . $headingRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A' . $headingRow . ':' . $lastColumn . $headingRow)
                    ->getBorders()
                    ->getOutline()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Bold the appended total row
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > $headingRow + 1) {
                    $sheet->getStyle('A' . $lastRow . ':' . $lastColumn . $lastRow)
                        ->getFont()
                        ->setBold(true);
                }
            },
        ];
    }

    /**
     * Build the trailing TOTAL row from the already-summed data.
     */
    private function totalRow(): array
    {
        $totals = [];

        foreach ($this->reportData as $row) {
            $row = (object) $row;

            foreach ($this->measureColumns() as $column) {
                $totals[$column] = ($totals[$column] ?? 0) + (float) ($row->{$column} ?? 0);
            }
        }

        $totalRow = ['TOTAL', '', '', ''];

        foreach ($this->measureColumns() as $column) {
            $decimals = substr($column, -4) === '_qty' ? 3 : 2;
            $totalRow[] = round($totals[$column] ?? 0, $decimals);
        }

        return $totalRow;
    }

    /**
     * Ordered measure columns matching the headings order.
     */
    private function measureColumns(): array
    {
        return [
            'open_stock_qty', 'open_stock_value',
            'grn_qty', 'grn_value',
            'transfer_in_qty', 'transfer_in_value',
            'total_in_qty', 'total_in_value',
            'sale_qty', 'sale_value',
            'good_return_qty', 'good_return_value',
            'transfer_out_qty', 'transfer_out_value',
            'product_discard_qty', 'product_discard_value',
            'total_out_qty', 'total_out_value',
            'adjustment_qty', 'adjustment_value',
            'close_stock_qty', 'close_stock_value',
        ];
    }
}
