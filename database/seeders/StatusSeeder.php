<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        DB::table('statuses')->insert([
            [
                'name' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Inactive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
