<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function getRoles()
    {
        return response()->json(Role::all());
    }

    public function createRole(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);
        $role = Role::create(['name' => $request->name]);
        return response()->json($role);
    }

    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
        return response()->json(['message' => 'Role deleted']);
    }

    public function getPermissions()
    {
        return response()->json(Permission::all());
    }

    public function createPermission(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:permissions,name']);
        $permission = Permission::create(['name' => $request->name]);
        return response()->json($permission);
    }

    public function deletePermission($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();
        return response()->json(['message' => 'Permission deleted']);
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions($roleId)
    {
        $role = Role::findOrFail($roleId);
        $permissions = $role->permissions;

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissionsToRole(Request $request, $roleId)
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($roleId);
        $permissions = Permission::whereIn('id', $request->permission_ids)->get();
        
        $role->syncPermissions($permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => $role->permissions,
        ]);
    }

    /**
     * Get permissions for a specific user
     */
    public function getUserPermissions($userId)
    {
        $user = User::findOrFail($userId);
        $permissions = $user->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Assign permissions directly to a user
     */
    public function assignPermissionsToUser(Request $request, $userId)
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $user = User::findOrFail($userId);
        $permissions = Permission::whereIn('id', $request->permission_ids)->get();
        
        $user->syncPermissions($permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned to user successfully',
            'data' => $user->getAllPermissions(),
        ]);
    }

    /**
     * Assign roles to a user
     */
    public function assignRolesToUser(Request $request, $userId)
    {
        $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = User::findOrFail($userId);
        $roles = Role::whereIn('id', $request->role_ids)->get();
        
        $user->syncRoles($roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned to user successfully',
            'data' => $user->roles,
        ]);
    }
}
