<?php

namespace App\Http\Controllers;

use App\Models\OrderStats;
use App\Models\RevenueSummary;
use Illuminate\Http\Request;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $query = OrderStats::where('status', 'Delivered');

        if ($request->has('pharmacy_id')) {
            $query->where('pharmacy_id', $request->pharmacy_id);
        }

        $revenue = $query->selectRaw('pharmacy_id, COUNT(*) as total_orders, SUM(price) as total_revenue')
            ->groupBy('pharmacy_id')
            ->get();

        return response()->json($revenue);
    }
}
