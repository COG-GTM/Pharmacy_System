<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrescriptionController extends Controller
{
    public function show($order, $prescription): StreamedResponse
    {
        $order_prescription = Prescription::where('id', $prescription)
            ->where('order_id', $order)
            ->first();
        if (is_null($order_prescription)) {
            abort(404);
        }

        return $order_prescription->fileResponse();
    }
}
