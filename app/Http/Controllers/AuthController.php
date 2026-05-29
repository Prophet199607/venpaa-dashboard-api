<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request) {
        $user = User::create([
            'name' => $request->name,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json(['user'=>$user, 'token'=>$token]);
    }

    public function login(Request $request) {
        $user = User::where('name', $request->name)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message'=>'Invalid credentials'], 401);
        }

        // Restrict login to assigned location only (Admins and Super Admins can bypass and update their current location)
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            if ($request->location) {
                $user->location = $request->location;
                $user->save();
            }
        } elseif ($user->location) {
            if ($request->location !== $user->location) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized location. You can only log in to your assigned location (' . $user->location . ').'
                ], 403);
            }
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request) {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request) {
        $user = $request->user()->load('roles');

        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
