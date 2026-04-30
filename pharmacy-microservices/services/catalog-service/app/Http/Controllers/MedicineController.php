<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function index()
    {
        return response()->json(Medicine::all());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'quantity' => 'required|integer',
            'price' => 'required|integer',
        ]);

        $medicine = Medicine::create($request->only(['name', 'type', 'quantity', 'price']));
        return response()->json(['message' => 'Medicine created', 'data' => $medicine], 201);
    }

    public function show($id)
    {
        return response()->json(Medicine::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $medicine = Medicine::findOrFail($id);
        $medicine->update($request->only(['name', 'type', 'quantity', 'price']));
        return response()->json(['message' => 'Medicine updated', 'data' => $medicine]);
    }

    public function destroy($id)
    {
        $medicine = Medicine::findOrFail($id);
        $medicine->delete();
        return response()->json(['message' => 'Medicine deleted']);
    }

    public function calculatePrice(Request $request)
    {
        $request->validate([
            'medicine_ids' => 'required|array',
            'quantities' => 'required|array',
        ]);

        $total = Medicine::calculateTotalPrice(
            $request->input('medicine_ids'),
            $request->input('quantities')
        );

        return response()->json(['total_price' => $total]);
    }
}
