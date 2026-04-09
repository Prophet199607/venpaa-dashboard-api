<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;

class SalesReportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $reportData;
    protected $totals;

    public function __construct(array $reportData, $totals = null)
    {
        $this->reportData = $reportData;
        $this->totals = $totals;
    }

    public function array(): array
    {
        $formatted = [];
        foreach ($this->reportData as $row) {
            $rowObj = (object)$row;
            
            $formatted[] = [
                $rowObj->BillDate ?? '',
                $rowObj->Loca ?? '',
                $rowObj->CODE ?? '',
                $rowObj->Description ?? '',
                $rowObj->Sale_Type ?? '',
                $rowObj->Unit_Price ?? 0,
                $rowObj->Qty ?? 0,
                $rowObj->Order_Value ?? 0,
                $rowObj->COD_Charge ?? 0,
                $rowObj->Courier_Charge ?? 0,
                $rowObj->Postal_Cost ?? 0,
                $rowObj->Gross_Amount ?? 0,
                $rowObj->Discount ?? 0,
                $rowObj->VAT ?? 0,
                $rowObj->Net_Amount ?? 0,
            ];
        }

        if ($this->totals) {
            $t = (object)$this->totals;
            $formatted[] = [
                'TOTAL',
                '',
                '',
                '',
                '',
                $t->Qty ?? 0,
                $t->Order_Value ?? 0,
                $t->COD_Charge ?? 0,
                $t->Courier_Charge ?? 0,
                $t->Postal_Cost ?? 0,
                $t->Gross_Amount ?? 0,
                $t->Discount ?? 0,
                $t->VAT ?? 0,
                $t->Net_Amount ?? 0,
            ];
        }

        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Sale Date',
            'Location',
            'Code',
            'Description',
            'Sale Type',
            'Unit Price',
            'Qty',
            'Order Value',
            'COD Fee',
            'Courier Charge',
            'Postal Cost',
            'Gross Amount',
            'Discount',
            'VAT',
            'Net Amount',
        ];
    }

    public function title(): string
    {
        return 'Sales Report';
    }
}
