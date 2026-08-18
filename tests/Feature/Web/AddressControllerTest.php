<?php

namespace Tests\Feature\Web;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/addresses')->assertRedirect('/login');
    }

    public function test_non_admins_may_not_manage_addresses()
    {
        $this->actingAsRole('pharmacy');

        $this->get('/addresses')->assertForbidden();
    }

    public function test_show_returns_the_address_as_json()
    {
        $this->actingAsRole('admin');
        $address = Address::factory()->create(['street_name' => 'Tahrir']);

        $this->get("/addresses/{$address->id}")
            ->assertOk()
            ->assertJsonPath('address.0.street_name', 'Tahrir');
    }

    public function test_store_creates_an_address_for_a_client()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();
        $area = Area::factory()->create();

        $this->post('/addresses', [
            'client_id' => $client->id,
            'area_id' => $area->id,
            'street_name' => 'Tahrir',
            'building_number' => 3,
            'floor_number' => 2,
            'flat_number' => 1,
            'is_main' => 'on',
        ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'client_id' => $client->id,
            'street_name' => 'Tahrir',
            'is_main' => 1,
        ]);
    }

    public function test_store_rejects_an_unknown_area()
    {
        $this->actingAsRole('admin');
        $client = Client::factory()->create();

        $this->post('/addresses', [
            'client_id' => $client->id,
            'area_id' => 999999,
            'street_name' => 'Tahrir',
            'building_number' => 3,
            'floor_number' => 2,
            'flat_number' => 1,
        ])->assertSessionHasErrors('area_id');

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_update_changes_the_address_and_clears_the_main_flag_when_unchecked()
    {
        $this->actingAsRole('admin');
        $address = Address::factory()->create(['is_main' => true]);

        $this->put("/addresses/{$address->id}", [
            'area_id' => $address->area_id,
            'street_name' => 'New Street',
            'building_number' => 9,
            'floor_number' => 8,
            'flat_number' => 7,
        ])
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'street_name' => 'New Street',
            'is_main' => 0,
        ]);
    }

    public function test_destroy_deletes_the_address()
    {
        $this->actingAsRole('admin');
        $address = Address::factory()->create();

        $this->delete("/addresses/{$address->id}")
            ->assertRedirect(route('clients.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }
}
