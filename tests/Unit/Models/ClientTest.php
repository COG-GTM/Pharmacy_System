<?php

namespace Tests\Unit\Models;

use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($client->user->is($user));
    }

    public function test_it_has_many_addresses()
    {
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);
        Address::factory()->create();

        $this->assertEquals([$address->id], $client->address->pluck('id')->all());
    }

    /**
     * The relation joins orders.user_id to the client primary key (the national
     * ID), so orders placed by the client's user are not returned.
     */
    public function test_orders_are_keyed_on_the_client_id_not_the_user_id()
    {
        $client = Client::factory()->create();
        Order::factory()->create(['user_id' => $client->user_id]);

        $this->assertSame('id', $client->orders()->getLocalKeyName());
        $this->assertCount(0, $client->orders);
    }

    public function test_an_unverified_client_has_no_verification_timestamp()
    {
        $client = Client::factory()->unverified()->create();

        $this->assertNull($client->email_verified_at);
    }
}
