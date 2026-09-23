<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Prescription;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    public function show($order, $prescription): StreamedResponse
    {
        $client_order = Order::where('id', $order)->where('user_id', auth()->id())->first();
        if (is_null($client_order)) {
            abort(404);
        }

        $client_prescription = Prescription::where('id', $prescription)
            ->where('order_id', $client_order->id)
            ->first();
        if (is_null($client_prescription)) {
            abort(404);
        }

        return $client_prescription->fileResponse();
    }
}
