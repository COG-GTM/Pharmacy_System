<?php

namespace Tests\Feature\Web;

use App\Models\Area;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class PharmacyControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('pharmacy', 'web');
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/pharmacies')->assertRedirect('/login');
    }

    public function test_a_doctor_may_not_manage_pharmacies()
    {
        $this->actingAsRole('doctor');

        $this->get('/pharmacies')->assertForbidden();
    }

    public function test_an_admin_sees_the_pharmacies_index()
    {
        $this->withoutVite();
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create();

        $this->get('/pharmacies')
            ->assertOk()
            ->assertViewIs('pharmacy.index')
            ->assertViewHas('areas', fn ($areas) => $areas->contains($pharmacy->area));
    }

    public function test_show_returns_the_pharmacy_with_its_areas_and_user()
    {
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create(['pharmacy_name' => 'Nile Pharmacy']);

        $this->get("/pharmacies/{$pharmacy->id}")
            ->assertOk()
            ->assertJsonPath('pharmacy.pharmacy_name', 'Nile Pharmacy')
            ->assertJsonPath('user.id', $pharmacy->user_id);
    }

    public function test_store_creates_a_pharmacy_with_a_user_and_the_pharmacy_role()
    {
        $this->actingAsRole('admin');
        $area = Area::factory()->create();

        $this->post('/pharmacies', [
            'id' => '29001011234567',
            'pharmacy_name' => 'Nile Pharmacy',
            'name' => 'Owner Name',
            'email' => 'owner@example.com',
            'password' => 'secret123',
            'area_id' => $area->id,
            'priority' => 3,
        ])
            ->assertRedirect(route('pharmacies.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('pharmacy'));
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('pharmacies', [
            'id' => '29001011234567',
            'user_id' => $user->id,
            'area_id' => $area->id,
            'priority' => 3,
        ]);
    }

    public function test_store_rejects_a_short_pharmacy_id()
    {
        $this->actingAsRole('admin');
        $area = Area::factory()->create();

        $this->post('/pharmacies', [
            'id' => '123',
            'pharmacy_name' => 'Nile Pharmacy',
            'name' => 'Owner Name',
            'email' => 'owner@example.com',
            'password' => 'secret123',
            'area_id' => $area->id,
            'priority' => 3,
        ])->assertSessionHasErrors('id');

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }

    public function test_update_changes_the_pharmacy_owner_details()
    {
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create();
        $pharmacy->user->assignRole('pharmacy');

        $this->put("/pharmacies/{$pharmacy->id}", [
            'id' => $pharmacy->id,
            'pharmacy_name' => 'Renamed Pharmacy',
            'name' => 'Renamed Owner',
            'email' => 'renamed@example.com',
            'password' => 'secret123',
            'area_id' => $pharmacy->area_id,
            'priority' => 9,
        ])
            ->assertRedirect(route('pharmacies.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $pharmacy->user_id, 'name' => 'Renamed Owner']);
        $this->assertDatabaseHas('pharmacies', [
            'id' => $pharmacy->id,
            'pharmacy_name' => 'Renamed Pharmacy',
            // The owner is a pharmacy user, so priority stays under admin control.
            'priority' => $pharmacy->priority,
        ]);
    }

    public function test_destroy_refuses_to_delete_a_pharmacy_with_open_orders()
    {
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create();
        Order::factory()->status('Processing')->create(['pharmacy_id' => $pharmacy->id]);

        $this->delete("/pharmacies/{$pharmacy->id}")
            ->assertRedirect(route('pharmacies.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
    }

    public function test_destroy_soft_deletes_a_pharmacy_without_open_orders_and_restore_brings_it_back()
    {
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create();

        $this->delete("/pharmacies/{$pharmacy->id}")->assertRedirect();
        $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);

        $this->get(route('pharmacies.restore', $pharmacy->id))->assertRedirect();
        $this->assertNotSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
    }
}
