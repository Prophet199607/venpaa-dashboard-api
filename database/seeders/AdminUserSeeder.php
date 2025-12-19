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
        $admins = [
            [
                'name' => 'admin1',
                'email' => 'admin1@example.com',
                'password' => '2025',
                'location' => '001',
            ],
            [
                'name' => 'admin2',
                'email' => 'admin2@example.com',
                'password' => '2025',
                'location' => '001',
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['name' => $admin['name']],
                [
                    'email' => $admin['email'],
                    'password' => Hash::make($admin['password']),
                    'location' => $admin['location'],
                ]
            );
        }
    }
}


