<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_get_returns_order_info()
    {
        Http::fake([
            '*/api/orders/1' => Http::response([
                'order' => ['id' => 1, 'status' => 'WaitingForUserConfirmation', 'price' => 50],
            ], 200),
        ]);

        $response = $this->getJson('/api/stripe/1');
        $response->assertStatus(200);
        $response->assertJsonStructure(['order', 'stripe_key']);
    }

    public function test_stripe_post_publishes_payment_completed_event()
    {
        Http::fake([
            '*/api/orders/1' => Http::response([
                'order' => ['id' => 1, 'status' => 'WaitingForUserConfirmation', 'price' => 50],
            ], 200),
        ]);

        $response = $this->postJson('/api/stripe', ['order_id' => 1]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', ['order_id' => 1, 'method' => 'stripe']);
    }
}
