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
                'name' => 'Venpura Book Store',
                'address' => '123 Main St, Anytown, USA',
                'phone' => '123-456-7890',
                'email' => 'info@venpurabookstore.com',
                'website' => 'https://venpurabookstore.com',
                'logo' => 'https://venpurabookstore.com/logo.png',
                'vat_number' => '1234567890',
                'tin_number' => '1234567890',
                'links' => 'https://venpurabookstore.com/links',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        ]);
    }
}
