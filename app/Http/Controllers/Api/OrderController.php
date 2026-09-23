<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AssignNewOrder;
use App\Models\Area;
use App\Models\Prescription;
use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Pharmacy;
use App\Support\ImageUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\OrderResource;
use Throwable;


class OrderController extends Controller
{
    public function index()
    {
        $client = auth()->user();
        $orders = Order::where('user_id', $client->id)->get();
        return OrderResource::collection($orders);
    }

    public function create(Request $request)
    {
        $orders = Order::where('status', "New")->get();
        $client = auth()->user();
        $delivering_address_id = $request->input('delivering_address_id');
        $is_insured = $request->input('is_insured');
        $prescriptions = $request->input('prescriptions');
        $addresses = Address::where('client_id', $client->Client->id)->get();
        if ($addresses->find($delivering_address_id)) {
            if ($request->hasFile('prescriptions')) {
                $request->validate([
                    'prescriptions.*' => ['image', 'mimes:jpeg,png', 'max:4096'],
                ]);
                $prescription_names = $this->storePrescriptions($request->file('prescriptions'));
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
                try {
                    DB::transaction(function () use ($order, $prescription_names) {
                        $order->save();
                        $this->savePrescriptions($order->id, $prescription_names);
                    });
                } catch (Throwable $e) {
                    $this->deletePrescriptionFiles($prescription_names);
                    throw $e;
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
        $order = Order::find($id);
        $order_prescriptions = Prescription::where('order_id', $id)->get();
        return response()->json([
            'message' => 'Order details',
            'data' => new OrderResource($order),
            'prescriptions' => $order_prescriptions
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if ($order->status == "New") { //New Order
            if ($request->hasFile('prescriptions')) {
                $request->validate([
                    'prescriptions.*' => ['image', 'mimes:jpeg,png', 'max:4096'],
                ]);
                $prescription_names = $this->storePrescriptions($request->file('prescriptions'));
                $superseded = Prescription::where("order_id", $id)->pluck('image')->all();
                try {
                    DB::transaction(function () use ($id, $prescription_names) {
                        Prescription::where("order_id", $id)->delete();
                        $this->savePrescriptions($id, $prescription_names);
                    });
                } catch (Throwable $e) {
                    $this->deletePrescriptionFiles($prescription_names);
                    throw $e;
                }
                $this->deletePrescriptionFiles($superseded);
            }

        }
        return response()->json([
            'message' => 'Order updated successfully',
            'data' =>new OrderResource($order)
        ], 200);


    }

    /**
     * Write every uploaded prescription to disk up front, removing the files
     * already written if a later one fails.
     *
     * @param  array  $prescriptions
     * @return array  the stored file names
     */
    private function storePrescriptions(array $prescriptions)
    {
        $names = [];
        try {
            foreach ($prescriptions as $prescription) {
                $names[] = ImageUpload::store($prescription, 'public/images/prescriptions', 'prescriptions');
            }
        } catch (Throwable $e) {
            $this->deletePrescriptionFiles($names);
            throw $e;
        }

        return $names;
    }

    private function savePrescriptions($order_id, array $names)
    {
        foreach ($names as $name) {
            $prescription = new Prescription([
                'order_id' => $order_id,
                'image' => $name,
            ]);
            $prescription->save();
        }
    }

    private function deletePrescriptionFiles(array $names)
    {
        foreach ($names as $name) {
            Storage::delete('public/images/prescriptions/' . $name);
        }
    }



}
