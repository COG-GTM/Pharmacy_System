<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAuthUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');
        return $user;
    }

    public function test_stripe_get_renders_view()
    {
        $user = $this->createAuthUser();
        $order = Order::factory()->create(['status' => 'WaitingForUserConfirmation']);

        $response = $this->actingAs($user)->get("/stripe/{$order->id}");
        $response->assertStatus(200);
        $response->assertViewIs('stripe');
    }

    public function test_stripe_post_confirms_waiting_order()
    {
        $user = $this->createAuthUser();
        $order = Order::factory()->create(['status' => 'WaitingForUserConfirmation']);

        $response = $this->actingAs($user)->post('/stripe', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('Confirmed', $order->status);
    }

    public function test_stripe_post_on_canceled_order()
    {
        $user = $this->createAuthUser();
        $order = Order::factory()->create(['status' => 'Canceled']);

        $response = $this->actingAs($user)->post('/stripe', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('actions.confirm');
    }

    public function test_stripe_post_on_delivered_order()
    {
        $user = $this->createAuthUser();
        $order = Order::factory()->create(['status' => 'Delivered']);

        $response = $this->actingAs($user)->post('/stripe', [
            'order_id' => $order->id,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('actions.confirm');
    }
}
