<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CurrentStockExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
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
}
