<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Iid;
use App\Models\DocNumber;

class IidSeeder extends Seeder
{
    public function run()
    {
        $docNumbers = DocNumber::where('type', 'NOT LIKE', 'Temp%')
            ->where('length', 8)
            ->get();

        foreach ($docNumbers as $doc) {

            Iid::updateOrCreate(
                ['iid' => $doc->prefix],
                [
                    'type' => $doc->type,
                    'name' => $this->generateName($doc->type),
                ]
            );
        }

        $customIids = [
            ['iid' => '001',   'name' => 'Pos Sales'],
            ['iid' => 'R01',   'name' => 'Pos Return'],
            ['iid' => '001-O', 'name' => 'Pos Sales (Online)'],
            ['iid' => 'R01-O', 'name' => 'Pos Return (Online)'],
        ];

        foreach ($customIids as $item) {
            Iid::updateOrCreate(
                ['iid' => $item['iid']],
                [
                    'type' => null,
                    'name' => $item['name'],
                ]
            );
        }
    }

    private function generateName($type)
    {
        return match ($type) {
            'PO' => 'Purchase Order',
            'IR' => 'Item Request',
            'GRN' => 'Good Receive Note',
            'SRN' => 'Supplier Return Note',
            'TGN' => 'Transfer Good Note',
            'AGN' => 'Accept Good Note',
            'STA' => 'Stock Adjustment',
            'TGR' => 'Transfer Good Return',
            'PD' => 'Product Discard',
            'INV' => 'Invoice',
            'Payment' => 'Payment',
            'Receipt' => 'Receipt',
            'CustomerReturn' => 'Customer Return',
            'CusAdavance' => 'Customer Advance',
            'SupAdavance' => 'Supplier Advance',
            'CashRefund' => 'Cash Refund',
            'CustomerSetOff' => 'Customer Set Off',
            'SupplierSetOff' => 'Supplier Set Off',
            'OpenStock' => 'Opening Stock',
            default => $type,
        };
    }
}