<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClientController extends Controller
{
    protected string $authServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = env('AUTH_SERVICE_URL', 'http://auth-service');
    }

    public function index()
    {
        return response()->json(Client::with('address')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'id' => 'required|digits:14|unique:clients,id',
            'gender' => 'required|in:Male,Female',
            'date_of_birth' => 'required|date',
            'phone' => ['required', 'regex:/^01[0-2,5]{1}[0-9]{8}$/'],
        ]);

        $userResponse = Http::post("{$this->authServiceUrl}/api/users", [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'client',
        ]);

        if (!$userResponse->successful()) {
            return response()->json(['error' => 'Failed to create user via Auth Service'], 500);
        }

        $userId = $userResponse->json('data.id');

        $client = Client::create([
            'id' => $request->id,
            'user_id' => $userId,
            'gender' => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'avatar_image' => 'default-avatar.jpg',
            'phone' => $request->phone,
        ]);

        return response()->json(['message' => 'Client created', 'data' => $client], 201);
    }

    public function show($id)
    {
        $client = Client::with('address')->findOrFail($id);
        return response()->json($client);
    }

    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);
        $client->update($request->only(['gender', 'date_of_birth', 'avatar_image', 'phone']));
        return response()->json(['message' => 'Client updated', 'data' => $client]);
    }

    public function destroy($id)
    {
        $client = Client::findOrFail($id);
        $client->address()->delete();
        Http::delete("{$this->authServiceUrl}/api/users/{$client->user_id}");
        $client->delete();
        return response()->json(['message' => 'Client deleted']);
    }
}
