<?php

namespace Tests\Unit\Models;

use App\Models\Medicine;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_persisted_medicine()
    {
        $medicine = Medicine::factory()->create();

        $this->assertDatabaseHas('medicines', ['id' => $medicine->id]);
    }

    public function test_orders_relationship_carries_the_pivot_quantity()
    {
        $medicine = Medicine::factory()->create();
        $order = Order::factory()->create();
        $order->medicines()->attach($medicine->id, ['quantity' => 3]);

        $related = $medicine->orders()->get();

        $this->assertTrue($related->contains($order));
        $this->assertSame(3, (int) $related->first()->pivot->quantity);
    }
}
