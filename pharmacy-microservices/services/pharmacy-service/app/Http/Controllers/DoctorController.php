<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DoctorController extends Controller
{
    protected string $authServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = env('AUTH_SERVICE_URL', 'http://auth-service');
    }

    public function index(Request $request)
    {
        $query = Doctor::with('pharmacy');

        if ($request->has('pharmacy_id')) {
            $query->where('pharmacy_id', $request->pharmacy_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $userResponse = Http::post("{$this->authServiceUrl}/api/users", [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'doctor',
        ]);

        if (!$userResponse->successful()) {
            return response()->json(['error' => 'Failed to create user via Auth Service'], 500);
        }

        $userId = $userResponse->json('data.id');

        $avatarImage = 'default-avatar.jpg';
        if ($request->hasFile('avatar_image')) {
            $avatarImage = $request->file('avatar_image')->store('images/doctors', 'public');
        }

        $doctor = Doctor::create([
            'user_id' => $userId,
            'avatar_image' => $avatarImage,
            'pharmacy_id' => $request->pharmacy_id,
            'is_banned' => $request->input('is_banned', 0),
        ]);

        if ($request->input('is_banned')) {
            Http::post("{$this->authServiceUrl}/api/users/{$userId}/ban", [
                'comment' => 'Banned on creation',
            ]);
        }

        return response()->json(['message' => 'Doctor created', 'data' => $doctor], 201);
    }

    public function show($id)
    {
        $doctor = Doctor::with('pharmacy')->findOrFail($id);
        return response()->json($doctor);
    }

    public function update(Request $request, $id)
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->update($request->only(['avatar_image', 'pharmacy_id']));
        return response()->json(['message' => 'Doctor updated', 'data' => $doctor]);
    }

    public function destroy($id)
    {
        $doctor = Doctor::findOrFail($id);
        Http::delete("{$this->authServiceUrl}/api/users/{$doctor->user_id}");
        $doctor->delete();
        return response()->json(['message' => 'Doctor deleted']);
    }

    public function ban($id)
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->update(['is_banned' => 1, 'banned_at' => now()]);
        Http::post("{$this->authServiceUrl}/api/users/{$doctor->user_id}/ban");
        return response()->json(['message' => 'Doctor banned']);
    }

    public function unban($id)
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->update(['is_banned' => 0, 'banned_at' => null]);
        Http::post("{$this->authServiceUrl}/api/users/{$doctor->user_id}/unban");
        return response()->json(['message' => 'Doctor unbanned']);
    }
}
