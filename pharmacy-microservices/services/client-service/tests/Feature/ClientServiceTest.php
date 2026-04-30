<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_client()
    {
        $client = Client::factory()->create();

        $response = $this->getJson('/api/clients');
        $response->assertStatus(200);

        $response = $this->getJson("/api/clients/{$client->id}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/clients/{$client->id}", [
            'phone' => '01012345678',
        ]);
        $response->assertStatus(200);
    }

    public function test_crud_address()
    {
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);

        $response = $this->getJson('/api/addresses');
        $response->assertStatus(200);

        $response = $this->getJson("/api/addresses/{$address->id}");
        $response->assertStatus(200);

        $response = $this->putJson("/api/addresses/{$address->id}", [
            'street_name' => 'Updated Street',
        ]);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/addresses/{$address->id}");
        $response->assertStatus(200);
    }

    public function test_validate_address_belongs_to_client()
    {
        $client = Client::factory()->create();
        $address = Address::factory()->create(['client_id' => $client->id]);

        $response = $this->getJson("/api/addresses/{$address->id}/validate");
        $response->assertStatus(200);
        $response->assertJson([
            'valid' => true,
            'client_id' => $client->id,
        ]);
    }

    public function test_api_address_crud()
    {
        $client = Client::factory()->create();

        $response = $this->postJson('/api/addresses', [
            'client_id' => $client->id,
            'area_id' => 1,
            'street_name' => 'Test Street',
            'building_number' => 10,
            'floor_number' => 3,
            'flat_number' => 5,
            'is_main' => true,
        ]);
        $response->assertStatus(201);

        $addressId = $response->json('data.id');
        $response = $this->getJson("/api/addresses/{$addressId}");
        $response->assertStatus(200);
    }
}
