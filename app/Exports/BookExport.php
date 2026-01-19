<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Product::where('status', 1)
            ->where('department', '10')
            ->with(['bookType'])
            ->get();
    }

    public function headings(): array
    {
        return [
            'Book Code',
            'Book Title',
            'Book Type',
            'Purchase Price',
            'Selling Price',
            'Alert Qty',
        ];
    }

    public function map($book): array
    {
        return [
            $book->prod_code,
            $book->prod_name,
            isset($book->bookType) ? $book->bookType->bkt_name : null,
            $book->purchase_price,
            $book->selling_price,
            $book->alert_qty,
        ];
    }
}
