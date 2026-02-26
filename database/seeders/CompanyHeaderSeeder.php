<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompanyHeaderSeeder extends Seeder
{
    public function run()
    {
        DB::table('company_headers')->insert([
            [
                'name' => 'Venpaa (Pvt) Ltd',
                'address' => 'No 465 1/1, Galle Road, Wellawatta, Colombo 06',
                'phone' => '0766699647',
                'email' => 'venpaabookhouse@gmail.com',
                'website' => '',
                'logo' => '',
                'vat_number' => '101121917',
                'tin_number' => '',
                'links' => '',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
