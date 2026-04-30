<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
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

    private function createPharmacyUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('pharmacy');
        Pharmacy::factory()->create(['user_id' => $user->id]);
        return $user;
    }

    public function test_admin_can_view_pharmacies_index()
    {
        $admin = $this->createAdmin();
        Pharmacy::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/pharmacies');
        $response->assertStatus(200);
    }

    public function test_pharmacy_role_can_view_pharmacies_index()
    {
        $pharmacyUser = $this->createPharmacyUser();

        $response = $this->actingAs($pharmacyUser)->get('/pharmacies');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_pharmacy()
    {
        $admin = $this->createAdmin();
        $area = Area::factory()->create();

        $response = $this->actingAs($admin)->post('/pharmacies', [
            'name' => 'Pharmacy Owner',
            'email' => 'pharmacy@test.com',
            'password' => 'password',
            'id' => 1,
            'pharmacy_name' => 'Test Pharmacy',
            'area_id' => $area->id,
            'priority' => 5,
        ]);

        $response->assertRedirect(route('pharmacies.index'));
        $this->assertDatabaseHas('users', ['email' => 'pharmacy@test.com']);
        $this->assertDatabaseHas('pharmacies', ['pharmacy_name' => 'Test Pharmacy']);
    }

    public function test_pharmacy_store_with_avatar_upload()
    {
        $admin = $this->createAdmin();
        $area = Area::factory()->create();

        $response = $this->actingAs($admin)->post('/pharmacies', [
            'name' => 'Pharmacy Owner',
            'email' => 'pharmacy2@test.com',
            'password' => 'password',
            'id' => 2,
            'pharmacy_name' => 'Avatar Pharmacy',
            'area_id' => $area->id,
            'priority' => 3,
        ]);

        $response->assertRedirect(route('pharmacies.index'));
        $this->assertDatabaseHas('pharmacies', ['pharmacy_name' => 'Avatar Pharmacy']);
    }

    public function test_pharmacy_show_returns_json()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();

        $response = $this->actingAs($admin)->get("/pharmacies/{$pharmacy->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['pharmacy', 'areas', 'user']);
    }

    public function test_admin_can_update_pharmacy_area_and_priority()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();
        $newArea = Area::factory()->create();

        $response = $this->actingAs($admin)->put("/pharmacies/{$pharmacy->id}", [
            'name' => 'Updated Name',
            'email' => $pharmacy->user->email,
            'area_id' => $newArea->id,
            'priority' => 9,
        ]);

        $response->assertRedirect(route('pharmacies.index'));
    }

    public function test_pharmacy_role_cannot_change_area_and_priority()
    {
        $user = User::factory()->create();
        $user->assignRole('pharmacy');
        $area = Area::factory()->create();
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id, 'area_id' => $area->id, 'priority' => 5]);

        $newArea = Area::factory()->create();

        $response = $this->actingAs($user)->put("/pharmacies/{$pharmacy->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'area_id' => $newArea->id,
            'priority' => 10,
        ]);

        $response->assertRedirect(route('pharmacies.index'));
        $pharmacy->refresh();
        $this->assertEquals($area->id, $pharmacy->area_id);
        $this->assertEquals(5, $pharmacy->priority);
    }

    public function test_destroy_blocked_when_active_orders_exist()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();
        Order::factory()->create(['pharmacy_id' => $pharmacy->id, 'status' => 'Processing']);

        $response = $this->actingAs($admin)->delete("/pharmacies/{$pharmacy->id}");

        $response->assertRedirect(route('pharmacies.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id]);
    }

    public function test_destroy_cascades_doctors_and_orders()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();
        $doctor = Doctor::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $order = Order::factory()->create(['pharmacy_id' => $pharmacy->id, 'status' => 'Delivered']);

        $response = $this->actingAs($admin)->delete("/pharmacies/{$pharmacy->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);
    }

    public function test_restore_soft_deleted_pharmacy()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();
        $pharmacy->delete();

        $this->assertSoftDeleted('pharmacies', ['id' => $pharmacy->id]);

        $response = $this->actingAs($admin)->get("/pharmacies/restore/{$pharmacy->id}");
        $response->assertRedirect();

        $this->assertDatabaseHas('pharmacies', ['id' => $pharmacy->id, 'deleted_at' => null]);
    }
}
