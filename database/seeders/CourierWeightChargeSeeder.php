<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CourierWeightCharge;

class CourierWeightChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $charges = [
            ['weight_from' => 0, 'weight_to' => 250, 'charge' => 200],
            ['weight_from' => 250, 'weight_to' => 500, 'charge' => 250],
            ['weight_from' => 500, 'weight_to' => 1000, 'charge' => 350],
            ['weight_from' => 1000, 'weight_to' => 2000, 'charge' => 400],
            ['weight_from' => 2000, 'weight_to' => 3000, 'charge' => 450],
            ['weight_from' => 3000, 'weight_to' => 4000, 'charge' => 500],
            ['weight_from' => 4000, 'weight_to' => 5000, 'charge' => 550],
            ['weight_from' => 5000, 'weight_to' => 6000, 'charge' => 600],
            ['weight_from' => 6000, 'weight_to' => 7000, 'charge' => 650],
            ['weight_from' => 7000, 'weight_to' => 8000, 'charge' => 700],
            ['weight_from' => 8000, 'weight_to' => 9000, 'charge' => 750],
            ['weight_from' => 9000, 'weight_to' => 10000, 'charge' => 800],
            ['weight_from' => 10000, 'weight_to' => 15000, 'charge' => 850],
            ['weight_from' => 15000, 'weight_to' => 20000, 'charge' => 1100],
            ['weight_from' => 20000, 'weight_to' => 25000, 'charge' => 1600],
            ['weight_from' => 25000, 'weight_to' => 30000, 'charge' => 2100],
            ['weight_from' => 30000, 'weight_to' => 35000, 'charge' => 2600],
            ['weight_from' => 35000, 'weight_to' => 40000, 'charge' => 3100],
        ];

        foreach ($charges as $charge) {
            CourierWeightCharge::create($charge);
        }
    }
}
