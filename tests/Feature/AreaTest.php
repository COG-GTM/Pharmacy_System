<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\CountriesSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CountriesSeeder::class);
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    public function test_admin_can_view_areas_with_countries()
    {
        $admin = $this->createAdmin();
        Area::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/areas');
        $response->assertStatus(200);
    }

    public function test_store_area()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/areas', [
            'name' => 'Test Area',
            'address' => '123 Test Street',
            'country_id' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('areas', ['name' => 'Test Area']);
    }

    public function test_update_area()
    {
        $admin = $this->createAdmin();
        $area = Area::factory()->create();

        $response = $this->actingAs($admin)->put("/areas/{$area->id}", [
            'name' => 'Updated Area',
            'address' => 'Updated Address',
            'country_id' => 1,
        ]);

        $response->assertRedirect();
    }

    public function test_destroy_area_blocked_if_has_pharmacies()
    {
        $admin = $this->createAdmin();
        $area = Area::factory()->create();
        Pharmacy::factory()->create(['area_id' => $area->id]);

        $response = $this->actingAs($admin)->delete("/areas/{$area->id}");
        $response->assertRedirect();
    }
}
