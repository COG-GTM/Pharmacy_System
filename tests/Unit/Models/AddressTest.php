<?php

namespace Tests\Unit\Models;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_the_fillable_attributes()
    {
        $address = Address::factory()->create([
            'street_name' => 'Gomhoreya',
            'building_number' => 5,
            'is_main' => true,
        ]);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street_name' => 'Gomhoreya',
            'building_number' => 5,
            'is_main' => true,
        ]);
    }

    public function test_it_belongs_to_a_client_and_an_area()
    {
        $client = Client::factory()->create();
        $area = Area::factory()->create();

        $address = Address::factory()->create([
            'client_id' => $client->id,
            'area_id' => $area->id,
        ]);

        $this->assertTrue($address->client->is($client));
        $this->assertTrue($address->area->is($area));
    }

    public function test_it_has_many_delivered_orders()
    {
        $address = Address::factory()->create();
        $order = Order::factory()->create(['delivering_address_id' => $address->id]);
        Order::factory()->create();

        $this->assertEquals([$order->id], $address->order->pluck('id')->all());
    }
}
