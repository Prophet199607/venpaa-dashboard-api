<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // 1️⃣ Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2️⃣ Create permissions
        $permissions = [
            'create invoice',
            'edit invoice',
            'delete invoice',
            'view reports',
            'manage users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 3️⃣ Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);

        $adminRole->givePermissionTo(Permission::all());
        $staffRole->givePermissionTo(['create invoice', 'edit invoice']);

        // 4️⃣ Assign roles to users (optional)
        // Make sure you have at least one user in your DB first
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('admin');
        }
    }
}
