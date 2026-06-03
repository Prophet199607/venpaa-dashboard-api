<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WebSalesReportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $orders;
    protected $totals;

    public function __construct(array $orders, $totals = null)
    {
        $this->orders = $orders;
        $this->totals = $totals;
    }

    public function array(): array
    {
        $formatted = [];
        foreach ($this->orders as $row) {
            $formatted[] = [
                $row['date'] ?? '',
                $row['doc_no'] ?? '',
                $row['customer_name'] ?? '',
                $row['location_name'] ?? '',
                $row['product_value'] ?? 0,
                $row['discount'] ?? 0,
                $row['sub_total'] ?? 0,
                $row['courier_charge'] ?? 0,
                $row['cod_charge'] ?? 0,
                $row['net_total'] ?? 0,
                $row['payment_type_name'] ?? '',
                $row['iid'] ?? '',
            ];
        }

        if ($this->totals) {
            $t = (object) $this->totals;
            $formatted[] = [
                'TOTAL',
                '',
                '',
                '',
                $t->total_product_value ?? 0,
                $t->total_discount ?? 0,
                $t->total_sub_total ?? 0,
                $t->total_courier_charge ?? 0,
                $t->total_cod_charge ?? 0,
                $t->total_net_total ?? 0,
                '',
                '',
            ];
        }

        return $formatted;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Order No',
            'Customer',
            'Location',
            'Product Value',
            'Discount',
            'Sub Total',
            'Courier Charge',
            'COD Charge',
            'Net Total',
            'Payment',
            'Type',
        ];
    }

    public function title(): string
    {
        return 'Web Sales Report';
    }
}
