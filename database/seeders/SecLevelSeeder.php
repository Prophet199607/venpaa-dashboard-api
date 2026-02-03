<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SecLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sec_levels')->insert([
            [
                'member' => 'Admin',
                'sec_level' => '21',
                'idx' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member' => 'Manager',
                'sec_level' => '15',
                'idx' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member' => 'Chief Cashier',
                'sec_level' => '12',
                'idx' => '3',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'member' => 'Cashier',
                'sec_level' => '10',
                'idx' => '4',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
