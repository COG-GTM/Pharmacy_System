<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ChangeOrderStatusJob;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeOrderStatusJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_confirmed_orders_as_delivered()
    {
        $confirmed = Order::factory()->status('Confirmed')->create();
        $processing = Order::factory()->status('Processing')->create();

        (new ChangeOrderStatusJob)->handle();

        $this->assertSame('Delivered', $confirmed->fresh()->status);
        $this->assertSame('Processing', $processing->fresh()->status);
    }

    public function test_it_does_nothing_when_there_are_no_confirmed_orders()
    {
        $order = Order::factory()->status('New')->create();

        (new ChangeOrderStatusJob)->handle();

        $this->assertSame('New', $order->fresh()->status);
    }
}
