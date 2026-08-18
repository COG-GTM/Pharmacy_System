<?php

namespace Tests\Feature\Web;

use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class AreaControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('countries')->insert([
            'id' => 818,
            'name' => 'Egypt',
            'country_code' => '818',
            'iso_3166_2' => 'EG',
            'iso_3166_3' => 'EGY',
            'region_code' => '002',
            'sub_region_code' => '015',
        ]);
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/areas')->assertRedirect('/login');
    }

    public function test_only_admins_may_manage_areas()
    {
        $this->actingAsRole('pharmacy');

        $this->get('/areas')->assertForbidden();
    }

    public function test_show_returns_the_area_and_the_countries_as_json()
    {
        $this->actingAsRole('admin');
        $area = Area::factory()->create(['name' => 'Maadi', 'country_id' => 818]);

        $this->get("/areas/{$area->id}")
            ->assertOk()
            ->assertJsonPath('area.0.name', 'Maadi')
            ->assertJsonPath('countries.0.name', 'Egypt');
    }

    public function test_store_creates_an_area()
    {
        $this->actingAsRole('admin');

        $this->post('/areas', [
            'id' => 11311,
            'name' => 'Maadi',
            'address' => 'South Cairo',
            'country_id' => 818,
        ])
            ->assertRedirect(route('areas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('areas', ['id' => 11311, 'name' => 'Maadi']);
    }

    public function test_store_rejects_an_unknown_country()
    {
        $this->actingAsRole('admin');

        $this->post('/areas', [
            'id' => 11311,
            'name' => 'Maadi',
            'address' => 'South Cairo',
            'country_id' => 999,
        ])->assertSessionHasErrors('country_id');

        $this->assertDatabaseCount('areas', 0);
    }

    public function test_update_changes_the_area()
    {
        $this->actingAsRole('admin');
        $area = Area::factory()->create(['country_id' => 818]);

        $this->put("/areas/{$area->id}", [
            'id' => $area->id,
            'name' => 'Renamed',
            'address' => 'Somewhere',
            'country_id' => 818,
        ])
            ->assertRedirect(route('areas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('areas', ['id' => $area->id, 'name' => 'Renamed']);
    }

    public function test_destroy_deletes_the_area()
    {
        $this->actingAsRole('admin');
        $area = Area::factory()->create(['country_id' => 818]);

        $this->delete("/areas/{$area->id}")->assertRedirect(route('areas.index'));

        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }
}
