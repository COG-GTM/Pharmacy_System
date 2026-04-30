<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMedicine;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_calls_catalog_for_price()
    {
        Http::fake([
            '*/api/addresses/*/validate' => Http::response(['valid' => true, 'client_id' => '12345678901234', 'area_id' => 1], 200),
            '*/api/doctors/*' => Http::response(['id' => 1, 'pharmacy_id' => 1], 200),
            '*/api/medicines/calculate-price' => Http::response(['total_price' => 25.50], 200),
        ]);

        $response = $this->postJson('/api/orders', [
            'user_id' => 1,
            'pharmacy_id' => 1,
            'doctor_id' => 1,
            'delivering_address_id' => 1,
            'status' => 'New',
            'creator_type' => 'pharmacy',
            'is_insured' => false,
            'medicine_id' => [1, 2],
            'quantity' => [2, 3],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(25.50, $response->json('data.price'));
    }

    public function test_create_order_validates_address_via_client_service()
    {
        Http::fake([
            '*/api/addresses/*/validate' => Http::response(['error' => 'not found'], 404),
        ]);

        $response = $this->postJson('/api/orders', [
            'user_id' => 1,
            'pharmacy_id' => 1,
            'delivering_address_id' => 999,
            'status' => 'New',
            'creator_type' => 'pharmacy',
            'is_insured' => false,
            'medicine_id' => [1],
            'quantity' => [1],
        ]);

        $response->assertStatus(422);
    }

    public function test_assign_new_order_calls_pharmacy_service()
    {
        Http::fake([
            '*/api/addresses/*' => Http::response(['area_id' => 1], 200),
            '*/api/pharmacies/by-area/*' => Http::response([
                ['id' => 5, 'priority' => 10],
            ], 200),
        ]);

        $order = Order::factory()->create([
            'status' => 'New',
            'pharmacy_id' => null,
            'delivering_address_id' => 1,
        ]);

        (new \App\Jobs\AssignNewOrder())->handle();

        $order->refresh();
        $this->assertEquals(5, $order->pharmacy_id);
        $this->assertEquals('Processing', $order->status);
    }

    public function test_full_order_lifecycle()
    {
        $order = Order::factory()->create(['status' => 'New']);

        $order->update(['status' => 'Processing']);
        $this->assertEquals('Processing', $order->fresh()->status);

        $order->update(['status' => 'WaitingForUserConfirmation']);
        $this->assertEquals('WaitingForUserConfirmation', $order->fresh()->status);

        $order->update(['status' => 'Confirmed']);
        $this->assertEquals('Confirmed', $order->fresh()->status);

        (new \App\Jobs\ChangeOrderStatusJob())->handle();
        $this->assertEquals('Delivered', $order->fresh()->status);
    }

    public function test_payment_completed_event_confirms_order()
    {
        $order = Order::factory()->create(['status' => 'WaitingForUserConfirmation']);

        $listener = new \App\Listeners\PaymentCompletedListener();
        $listener->handle(['order_id' => $order->id]);

        $this->assertEquals('Confirmed', $order->fresh()->status);
    }

    public function test_change_order_status_job()
    {
        Order::factory()->create(['status' => 'Confirmed']);
        Order::factory()->create(['status' => 'Confirmed']);
        Order::factory()->create(['status' => 'Processing']);

        (new \App\Jobs\ChangeOrderStatusJob())->handle();

        $this->assertEquals(2, Order::where('status', 'Delivered')->count());
        $this->assertEquals(1, Order::where('status', 'Processing')->count());
    }
}
