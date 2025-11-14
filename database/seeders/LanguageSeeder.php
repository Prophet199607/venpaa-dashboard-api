<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('languages')->insert([
            [
                'lang_code' => 'LNG001',
                'lang_name' => 'English',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'lang_code' => 'LNG002',
                'lang_name' => 'Tamil',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'lang_code' => 'LNG003',
                'lang_name' => 'Sinhala',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

    }
}
