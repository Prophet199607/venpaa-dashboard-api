<?php

namespace Database\Seeders;

use App\Models\DocNumber;
use Illuminate\Database\Seeder;

class DocNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['type' => 'Location', 'prefix' => '', 'last_id' => 4, 'length' => 3],
            ['type' => 'BookType', 'prefix' => 'BT', 'last_id' => 3, 'length' => 3],
            ['type' => 'Department', 'prefix' => '', 'last_id' => 9, 'length' => 2],
            ['type' => 'SubCategory', 'prefix' => 'SC', 'last_id' => 0, 'length' => 3],
            ['type' => 'Category', 'prefix' => '', 'last_id' => 999, 'length' => 4],
            ['type' => 'Publisher', 'prefix' => 'PUB', 'last_id' => 0, 'length' => 3],
            ['type' => 'Supplier', 'prefix' => 'SUP', 'last_id' => 0, 'length' => 3],
            ['type' => 'Product', 'prefix' => 'P', 'last_id' => 0, 'length' => 8],
            ['type' => 'Author', 'prefix' => 'AUT', 'last_id' => 0, 'length' => 3],
            ['type' => 'Customer', 'prefix' => 'C', 'last_id' => 0, 'length' => 5],
            ['type' => 'TempPO', 'prefix' => 'PO', 'last_id' => 0, 'length' => 8],
            ['type' => 'PO', 'prefix' => 'PO', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempIR', 'prefix' => 'IR', 'last_id' => 0, 'length' => 8],
            ['type' => 'IR', 'prefix' => 'IR', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempGRN', 'prefix' => 'GRN', 'last_id' => 0, 'length' => 8],
            ['type' => 'GRN', 'prefix' => 'GRN', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempSRN', 'prefix' => 'SRN', 'last_id' => 0, 'length' => 8],
            ['type' => 'SRN', 'prefix' => 'SRN', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempTGN', 'prefix' => 'TGN', 'last_id' => 0, 'length' => 8],
            ['type' => 'TGN', 'prefix' => 'TGN', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempAGN', 'prefix' => 'AGN', 'last_id' => 0, 'length' => 8],
            ['type' => 'AGN', 'prefix' => 'AGN', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempSTA', 'prefix' => 'STA', 'last_id' => 0, 'length' => 8],
            ['type' => 'STA', 'prefix' => 'STA', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempTGR', 'prefix' => 'TGR', 'last_id' => 0, 'length' => 8],
            ['type' => 'TGR', 'prefix' => 'TGR', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempPD', 'prefix' => 'PD', 'last_id' => 0, 'length' => 8],
            ['type' => 'PD', 'prefix' => 'PD', 'last_id' => 0, 'length' => 8],
            ['type' => 'Payment', 'prefix' => 'PMT', 'last_id' => 0, 'length' => 8],
            ['type' => 'TempINV', 'prefix' => 'INV', 'last_id' => 0, 'length' => 8],
            ['type' => 'INV', 'prefix' => 'INV', 'last_id' => 0, 'length' => 8],
            ['type' => 'Receipt', 'prefix' => 'REC', 'last_id' => 0, 'length' => 8],
            ['type' => 'CustomerReturn', 'prefix' => 'CUR', 'last_id' => 0, 'length' => 8],
            ['type' => 'CusAdavance', 'prefix' => 'CADV', 'last_id' => 0, 'length' => 8],
            ['type' => 'SupAdavance', 'prefix' => 'SADV', 'last_id' => 0, 'length' => 8],
            ['type' => 'CashRefund', 'prefix' => 'CAR', 'last_id' => 0, 'length' => 8],
            ['type' => 'CustomerSetOff', 'prefix' => 'CSOF', 'last_id' => 0, 'length' => 8],
            ['type' => 'SupplierSetOff', 'prefix' => 'SSOF', 'last_id' => 0, 'length' => 8],
        ];

        foreach ($default as $key => $value) {
            DocNumber::create($value);
        }
    }
}
