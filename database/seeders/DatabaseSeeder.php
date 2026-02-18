<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\StatusSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\BookTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DocNumberSeeder;
use Illuminate\Support\Facades\Lang;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UnitSeeder::class,
            LocationSeeder::class,
            AdminUserSeeder::class,
            DocNumberSeeder::class,
            BookTypeSeeder::class,
            LanguageSeeder::class,
            StatusSeeder::class,
            RolePermissionSeeder::class,
            PaymentTypeSeeder::class,
            SecLevelSeeder::class,
            CompanyHeaderSeeder::class,
        ]);
    }
}
