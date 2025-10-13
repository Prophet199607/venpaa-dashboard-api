<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\DocNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
            ['type' => 'Location', 'prefix' => 'L', 'last_id' => 4],
            ['type' => 'BookType', 'prefix' => 'BT', 'last_id' => 3],
            ['type' => 'Department', 'prefix' => 'DEP', 'last_id' => 0],
            ['type' => 'SubCategory', 'prefix' => 'SC', 'last_id' => 0],
            ['type' => 'Category', 'prefix' => 'CAT', 'last_id' => 0],
            ['type' => 'Publisher', 'prefix' => 'PUB', 'last_id' => 0],
        ];

        foreach ($default as $key => $value) {
            DocNumber::create($value);
        }
    }
}
