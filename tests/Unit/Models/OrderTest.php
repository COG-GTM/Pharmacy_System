<?php

namespace Tests\Unit\Models;

use App\Models\Address;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_its_user_pharmacy_doctor_and_address()
    {
        $user = User::factory()->create();
        $pharmacy = Pharmacy::factory()->create();
        $doctor = Doctor::factory()->create();
        $address = Address::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'doctor_id' => $doctor->id,
            'delivering_address_id' => $address->id,
        ]);

        $this->assertTrue($order->user->is($user));
        $this->assertTrue($order->pharmacy->is($pharmacy));
        $this->assertTrue($order->doctor->is($doctor));
        $this->assertTrue($order->address->is($address));
    }

    public function test_medicines_are_attached_with_their_quantity()
    {
        $order = Order::factory()->create();
        $medicine = Medicine::factory()->create();

        $order->medicines()->attach($medicine->id, ['quantity' => 3]);

        $this->assertSame(3, (int) $order->medicines()->first()->pivot->quantity);
    }

    public function test_total_price_sums_the_medicine_prices_in_dollars()
    {
        $first = Medicine::factory()->create(['price' => 250]);
        $second = Medicine::factory()->create(['price' => 100]);

        $total = Order::totalPrice([2, 3], [$first->id, $second->id]);

        $this->assertEqualsWithDelta(8.0, $total, 0.001);
    }

    public function test_total_price_is_zero_without_medicines()
    {
        $this->assertSame(0.0, (float) Order::totalPrice([], null));
    }

    public function test_create_order_medicine_attaches_every_medicine()
    {
        $order = Order::factory()->create();
        $first = Medicine::factory()->create();
        $second = Medicine::factory()->create();

        Order::createOrderMedicine($order, [1, 4], [$first->id, $second->id]);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $order->medicines()->pluck('medicines.id')->all()
        );
        $this->assertSame(4, (int) $order->medicines()->where('medicines.id', $second->id)->first()->pivot->quantity);
    }

    public function test_update_order_medicine_attaches_the_edited_medicines()
    {
        $order = Order::factory()->create();
        $medicine = Medicine::factory()->create();

        Order::updateOrderMedicine($order, [7], [$medicine->id]);

        $this->assertSame(7, (int) $order->medicines()->first()->pivot->quantity);
    }
}
