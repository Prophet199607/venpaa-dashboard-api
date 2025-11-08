<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('suppliers')->insert([
            [
                'sup_code' => 'SUP001',
                'sup_name' => 'Bharaneetharan',
                'company' => 'Jeevanathy',
                'address' => '123 Main Street, Colombo',
                'mobile' => '0741092400',
                'telephone' => '0741092400',
                'email' => 'Bharaneetharan@gmail.com',
                'description' => '',
                'sup_image' => 'suppliers/SUP001.png',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'sup_code' => 'SUP002',
                'sup_name' => 'Aathi Paarthiban',
                'company' => 'Aathi Paarthiban',
                'address' => '45 Lotus Road, Kandy',
                'mobile' => '+94770216620',
                'telephone' => '+94770216620',
                'email' => 'aathi@gmail.com',
                'description' => '',
                'sup_image' => 'suppliers/SUP002.png',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
