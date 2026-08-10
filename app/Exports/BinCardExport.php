<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BinCardExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $transactions;

    public function __construct(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function array(): array
    {
        $formatted = [];
        foreach ($this->transactions as $row) {
            $formatted[] = [
                $row['transaction'],
                $row['date'],
                $row['document'],
                $row['reference'],
                $row['cost'],
                $row['selling_price'],
                $row['stock_in'],
                $row['stock_out'] ? '-' . $row['stock_out'] : '',
                $row['balance'],
            ];
        }
        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Transaction',
            'Date',
            'Document',
            'Reference',
            'Purchase Price',
            'Selling Price',
            'Stock In',
            'Stock Out',
            'Balance',
        ];
    }

    public function title(): string
    {
        return 'Bin Card';
    }
}
