<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    public function test_store_address_with_is_main()
    {
        $admin = $this->createAdmin();
        $client = Client::factory()->create();
        $area = Area::factory()->create();

        $response = $this->actingAs($admin)->post('/addresses', [
            'client_id' => $client->id,
            'area_id' => $area->id,
            'street_name' => 'Main Street',
            'building_number' => '10',
            'floor_number' => '2',
            'flat_number' => '5',
            'is_main' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('addresses', ['street_name' => 'Main Street', 'is_main' => 1]);
    }

    public function test_update_address()
    {
        $admin = $this->createAdmin();
        $address = Address::factory()->create();

        $response = $this->actingAs($admin)->put("/addresses/{$address->id}", [
            'client_id' => $address->client_id,
            'area_id' => $address->area_id,
            'street_name' => 'Updated Street',
            'building_number' => '20',
            'floor_number' => '3',
            'flat_number' => '10',
            'is_main' => 0,
        ]);

        $response->assertRedirect();
    }

    public function test_show_address()
    {
        $admin = $this->createAdmin();
        $address = Address::factory()->create();

        $response = $this->actingAs($admin)->get("/addresses/{$address->id}");
        $response->assertStatus(200);
    }

    public function test_destroy_address()
    {
        $admin = $this->createAdmin();
        $address = Address::factory()->create();

        $response = $this->actingAs($admin)->delete("/addresses/{$address->id}");
        $response->assertRedirect();
    }

    public function test_api_address_crud()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);
        $area = Area::factory()->create();

        Sanctum::actingAs($user);

        $storeResponse = $this->postJson('/api/address', [
            'client_id' => $client->id,
            'area_id' => $area->id,
            'street_name' => 'API Street',
            'building_number' => '15',
            'floor_number' => '4',
            'flat_number' => '8',
            'is_main' => 0,
        ]);
        $storeResponse->assertStatus(200);

        $indexResponse = $this->getJson('/api/address');
        $indexResponse->assertStatus(200);

        $address = Address::where('street_name', 'API Street')->first();
        if ($address) {
            $showResponse = $this->getJson("/api/address/{$address->id}");
            $showResponse->assertStatus(200);

            $updateResponse = $this->putJson("/api/address/{$address->id}", [
                'street_name' => 'Updated API Street',
                'building_number' => '20',
                'floor_number' => '5',
                'flat_number' => '12',
                'is_main' => 1,
            ]);
            $updateResponse->assertStatus(200);

            $deleteResponse = $this->deleteJson("/api/address/{$address->id}");
            $deleteResponse->assertStatus(200);
        }
    }
}
