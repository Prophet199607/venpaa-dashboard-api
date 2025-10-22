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
            ['type' => 'Location', 'prefix' => 'L', 'last_id' => 4, 'length' => 3],
            ['type' => 'BookType', 'prefix' => 'BT', 'last_id' => 3, 'length' => 3],
            ['type' => 'Department', 'prefix' => '', 'last_id' => 9, 'length' => 2],
            ['type' => 'SubCategory', 'prefix' => 'SC', 'last_id' => 0, 'length' => 3],
            ['type' => 'Category', 'prefix' => '', 'last_id' => 999, 'length' => 4],
            ['type' => 'Publisher', 'prefix' => 'PUB', 'last_id' => 0, 'length' => 3],
            ['type' => 'Supplier', 'prefix' => 'SUP', 'last_id' => 0, 'length' => 3],
            ['type' => 'Product', 'prefix' => 'P', 'last_id' => 0, 'length' => 8],
            ['type' => 'Author', 'prefix' => 'AUT', 'last_id' => 0, 'length' => 3],
        ];

        foreach ($default as $key => $value) {
            DocNumber::create($value);
        }
    }
}
