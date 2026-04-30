<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class AssignNewOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $pharmacyServiceUrl = env('PHARMACY_SERVICE_URL', 'http://pharmacy-service');
        $clientServiceUrl = env('CLIENT_SERVICE_URL', 'http://client-service');

        $orders = Order::where('status', 'New')->whereNull('pharmacy_id')->get();

        foreach ($orders as $order) {
            $addressResponse = Http::get("{$clientServiceUrl}/api/addresses/{$order->delivering_address_id}");
            if (!$addressResponse->successful()) {
                continue;
            }

            $areaId = $addressResponse->json('area_id');

            $pharmacyResponse = Http::get("{$pharmacyServiceUrl}/api/pharmacies/by-area/{$areaId}");
            if (!$pharmacyResponse->successful() || empty($pharmacyResponse->json())) {
                continue;
            }

            $pharmacy = $pharmacyResponse->json()[0];

            $order->update([
                'pharmacy_id' => $pharmacy['id'],
                'status' => 'Processing',
            ]);
        }
    }
}
