<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Events\UserRegistered;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'role' => 'sometimes|in:admin,pharmacy,doctor,client',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $role = $request->input('role', 'client');
        $user->assignRole($role);

        event(new UserRegistered($user, $role));

        return response()->json([
            'message' => 'User registered successfully',
            'data' => $user,
        ], 201);
    }

    public function getToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        User::where('id', $user->id)->update(['last_login' => Carbon::now()]);

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function resend($id)
    {
        $user = User::findOrFail($id);
        if ($user->email_verified_at) {
            return response()->json('User already has verified email!', 422);
        }
        $user->sendEmailVerificationNotification();
        return response()->json('The email verification has been resubmitted');
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|in:admin,pharmacy,doctor,client',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return response()->json(['message' => 'User created', 'data' => $user], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->only(['name', 'email']));
        return response()->json(['message' => 'User updated', 'data' => $user]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }

    public function ban($id, Request $request)
    {
        $user = User::findOrFail($id);
        $user->ban(['comment' => $request->input('comment', 'Banned by admin')]);
        return response()->json(['message' => 'User banned']);
    }

    public function unban($id)
    {
        $user = User::findOrFail($id);
        $user->unban();
        return response()->json(['message' => 'User unbanned']);
    }
}
