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

        // Modules and their actions
        $masterModules = [
            'department', 'supplier', 'location', 'product', 'customer', 'price-level', 
            'publisher', 'author', 'book', 'magazine', 'book-type', 'category', 
            'sub-category', 'sub-category-l2', 'language', 'bin-card'
        ];

        $userManagementModules = [
            'user', 'role', 'permission'
        ];

        $salesOperationModules = [
            'cashier', 'salesman'
        ];

        $transactionModules = [
            'item-request', 'purchase-order', 'good-receive-note', 'supplier-return-note', 
            'stock-adjustment', 'transfer-good-note', 'accept-good-note', 'transfer-good-return', 
            'product-discard', 'invoice'
        ];

        $paymentModules = [
            'advance-payment', 'customer-receipt', 'payment-voucher'
        ];

        $reportModules = [
            'pos-sales-summary-report', 'daily-collection-report', 'current-stock-report'
        ];

        $allPermissionsList = [];

        // Master Data Permissions (View, Create, Edit Only)
        foreach ($masterModules as $module) {
            $allPermissionsList[] = "view $module";
            if ($module !== 'language' && $module !== 'bin-card') {
                $allPermissionsList[] = "create $module";
                $allPermissionsList[] = "edit $module";
            }
            
            // Special case for bin-card export
            if ($module === 'bin-card') {
                $allPermissionsList[] = "export bin-card";
            }
        }

        // User Management Permissions
        foreach ($userManagementModules as $module) {
            $allPermissionsList[] = "view $module";
            $allPermissionsList[] = "create $module";
            $allPermissionsList[] = "edit $module";
        }

        // Sales Operation Permissions
        foreach ($salesOperationModules as $module) {
            $allPermissionsList[] = "view $module";
            $allPermissionsList[] = "create $module";
            $allPermissionsList[] = "edit $module";
        }

        // Transaction Permissions (View, Create, Edit, Print Only)
        foreach ($transactionModules as $module) {
            $allPermissionsList[] = "view $module";
            if ($module !== 'accept-good-note') {
                $allPermissionsList[] = "create $module";
            }
            $allPermissionsList[] = "edit $module";
            $allPermissionsList[] = "print $module";
        }

        // Payment Permissions (View, Create, Edit, Print Only)
        foreach ($paymentModules as $module) {
            $allPermissionsList[] = "view $module";
            $allPermissionsList[] = "create $module";
            $allPermissionsList[] = "edit $module";
            $allPermissionsList[] = "print $module";
        }

        // Report Permissions
        foreach ($reportModules as $module) {
            $allPermissionsList[] = "view $module";
        }

        // System/Other Permissions
        $otherPermissions = [
            'view dashboard stats',
            'permission assign',
            'manage discount',
            'process day-end',
            'manage-pending-item-request',
            'manage-open-stock'
        ];

        $allPermissionsList = array_merge($allPermissionsList, $otherPermissions);

        // 2️⃣ Create or Update permissions
        foreach ($allPermissionsList as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => $guard,
            ]);
        }

        Permission::where('guard_name', $guard)
            ->whereNotIn('name', $allPermissionsList)
            ->delete();

        // 3️⃣ Create roles and assign permissions
        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $guard,
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $adminPermissions = Permission::where('guard_name', $guard)
            ->whereNotIn('name', [
                'manage role',
                'manage permission',
                'create role',
                'edit role',
                'delete role',
                'create permission',
                'edit permission',
                'delete permission',
                'view permission'
            ])
            ->get();

        $allPerms = Permission::where('guard_name', $guard)->get();
        
        $superAdmin->syncPermissions($allPerms);
        $admin->syncPermissions($adminPermissions);

        // 4️⃣ Ensure initial users have roles
        $user = User::where('name', 'Super Admin')->first();
        if ($user) {
            $user->assignRole($superAdmin);
        }

        $user1 = User::where('name', 'Admin')->first();
        if ($user1) {
            $user1->assignRole($admin);
        }
    }
}
