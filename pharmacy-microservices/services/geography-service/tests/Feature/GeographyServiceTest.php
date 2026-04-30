<?php

namespace Tests\Feature;

use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_area()
    {
        $area = Area::factory()->create();

        $response = $this->getJson('/api/areas');
        $response->assertStatus(200);

        $response = $this->getJson("/api/areas/{$area->id}");
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => $area->name]);

        $response = $this->putJson("/api/areas/{$area->id}", [
            'name' => 'Updated Area',
            'address' => 'New Address',
            'country_id' => 1,
        ]);
        $response->assertStatus(200);

        $response = $this->deleteJson("/api/areas/{$area->id}");
        $response->assertStatus(200);
    }

    public function test_countries_seeded()
    {
        $this->seed(\Database\Seeders\CountriesSeeder::class);

        $response = $this->getJson('/api/areas');
        $response->assertStatus(200);
        $response->assertJsonStructure(['areas', 'countries']);
    }
}
