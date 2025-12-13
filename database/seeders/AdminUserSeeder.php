<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create or update an admin user with a known username
        User::updateOrCreate(
            [ 'name' => 'admin' ],
            [
                // email may be nullable in controller usage; set a default safe value if column exists
                'email' => 'admin@example.com',
                'password' => Hash::make('2025'),
                'location' => '001'
            ]
        );
    }
}


