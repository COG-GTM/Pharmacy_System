<?php

namespace Tests\Unit\Models;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMedicineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_writes_to_the_orders_medicines_pivot_table()
    {
        $order = Order::factory()->create();
        $medicine = Medicine::factory()->create();

        $pivot = OrderMedicine::create([
            'order_id' => $order->id,
            'medicine_id' => $medicine->id,
            'quantity' => 5,
        ]);

        $this->assertSame('orders_medicines', $pivot->getTable());
        $this->assertDatabaseHas('orders_medicines', [
            'order_id' => $order->id,
            'medicine_id' => $medicine->id,
            'quantity' => 5,
        ]);
    }

    public function test_the_pivot_row_is_visible_through_the_order_relation()
    {
        $row = OrderMedicine::factory()->create(['quantity' => 2]);

        $order = Order::find($row->order_id);

        $this->assertSame(2, (int) $order->medicines()->first()->pivot->quantity);
    }
}
