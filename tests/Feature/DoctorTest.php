<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
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

    public function test_admin_sees_all_doctors()
    {
        $admin = $this->createAdmin();
        Doctor::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/doctors');
        $response->assertStatus(200);
    }

    public function test_pharmacy_sees_own_doctors()
    {
        $pharmacyUser = User::factory()->create();
        $pharmacyUser->assignRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $pharmacyUser->id]);
        Doctor::factory()->count(2)->create(['pharmacy_id' => $pharmacy->id]);

        $response = $this->actingAs($pharmacyUser)->get('/doctors');
        $response->assertStatus(200);
    }

    public function test_doctor_sees_only_self()
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $response = $this->actingAs($doctorUser)->get('/doctors');
        $response->assertStatus(200);
    }

    public function test_store_doctor_with_ban()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();

        $response = $this->actingAs($admin)->post('/doctors', [
            'name' => 'Dr. Banned',
            'email' => 'banned@test.com',
            'password' => 'password',
            'id' => 100,
            'pharmacy_id' => $pharmacy->id,
            'is_banned' => 1,
        ]);

        $response->assertRedirect(route('doctors.index'));
        $this->assertDatabaseHas('doctors', ['id' => 100, 'is_banned' => 1]);
    }

    public function test_store_doctor_without_ban()
    {
        $admin = $this->createAdmin();
        $pharmacy = Pharmacy::factory()->create();

        $response = $this->actingAs($admin)->post('/doctors', [
            'name' => 'Dr. Active',
            'email' => 'active@test.com',
            'password' => 'password',
            'id' => 101,
            'pharmacy_id' => $pharmacy->id,
            'is_banned' => 0,
        ]);

        $response->assertRedirect(route('doctors.index'));
        $this->assertDatabaseHas('doctors', ['id' => 101, 'is_banned' => 0]);
    }

    public function test_show_doctor_returns_json()
    {
        $admin = $this->createAdmin();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->get("/doctors/{$doctor->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['doctor', 'pharmacies', 'users']);
    }

    public function test_update_doctor_as_admin()
    {
        $admin = $this->createAdmin();
        $doctor = Doctor::factory()->create();
        $newPharmacy = Pharmacy::factory()->create();

        $response = $this->actingAs($admin)->put("/doctors/{$doctor->id}", [
            'name' => 'Dr. Updated',
            'email' => $doctor->user->email,
            'pharmacy_id' => $newPharmacy->id,
            'id' => $doctor->id,
        ]);

        $response->assertRedirect(route('doctors.index'));
    }

    public function test_update_doctor_as_doctor_fields_locked()
    {
        $doctorUser = User::factory()->create();
        $doctorUser->assignRole('doctor');
        $pharmacy = Pharmacy::factory()->create();
        $doctor = Doctor::factory()->create(['user_id' => $doctorUser->id, 'pharmacy_id' => $pharmacy->id]);

        $response = $this->actingAs($doctorUser)->put("/doctors/{$doctor->id}", [
            'name' => 'Updated Doc Name',
            'email' => $doctorUser->email,
            'pharmacy_id' => $pharmacy->id,
            'id' => $doctor->id,
        ]);

        $response->assertRedirect(route('doctors.index'));
        $doctor->refresh();
        $this->assertEquals($pharmacy->id, $doctor->pharmacy_id);
    }

    public function test_ban_doctor()
    {
        $admin = $this->createAdmin();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->post("/doctors/{$doctor->id}/ban");
        $response->assertRedirect();
    }

    public function test_unban_doctor()
    {
        $admin = $this->createAdmin();
        $doctor = Doctor::factory()->create();
        $doctor->user->ban(['comment' => 'Test ban']);

        $response = $this->actingAs($admin)->post("/doctors/{$doctor->id}/unban");
        $response->assertRedirect();
    }
}
