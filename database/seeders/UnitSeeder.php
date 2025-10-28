<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['unit_name' => 'NOS', 'unit_type' => 'WHOLE',],
            ['unit_name' => 'KG', 'unit_type' => 'DEC',],
            ['unit_name' => 'LITRE', 'unit_type' => 'DEC',],
            ['unit_name' => 'YARD', 'unit_type' => 'DEC',],

        ];

        foreach ($default as $key => $value) {
            Unit::create($value);
        }
    }
}
