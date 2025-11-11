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

        $guard = 'sanctum';

        // 2️⃣ Create permissions
        $permissions = [
            'books',
            'create book',
            'edit book',
            'delete book',
            'view book',
            'products',
            'create product',
            'edit product',
            'delete product',
            'view product',
            'locations',
            'create location',
            'edit location',
            'delete location',
            'view location',
            'suppliers',
            'create supplier',
            'edit supplier',
            'delete supplier',
            'view supplier',
            'authors',
            'create author',
            'edit author',
            'delete author',
            'view author',
            'publishers',
            'create publisher',
            'edit publisher',
            'delete publisher',
            'view publisher',
            'create invoice',
            'edit invoice',
            'delete invoice',
            'view reports',
            'manage users',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => $guard,
            ]);
        }

        // 3️⃣ Create roles and assign permissions
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);
        $staffRole = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => $guard,
        ]);

        $adminRole->givePermissionTo(Permission::where('guard_name', $guard)->get());
        $staffRole->givePermissionTo(['create invoice', 'edit invoice']);

        // 4️⃣ Assign roles to users (optional)
        // Make sure you have at least one user in your DB first
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('admin');
        }
    }
}
