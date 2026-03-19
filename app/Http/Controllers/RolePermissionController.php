<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function getRoles()
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        
        $query = Role::query();
        if ($currentUser) {
            if ($currentUser->hasRole('super-admin')) {
                // Super admin can see all roles
            } elseif ($currentUser->hasRole('admin')) {
                // Admin can see all roles except super-admin and admin
                $query->whereNotIn('name', ['super-admin', 'admin']);
            } else {
                // Other users see no restricted roles by default if they have access
                $query->whereNotIn('name', ['super-admin', 'admin']);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    public function createRole(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                'unique:roles,name',
            ],
        ]);

        $role = Role::create(['name' => strtolower($validated['name'])]);
        return response()->json($role);
    }

    public function deleteRole($id)
    {
        $role = Role::findOrFail($id);

        if (strtolower($role->name) === 'admin' || strtolower($role->name) === 'super-admin') {
            return response()->json([
                'success' => false,
                'message' => 'The ' . $role->name . ' role cannot be deleted.',
            ], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted']);
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole('super-admin');

        if (strtolower($role->name) === 'super-admin' && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit super-admin role.',
            ], 403);
        }

        $newName = strtolower($request->name);

        if (strtolower($role->name) === 'admin' && $newName !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'The admin role name cannot be changed.',
            ], 403);
        }

        $role->name = $newName;
        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }

    public function getPermissions()
    {
        return response()->json([
            'success' => true,
            'data' => Permission::all()
        ]);
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
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($roleId);
        $permissions = Permission::whereIn('id', $request->input('permission_ids', []))->get();
        
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
        $user = User::with('roles.permissions')->findOrFail($userId);

        $directPermissions = $user->getDirectPermissions();
        $inheritedPermissions = $user->getPermissionsViaRoles();

        $directPermissionIds = $directPermissions->pluck('id')->values();
        $inheritedPermissionIds = $inheritedPermissions
            ->pluck('id')
            ->reject(function ($id) use ($directPermissionIds) {
                return $directPermissionIds->contains($id);
            })
            ->unique()
            ->values();

        $rolePermissionsMap = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $rolePermissionsMap[$permission->id]['roles'][] = $role->name;
            }
        }

        $allPermissions = $directPermissions
            ->merge($inheritedPermissions)
            ->unique('id')
            ->values()
            ->map(function ($permission) use ($directPermissionIds, $rolePermissionsMap) {
                $isDirect = $directPermissionIds->contains($permission->id);
                $roles = $rolePermissionsMap[$permission->id]['roles'] ?? [];

                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                    'source' => $isDirect ? 'direct' : 'role',
                    'roles' => array_values(array_unique($roles)),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $allPermissions,
                'direct_permission_ids' => $directPermissionIds->toArray(),
                'inherited_permission_ids' => $inheritedPermissionIds->toArray(),
            ],
        ]);
    }

    /**
     * Assign permissions directly to a user
     */
    public function assignPermissionsToUser(Request $request, $userId)
    {
        $request->validate([
            'permission_ids' => 'present|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $user = User::findOrFail($userId);
        $permissions = Permission::whereIn('id', $request->input('permission_ids', []))->get();
        
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
            'role_ids' => 'present|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = User::findOrFail($userId);
        $roles = Role::whereIn('id', $request->input('role_ids', []))->get();

        /** @var \App\Models\User $currentUser */
        $currentUser = auth()->user();
        if (!$currentUser || !$currentUser->hasRole('super-admin')) {
            foreach ($roles as $role) {
                if (in_array(strtolower($role->name), ['super-admin', 'admin'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to assign restricted roles.',
                    ], 403);
                }
            }
        }
        
        $user->syncRoles($roles);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned to user successfully',
            'data' => $user->roles,
        ]);
    }
}
