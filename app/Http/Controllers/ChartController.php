<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ChartController extends Controller
{
    public function statusData(): JsonResponse
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            $orders = Order::query();
        } elseif ($user->hasRole('pharmacy')) {
            $pharmacy = Pharmacy::where('user_id', $user->id)->first()->id;
            $orders = Order::where('pharmacy_id', $pharmacy);
        } elseif ($user->hasRole('doctor')) {
            $pharmacy = Doctor::where('user_id', $user->id)->first()->pharmacy_id;
            $orders = Order::where('pharmacy_id', $pharmacy);
        }

        $counts = $orders->toBase()
            ->selectRaw('status, COUNT(*) AS order_count')
            ->groupBy('status')
            ->pluck('order_count', 'status');

        $labels = ['New', 'Processing', 'WaitingForUserConfirmation', 'Canceled', 'Delivered'];

        return response()->json([
            'labels' => $labels,
            'data' => array_map(static fn (string $label): int => (int) ($counts[$label] ?? 0), $labels),
        ]);
    }
}