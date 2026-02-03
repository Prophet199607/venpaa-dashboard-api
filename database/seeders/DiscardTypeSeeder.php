<?php

namespace Database\Seeders;

use App\Models\DiscardType;
use Illuminate\Database\Seeder;

class DiscardTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DiscardType::create(['type_name' => 'Location Discard']);
        DiscardType::create(['type_name' => 'Damage Discard']);
        DiscardType::create(['type_name' => 'Expired Discard']);
    }
}
