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
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => 'onimta1+',
                'location' => '001',
                'role' => 'super-admin',
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => '2025',
                'location' => '001',
                'role' => 'admin',
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


