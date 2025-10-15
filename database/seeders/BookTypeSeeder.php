<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('book_types')->insert([
            [
                'bkt_code' => 'BT001',
                'bkt_name' => 'Hardcover',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'bkt_code' => 'BT002',
                'bkt_name' => 'Paperback',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'bkt_code' => 'BT003',
                'bkt_name' => 'Audio Book',
                'created_by' => 1,
                'updated_by' => 1,
                'status' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
