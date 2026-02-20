<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CodValueCharge;

class CodValueChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $charges = [
            ['value_from' => 0, 'value_to' => 4000, 'charge' => 100],
            ['value_from' => 4000, 'value_to' => 10000, 'charge' => 130],
            ['value_from' => 10000, 'value_to' => 50000, 'charge' => 180],
            ['value_from' => 50000, 'value_to' => 100000, 'charge' => 280],
        ];

        foreach ($charges as $charge) {
            CodValueCharge::create($charge);
        }
    }
}
