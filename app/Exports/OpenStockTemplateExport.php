<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpenStockTemplateExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return collect([]);
    }

    public function headings(): array
    {
        return [
            'product_code',
            'product_name',
            'supplier_code',
            'quantity',
            'cost',
            'selling_price'
        ];
    }
}
