<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $default = [
            ['name' => 'Cash', 'status' => 1, 'mandatory' => 1],
            ['name' => 'Cheque', 'status' => 1, 'mandatory' => 1],
            ['name' => 'Bank Transfer', 'status' => 1, 'mandatory' => 1],
            ['name' => 'Credit Card', 'status' => 1, 'mandatory' => 1],
            ['name' => 'Debit Card', 'status' => 1, 'mandatory' => 1],
            ['name' => 'KoKo Pay', 'status' => 1, 'mandatory' => 0],
        ];

        foreach ($default as $key => $value) {
            PaymentType::create($value);
        }
    }
}
