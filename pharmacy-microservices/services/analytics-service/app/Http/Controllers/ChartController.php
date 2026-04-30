<?php

namespace App\Http\Controllers;

use App\Models\OrderStats;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function statusData(Request $request)
    {
        $query = OrderStats::query();

        if ($request->has('pharmacy_id')) {
            $query->where('pharmacy_id', $request->pharmacy_id);
        }

        $statuses = ['New', 'Processing', 'WaitingForUserConfirmation', 'Confirmed', 'Delivered', 'Canceled'];
        $counts = [];

        foreach ($statuses as $status) {
            $counts[] = (clone $query)->where('status', $status)->count();
        }

        return response()->json([
            'labels' => $statuses,
            'data' => $counts,
        ]);
    }
}
