<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PrescriptionUploadRequest;
use App\Jobs\AssignNewOrder;
use App\Models\Area;
use App\Models\Prescription;
use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Address;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\OrderResource;


class OrderController extends Controller
{
    public function index()
    {
        $client = auth()->user();
        $orders = Order::where('user_id', $client->id)->get();
        return OrderResource::collection($orders);
    }

    public function create(PrescriptionUploadRequest $request)
    {
        $orders = Order::where('status', "New")->get();
        $client = auth()->user();
        $delivering_address_id = $request->input('delivering_address_id');
        $is_insured = $request->input('is_insured');
        $prescriptions = $request->input('prescriptions');
        $addresses = Address::where('client_id', $client->Client->id)->get();
        if ($addresses->find($delivering_address_id)) {
            if ($request->hasFile('prescriptions')) {
                $order = new Order([
                    'delivering_address_id' => $delivering_address_id,
                    'doctor_id' => null,
                    'is_insured' => $is_insured,
                    'status' => "New",
                    'creator_type' => "client",
                    'price' => 0,
                    'user_id' => $client->id,
                    'pharmacy_id' => null,
                ]);
                $order->save();
                foreach ($request->file('prescriptions') as $prescription) {
                    Prescription::storeFor($order, $prescription);
                }
            } else {
                return response()->json([
                    'message' => 'No prescriptions',
                ], 400);
            }
        } else {
            return response()->json([
                'message' => 'address id does not belong to this user',
            ], 400);
        }

        return response()->json([
            'message' => 'Order created successfully',
            'data' => new OrderResource($order)
        ], 200);
    }

    public function show($id)
    {
        $order = $this->findOwnedOrder($id);
        if (is_null($order)) {
            return $this->orderNotFound();
        }
        $order_prescriptions = Prescription::where('order_id', $order->id)->get()->map(function (Prescription $prescription) use ($order) {
            return [
                'id' => $prescription->id,
                'url' => route('api.orders.prescriptions.show', ['order' => $order->id, 'prescription' => $prescription->id]),
            ];
        });
        return response()->json([
            'message' => 'Order details',
            'data' => new OrderResource($order),
            'prescriptions' => $order_prescriptions
        ], 200);
    }

    public function update(PrescriptionUploadRequest $request, $id)
    {
        $order = $this->findOwnedOrder($id);
        if (is_null($order)) {
            return $this->orderNotFound();
        }
        if ($order->status == "New") { //New Order
            if ($request->hasFile('prescriptions')) {
                $images = Prescription::where("order_id", $order->id)->get();
                foreach ($images as $image) {
                    $image->deleteFile();
                }
                Prescription::where("order_id", $order->id)->delete();
                foreach ($request->file('prescriptions') as $prescription) {
                    Prescription::storeFor($order, $prescription);
                }
            }

        }
        return response()->json([
            'message' => 'Order updated successfully',
            'data' =>new OrderResource($order)
        ], 200);


    }

    private function findOwnedOrder($id)
    {
        return Order::where('id', $id)->where('user_id', auth()->id())->first();
    }

    private function orderNotFound()
    {
        return response()->json([
            'message' => 'Order not found',
        ], 404);
    }



}
