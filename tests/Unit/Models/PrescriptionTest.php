<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_image_for_an_order()
    {
        $order = Order::factory()->create();

        $prescription = Prescription::create([
            'order_id' => $order->id,
            'image' => 'image-scan.jpg',
        ]);

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'order_id' => $order->id,
            'image' => 'image-scan.jpg',
        ]);
    }

    public function test_it_belongs_to_an_order()
    {
        $order = Order::factory()->create();
        $prescription = Prescription::factory()->create(['order_id' => $order->id]);

        $this->assertTrue($prescription->order->is($order));
    }
}
