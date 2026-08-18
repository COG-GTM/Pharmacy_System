<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
    }

    private function authenticate(): void
    {
        Sanctum::actingAs($this->client->user);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->getJson('/api/address')->assertUnauthorized();
    }

    public function test_index_returns_only_the_addresses_of_the_authenticated_client()
    {
        $this->authenticate();
        $own = Address::factory()->create(['client_id' => $this->client->id]);
        $other = Address::factory()->create();

        $response = $this->getJson('/api/address')->assertOk();

        $ids = array_column($response->json('data'), 'Address_id');
        $this->assertContains($own->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_index_returns_404_when_the_client_has_no_addresses()
    {
        $this->authenticate();

        $this->getJson('/api/address')
            ->assertNotFound()
            ->assertJsonPath('message', 'This User does not have any addresses');
    }

    public function test_show_returns_the_address_and_404_for_an_address_of_another_client()
    {
        $this->authenticate();
        $own = Address::factory()->create(['client_id' => $this->client->id, 'is_main' => true]);
        $other = Address::factory()->create();

        $this->getJson("/api/address/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.Address_id', $own->id)
            ->assertJsonPath('data.Main_street', 'yes');

        $this->getJson("/api/address/{$other->id}")->assertNotFound();
    }

    public function test_store_persists_an_address_for_the_authenticated_client()
    {
        $this->authenticate();
        $area = Area::factory()->create();

        $this->postJson('/api/address', [
            'area_id' => $area->id,
            'street_name' => 'Tahrir',
            'building_number' => 12,
            'floor_number' => 3,
            'flat_number' => 4,
            'is_main' => true,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Address added successfully')
            ->assertJsonPath('data.Street_name', 'Tahrir');

        $this->assertDatabaseHas('addresses', [
            'client_id' => $this->client->id,
            'street_name' => 'Tahrir',
            'building_number' => 12,
        ]);
    }

    public function test_store_rejects_an_unknown_area()
    {
        $this->authenticate();

        $this->postJson('/api/address', [
            'area_id' => 999999,
            'street_name' => 'Tahrir',
            'building_number' => 12,
            'floor_number' => 3,
            'flat_number' => 4,
            'is_main' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('area_id');
    }

    public function test_update_changes_the_address()
    {
        $this->authenticate();
        $address = Address::factory()->create(['client_id' => $this->client->id]);

        $this->putJson("/api/address/{$address->id}", [
            'area_id' => $address->area_id,
            'street_name' => 'New Street',
            'building_number' => 99,
            'floor_number' => 1,
            'flat_number' => 2,
            'is_main' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Address updated successfully');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street_name' => 'New Street',
            'building_number' => 99,
        ]);
    }

    public function test_destroy_deletes_own_address_and_404s_for_another_clients_address()
    {
        $this->authenticate();
        $own = Address::factory()->create(['client_id' => $this->client->id]);
        $other = Address::factory()->create();

        $this->deleteJson("/api/address/{$own->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Address deleted successfully');
        $this->assertDatabaseMissing('addresses', ['id' => $own->id]);

        $this->deleteJson("/api/address/{$other->id}")->assertNotFound();
        $this->assertDatabaseHas('addresses', ['id' => $other->id]);
    }
}
