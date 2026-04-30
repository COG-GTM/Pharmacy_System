<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;
use Webpatser\Countries\Countries;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::all();
        $countries = Countries::all();
        return response()->json(['areas' => $areas, 'countries' => $countries]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'required|string',
            'country_id' => 'required|exists:countries,id',
        ]);

        $area = Area::create($request->only(['name', 'address', 'country_id']));
        return response()->json(['message' => 'Area created', 'data' => $area], 201);
    }

    public function show($id)
    {
        $area = Area::findOrFail($id);
        return response()->json($area);
    }

    public function update(Request $request, $id)
    {
        $area = Area::findOrFail($id);
        $area->update($request->only(['name', 'address', 'country_id']));
        return response()->json(['message' => 'Area updated', 'data' => $area]);
    }

    public function destroy($id)
    {
        $area = Area::findOrFail($id);
        $area->delete();
        return response()->json(['message' => 'Area deleted', 'data' => $area]);
    }
}
