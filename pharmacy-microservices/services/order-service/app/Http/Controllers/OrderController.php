<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    protected string $catalogServiceUrl;
    protected string $pharmacyServiceUrl;
    protected string $clientServiceUrl;
    protected string $geographyServiceUrl;

    public function __construct()
    {
        $this->catalogServiceUrl = env('CATALOG_SERVICE_URL', 'http://catalog-service');
        $this->pharmacyServiceUrl = env('PHARMACY_SERVICE_URL', 'http://pharmacy-service');
        $this->clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://client-service');
        $this->geographyServiceUrl = env('GEOGRAPHY_SERVICE_URL', 'http://geography-service');
    }

    public function index(Request $request)
    {
        $query = Order::with('prescriptions');

        if ($request->has('pharmacy_id')) {
            $query->where('pharmacy_id', $request->pharmacy_id);
        }
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'pharmacy_id' => 'required|integer',
            'doctor_id' => 'sometimes|integer',
            'delivering_address_id' => 'required|integer',
            'status' => 'required|in:New,Processing,WaitingForUserConfirmation,Canceled,Confirmed,Delivered',
            'creator_type' => 'required|in:client,doctor,pharmacy',
            'is_insured' => 'required|boolean',
            'medicine_id' => 'required|array',
            'quantity' => 'required|array',
        ]);

        $addressResponse = Http::get("{$this->clientServiceUrl}/api/addresses/{$request->delivering_address_id}/validate");
        if (!$addressResponse->successful()) {
            return response()->json(['error' => 'Invalid address'], 422);
        }

        if ($request->has('doctor_id')) {
            $doctorResponse = Http::get("{$this->pharmacyServiceUrl}/api/doctors/{$request->doctor_id}");
            if (!$doctorResponse->successful()) {
                return response()->json(['error' => 'Invalid doctor'], 422);
            }
            $doctor = $doctorResponse->json();
            if (isset($doctor['pharmacy_id']) && $doctor['pharmacy_id'] != $request->pharmacy_id) {
                return response()->json(['error' => 'Doctor does not belong to this pharmacy'], 422);
            }
        }

        $priceResponse = Http::post("{$this->catalogServiceUrl}/api/medicines/calculate-price", [
            'medicine_ids' => $request->medicine_id,
            'quantities' => $request->quantity,
        ]);

        $totalPrice = $priceResponse->successful() ? $priceResponse->json('total_price') : 0;

        $order = Order::create([
            'user_id' => $request->user_id,
            'pharmacy_id' => $request->pharmacy_id,
            'doctor_id' => $request->doctor_id,
            'delivering_address_id' => $request->delivering_address_id,
            'status' => $request->status,
            'creator_type' => $request->creator_type,
            'is_insured' => $request->is_insured,
            'price' => $totalPrice,
        ]);

        foreach ($request->medicine_id as $i => $medicineId) {
            OrderMedicine::create([
                'order_id' => $order->id,
                'medicine_id' => $medicineId,
                'quantity' => $request->quantity[$i],
            ]);
        }

        $this->publishEvent('order.created', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'pharmacy_id' => $order->pharmacy_id,
            'status' => $order->status,
            'price' => $order->price,
            'created_at' => $order->created_at->toIso8601String(),
        ]);

        return response()->json(['message' => 'Order created', 'data' => $order], 201);
    }

    public function show($id)
    {
        $order = Order::with('prescriptions')->findOrFail($id);

        $userData = Http::get("{$this->clientServiceUrl}/api/clients", ['user_id' => $order->user_id])->json();
        $pharmacyData = Http::get("{$this->pharmacyServiceUrl}/api/pharmacies/{$order->pharmacy_id}")->json();
        $addressData = $order->delivering_address_id
            ? Http::get("{$this->clientServiceUrl}/api/addresses/{$order->delivering_address_id}")->json()
            : null;

        return response()->json([
            'order' => $order,
            'client' => $userData,
            'pharmacy' => $pharmacyData,
            'address' => $addressData,
            'prescriptions' => $order->prescriptions,
        ]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($request->has('medicine_id') && $request->has('quantity')) {
            OrderMedicine::where('order_id', $id)->delete();

            foreach ($request->medicine_id as $i => $medicineId) {
                OrderMedicine::create([
                    'order_id' => $id,
                    'medicine_id' => $medicineId,
                    'quantity' => $request->quantity[$i],
                ]);
            }

            $priceResponse = Http::post("{$this->catalogServiceUrl}/api/medicines/calculate-price", [
                'medicine_ids' => $request->medicine_id,
                'quantities' => $request->quantity,
            ]);

            if ($priceResponse->successful()) {
                $order->price = $priceResponse->json('total_price');
            }
        }

        $order->update($request->only(['status', 'doctor_id', 'delivering_address_id', 'is_insured']));

        return response()->json(['message' => 'Order updated', 'data' => $order]);
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);
        OrderMedicine::where('order_id', $id)->delete();
        Prescription::where('order_id', $id)->delete();
        $order->delete();
        return response()->json(['message' => 'Order deleted']);
    }

    public function cancel($id)
    {
        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $order->update(['status' => 'Canceled']);

        $this->publishEvent('order.status_changed', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => 'Canceled',
            'changed_at' => now()->toIso8601String(),
        ]);

        return response()->json(['message' => 'Order canceled']);
    }

    private function publishEvent(string $event, array $data)
    {
        try {
            $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
                env('RABBITMQ_HOST', 'rabbitmq'), 5672, 'guest', 'guest'
            );
            $channel = $connection->channel();
            $channel->exchange_declare('pharmacy_events', 'topic', false, true, false);
            $msg = new \PhpAmqpLib\Message\AMQPMessage(
                json_encode(['event' => $event, 'data' => $data]),
                ['delivery_mode' => 2]
            );
            $channel->basic_publish($msg, 'pharmacy_events', $event);
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            \Log::error("Failed to publish {$event}: " . $e->getMessage());
        }
    }
}
