<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('locations')->insert([
            [
                'loca_code' => '001',
                'loca_name' => 'HeadBranch',
                'location_type' => 'Branch',
                'delivery_address' => 'Jaffna',
                'is_active' => 1,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'loca_code' => '002',
                'loca_name' => 'Colombo',
                'location_type' => 'Branch',
                'delivery_address' => 'Wallewatthe',
                'is_active' => 1,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'loca_code' => '003',
                'loca_name' => 'Colombo',
                'location_type' => 'Exhibition',
                'delivery_address' => 'BMICH',
                'is_active' => 1,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'loca_code' => '004',
                'loca_name' => 'Jaffna Trade Fair',
                'location_type' => 'Exhibition',
                'delivery_address' => 'Jaffna',
                'is_active' => 1,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
