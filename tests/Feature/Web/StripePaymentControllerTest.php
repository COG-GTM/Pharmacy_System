<?php

namespace Tests\Feature\Web;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class StripePaymentControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    public function test_guests_are_redirected_to_login()
    {
        $order = Order::factory()->create();

        $this->get("/stripe/{$order->id}")->assertRedirect('/login');
    }

    public function test_it_shows_the_payment_page_for_an_order()
    {
        $this->actingAsRole('client');
        $order = Order::factory()->create();

        $this->get("/stripe/{$order->id}")
            ->assertOk()
            ->assertViewIs('stripe')
            ->assertViewHas('order', fn ($viewOrder) => $viewOrder->is($order));
    }

    public function test_paying_an_order_awaiting_confirmation_confirms_it()
    {
        $this->actingAsRole('client');
        $order = Order::factory()->status('WaitingForUserConfirmation')->create();

        $this->post('/stripe', ['order_id' => $order->id])
            ->assertOk()
            ->assertViewIs('actions.confirm')
            ->assertViewHas('state', 'Confirmednow');

        $this->assertSame('Confirmed', $order->fresh()->status);
    }

    public function test_paying_a_canceled_order_leaves_its_status_unchanged()
    {
        $this->actingAsRole('client');
        $order = Order::factory()->status('Canceled')->create();

        $this->post('/stripe', ['order_id' => $order->id])
            ->assertOk()
            ->assertViewHas('state', 'Canceled');

        $this->assertSame('Canceled', $order->fresh()->status);
    }

    public function test_paying_an_already_delivered_order_leaves_its_status_unchanged()
    {
        $this->actingAsRole('client');
        $order = Order::factory()->status('Delivered')->create();

        $this->post('/stripe', ['order_id' => $order->id])
            ->assertOk()
            ->assertViewHas('state', 'Delivered');

        $this->assertSame('Delivered', $order->fresh()->status);
    }
}
