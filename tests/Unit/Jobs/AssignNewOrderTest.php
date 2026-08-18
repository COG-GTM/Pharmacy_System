<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AssignNewOrder;
use App\Models\Address;
use App\Models\Area;
use App\Models\Order;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignNewOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_new_orders_to_the_highest_priority_pharmacy_in_their_area()
    {
        $area = Area::factory()->create();
        $address = Address::factory()->create(['area_id' => $area->id]);
        $topPharmacy = Pharmacy::factory()->create(['area_id' => $area->id, 'priority' => 10]);
        Pharmacy::factory()->create(['area_id' => $area->id, 'priority' => 1]);
        Pharmacy::factory()->create(['priority' => 99]);

        $order = Order::factory()->create([
            'status' => 'New',
            'delivering_address_id' => $address->id,
        ]);

        (new AssignNewOrder)->handle();

        $order->refresh();
        $this->assertSame($topPharmacy->id, $order->pharmacy_id);
        $this->assertSame('Processing', $order->status);
    }

    public function test_it_leaves_orders_that_are_not_new_untouched()
    {
        $area = Area::factory()->create();
        $address = Address::factory()->create(['area_id' => $area->id]);
        Pharmacy::factory()->create(['area_id' => $area->id, 'priority' => 5]);

        $pharmacy = Pharmacy::factory()->create();
        $order = Order::factory()->create([
            'status' => 'Processing',
            'pharmacy_id' => $pharmacy->id,
            'delivering_address_id' => $address->id,
        ]);

        (new AssignNewOrder)->handle();

        $order->refresh();
        $this->assertSame($pharmacy->id, $order->pharmacy_id);
        $this->assertSame('Processing', $order->status);
    }

    /**
     * Documents a live bug: with no pharmacy in the order's area the job
     * dereferences null and the order is never assigned.
     */
    public function test_it_fails_when_no_pharmacy_serves_the_area_of_the_order()
    {
        $area = Area::factory()->create();
        $address = Address::factory()->create(['area_id' => $area->id]);
        Pharmacy::factory()->create(['priority' => 10]);

        $order = Order::factory()->create([
            'status' => 'New',
            'delivering_address_id' => $address->id,
        ]);

        try {
            (new AssignNewOrder)->handle();
            $this->fail('Expected the job to fail when no pharmacy serves the area.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('on null', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame('New', $order->status);
    }
}
