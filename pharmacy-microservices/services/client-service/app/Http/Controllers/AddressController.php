<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $query = Address::query();
        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'area_id' => 'required|integer',
            'street_name' => 'required|string',
            'building_number' => 'required|integer',
            'floor_number' => 'required|integer',
            'flat_number' => 'required|integer',
            'is_main' => 'boolean',
        ]);

        if ($request->input('is_main')) {
            Address::where('client_id', $request->client_id)->update(['is_main' => 0]);
        }

        $address = Address::create($request->all());
        return response()->json(['message' => 'Address created', 'data' => $address], 201);
    }

    public function show($id)
    {
        return response()->json(Address::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $address = Address::findOrFail($id);
        $address->update($request->all());
        return response()->json(['message' => 'Address updated', 'data' => $address]);
    }

    public function destroy($id)
    {
        Address::findOrFail($id)->delete();
        return response()->json(['message' => 'Address deleted']);
    }

    public function validate_address($id)
    {
        $address = Address::findOrFail($id);
        return response()->json([
            'valid' => true,
            'client_id' => $address->client_id,
            'area_id' => $address->area_id,
        ]);
    }
}
