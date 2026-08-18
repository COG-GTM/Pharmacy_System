<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_payment_method_for_an_order()
    {
        $order = Order::factory()->create();

        $payment = Payment::create(['method' => 'card', 'order_id' => $order->id]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'method' => 'card',
            'order_id' => $order->id,
        ]);
    }

    public function test_only_fillable_attributes_are_mass_assigned()
    {
        $payment = Payment::factory()->create();

        $this->assertEqualsCanonicalizing(['method', 'order_id'], $payment->getFillable());
    }
}
