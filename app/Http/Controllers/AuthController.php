<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'delivery_address' => 'nullable|string',
            'role_id' => 'required|integer|exists:roles,id',
        ]);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'phone' => $validated['phone'],
            'delivery_address' => $validated['delivery_address'],
        ]);

        $user->load('role');
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration Successful!',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if (! Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid Credentials',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->load('role');
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login Successful!',
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json('Logout Successful');
    }

    public function userInfo()
    {
        /** @var User $user */
        $user = Auth::user();
        $user->load('role');

        return response()->json($user);
    }
}
