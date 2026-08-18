<?php

namespace Tests\Feature\Web;

use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('doctor', 'web');
    }

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/doctors')->assertRedirect('/login');
    }

    public function test_a_client_may_not_manage_doctors()
    {
        $this->actingAsRole('client');

        $this->get('/doctors')->assertForbidden();
    }

    public function test_an_admin_sees_the_doctors_index()
    {
        $this->withoutVite();
        $this->actingAsRole('admin');
        $doctor = Doctor::factory()->create();

        $this->get('/doctors')
            ->assertOk()
            ->assertViewIs('doctor.index')
            ->assertViewHas('doctors', fn ($doctors) => $doctors->contains($doctor));
    }

    public function test_show_returns_the_doctor_with_the_pharmacies_and_users()
    {
        $this->actingAsRole('admin');
        $doctor = Doctor::factory()->create();

        $this->get("/doctors/{$doctor->id}")
            ->assertOk()
            ->assertJsonPath('doctor.id', $doctor->id)
            ->assertJsonPath('doctor.pharmacy_id', $doctor->pharmacy_id);
    }

    public function test_show_returns_404_for_an_unknown_doctor()
    {
        $this->actingAsRole('admin');

        $this->get('/doctors/99999999999999')->assertNotFound();
    }

    public function test_store_creates_a_doctor_with_a_user_and_the_doctor_role()
    {
        $this->actingAsRole('admin');
        $pharmacy = Pharmacy::factory()->create();

        $this->post('/doctors', [
            'id' => '29001011234567',
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Dr Mona',
            'email' => 'mona@example.com',
            'password' => 'secret123',
        ])
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'mona@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('doctor'));
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('doctors', [
            'id' => '29001011234567',
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'avatar_image' => 'default-avatar.jpg',
        ]);
    }

    public function test_store_rejects_an_unknown_pharmacy()
    {
        $this->actingAsRole('admin');

        $this->post('/doctors', [
            'id' => '29001011234567',
            'pharmacy_id' => 999999,
            'name' => 'Dr Mona',
            'email' => 'mona@example.com',
            'password' => 'secret123',
        ])->assertSessionHasErrors('pharmacy_id');

        $this->assertDatabaseMissing('users', ['email' => 'mona@example.com']);
    }

    public function test_an_admin_may_move_a_doctor_to_another_pharmacy()
    {
        $this->actingAsRole('admin');
        $doctor = Doctor::factory()->create();
        $doctor->user->assignRole('doctor');
        $newPharmacy = Pharmacy::factory()->create();

        $this->put("/doctors/{$doctor->id}", [
            'id' => $doctor->id,
            'pharmacy_id' => $newPharmacy->id,
            'name' => 'Renamed Doctor',
            'email' => 'renamed@example.com',
            'password' => 'secret123',
        ])
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $doctor->user_id,
            'name' => 'Renamed Doctor',
        ]);
        // The doctor owns the account, so their pharmacy assignment is preserved.
        $this->assertSame($doctor->pharmacy_id, $doctor->fresh()->pharmacy_id);
    }

    public function test_ban_and_unban_toggle_the_flag_on_the_doctor()
    {
        $this->actingAsRole('admin');
        $doctor = Doctor::factory()->create();

        $this->post(route('doctors.ban', $doctor->id))->assertRedirect();
        $this->assertSame(1, (int) $doctor->fresh()->is_banned);
        $this->assertTrue($doctor->user->fresh()->isBanned());

        $this->post(route('doctors.unban', $doctor->id))->assertRedirect();
        $this->assertSame(0, (int) $doctor->fresh()->is_banned);
        $this->assertFalse($doctor->user->fresh()->isBanned());
    }

    public function test_destroy_removes_the_doctor()
    {
        $this->actingAsRole('admin');
        $doctor = Doctor::factory()->create();

        $this->delete("/doctors/{$doctor->id}")
            ->assertRedirect(route('doctors.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
    }
}
