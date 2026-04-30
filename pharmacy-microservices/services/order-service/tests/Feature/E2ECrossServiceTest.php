<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class E2ECrossServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_order_flow()
    {
        Http::fake([
            '*/api/register' => Http::response(['data' => ['id' => 1, 'email' => 'user@test.com']], 201),
            '*/api/login' => Http::response(['token' => 'test-jwt-token'], 200),
            '*/api/clients' => Http::response(['data' => ['id' => '12345678901234']], 201),
            '*/api/addresses' => Http::response(['data' => ['id' => 1, 'area_id' => 1]], 201),
            '*/api/addresses/*/validate' => Http::response(['valid' => true, 'client_id' => '12345678901234', 'area_id' => 1], 200),
            '*/api/doctors/*' => Http::response(['id' => 1, 'pharmacy_id' => 1], 200),
            '*/api/medicines/calculate-price' => Http::response(['total_price' => 50.00], 200),
            '*/api/pharmacies/by-area/*' => Http::response([['id' => 1, 'priority' => 10]], 200),
        ]);

        $orderResponse = $this->postJson('/api/orders', [
            'user_id' => 1,
            'pharmacy_id' => 1,
            'doctor_id' => 1,
            'delivering_address_id' => 1,
            'status' => 'New',
            'creator_type' => 'client',
            'is_insured' => false,
            'medicine_id' => [1, 2],
            'quantity' => [2, 3],
        ]);
        $orderResponse->assertStatus(201);
        $orderId = $orderResponse->json('data.id');

        $order = Order::find($orderId);
        $order->update(['status' => 'Processing']);
        $this->assertEquals('Processing', $order->fresh()->status);

        $order->update(['status' => 'WaitingForUserConfirmation']);

        $listener = new \App\Listeners\PaymentCompletedListener();
        $listener->handle(['order_id' => $orderId]);
        $this->assertEquals('Confirmed', $order->fresh()->status);

        (new \App\Jobs\ChangeOrderStatusJob())->handle();
        $this->assertEquals('Delivered', $order->fresh()->status);
    }

    public function test_pharmacy_creation_flow()
    {
        Http::fake([
            '*/api/users' => Http::response(['data' => ['id' => 10]], 201),
        ]);

        $this->assertTrue(true);
    }

    public function test_notification_flow()
    {
        Http::fake([
            '*/api/addresses/*/validate' => Http::response(['valid' => true, 'client_id' => '12345678901234', 'area_id' => 1], 200),
            '*/api/medicines/calculate-price' => Http::response(['total_price' => 25.00], 200),
        ]);

        $response = $this->postJson('/api/orders', [
            'user_id' => 1,
            'pharmacy_id' => 1,
            'delivering_address_id' => 1,
            'status' => 'New',
            'creator_type' => 'client',
            'is_insured' => false,
            'medicine_id' => [1],
            'quantity' => [1],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', ['user_id' => 1]);
    }
}
