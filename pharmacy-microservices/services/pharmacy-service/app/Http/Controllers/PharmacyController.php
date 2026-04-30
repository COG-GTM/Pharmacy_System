<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PharmacyController extends Controller
{
    protected string $authServiceUrl;
    protected string $orderServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = env('AUTH_SERVICE_URL', 'http://auth-service');
        $this->orderServiceUrl = env('ORDER_SERVICE_URL', 'http://order-service');
    }

    public function index()
    {
        return response()->json(Pharmacy::with('doctors')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'pharmacy_name' => 'required|string',
            'area_id' => 'required|integer',
            'priority' => 'required|integer|between:1,10',
        ]);

        $userResponse = Http::post("{$this->authServiceUrl}/api/users", [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'pharmacy',
        ]);

        if (!$userResponse->successful()) {
            return response()->json(['error' => 'Failed to create user via Auth Service'], 500);
        }

        $userId = $userResponse->json('data.id');

        $avatarImage = 'default-avatar.jpg';
        if ($request->hasFile('avatar_image')) {
            $avatarImage = $request->file('avatar_image')->store('images/pharmacies', 'public');
        }

        $pharmacy = Pharmacy::create([
            'user_id' => $userId,
            'pharmacy_name' => $request->pharmacy_name,
            'avatar_image' => $avatarImage,
            'area_id' => $request->area_id,
            'priority' => $request->priority,
        ]);

        return response()->json(['message' => 'Pharmacy created', 'data' => $pharmacy], 201);
    }

    public function show($id)
    {
        $pharmacy = Pharmacy::with('doctors')->findOrFail($id);
        return response()->json($pharmacy);
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacy::findOrFail($id);
        $pharmacy->update($request->only(['pharmacy_name', 'avatar_image', 'area_id', 'priority']));
        return response()->json(['message' => 'Pharmacy updated', 'data' => $pharmacy]);
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacy::findOrFail($id);

        $orderResponse = Http::get("{$this->orderServiceUrl}/api/orders", [
            'pharmacy_id' => $id,
            'status' => 'Processing,WaitingForUserConfirmation,Confirmed',
        ]);

        if ($orderResponse->successful() && count($orderResponse->json('data', [])) > 0) {
            return response()->json(['error' => 'Cannot delete pharmacy with active orders'], 422);
        }

        $pharmacy->doctors()->delete();
        $pharmacy->delete();

        Http::delete("{$this->authServiceUrl}/api/users/{$pharmacy->user_id}");

        return response()->json(['message' => 'Pharmacy deleted']);
    }

    public function restore($id)
    {
        $pharmacy = Pharmacy::withTrashed()->findOrFail($id);
        $pharmacy->restore();
        $pharmacy->doctors()->withTrashed()->restore();
        return response()->json(['message' => 'Pharmacy restored', 'data' => $pharmacy]);
    }

    public function byArea($areaId)
    {
        $pharmacies = Pharmacy::where('area_id', $areaId)->orderBy('priority', 'desc')->get();
        return response()->json($pharmacies);
    }
}
