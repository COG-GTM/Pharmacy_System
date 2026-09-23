<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Doctor;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DoctorAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private int $areaId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'pharmacy', 'doctor'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $countryId = random_int(1, 999999999);
        DB::table('countries')->insert([
            'id' => $countryId,
            'country_code' => 'TST',
            'iso_3166_2' => 'TS',
            'iso_3166_3' => 'TST',
            'name' => 'Testland',
            'region_code' => '001',
            'sub_region_code' => '001',
        ]);

        $this->areaId = random_int(1, 999999999);
        Area::create([
            'id' => $this->areaId,
            'name' => 'Test Area',
            'address' => 'Test Address',
            'country_id' => $countryId,
        ]);
    }

    public function test_doctor_cannot_update_another_doctor(): void
    {
        [$attacker] = $this->makeDoctor();
        [$victim, $victimUser] = $this->makeDoctor();

        $response = $this->actingAs($attacker->user)->put('/doctors/'.$victim->id, [
            'id' => (string) $victim->id,
            'pharmacy_id' => $victim->pharmacy_id,
            'name' => 'Attacker',
            'email' => 'attacker@evil.test',
            'password' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertSame($victimUser->email, $victimUser->fresh()->email);
    }

    public function test_doctor_can_update_own_record(): void
    {
        [$doctor, $doctorUser] = $this->makeDoctor();

        $response = $this->actingAs($doctorUser)->put('/doctors/'.$doctor->id, [
            'id' => (string) $doctor->id,
            'pharmacy_id' => $doctor->pharmacy_id,
            'name' => 'New Name',
            'email' => 'new-name@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('doctors.index'));
        $this->assertSame('new-name@example.test', $doctorUser->fresh()->email);
    }

    public function test_pharmacy_cannot_view_doctor_of_another_pharmacy(): void
    {
        [$doctor] = $this->makeDoctor();
        $otherPharmacyUser = $this->makePharmacy()->user;

        $this->actingAs($otherPharmacyUser)->get('/doctors/'.$doctor->id)->assertForbidden();
    }

    public function test_pharmacy_can_view_own_doctor(): void
    {
        [$doctor] = $this->makeDoctor();

        $this->actingAs($doctor->pharmacy->user)
            ->get('/doctors/'.$doctor->id)
            ->assertOk();
    }

    public function test_pharmacy_cannot_delete_doctor_of_another_pharmacy(): void
    {
        [$doctor] = $this->makeDoctor();
        $otherPharmacyUser = $this->makePharmacy()->user;

        $this->actingAs($otherPharmacyUser)->delete('/doctors/'.$doctor->id)->assertForbidden();
        $this->assertNotNull(Doctor::find($doctor->id));
    }

    public function test_pharmacy_cannot_ban_or_unban_doctor_of_another_pharmacy(): void
    {
        [$doctor, $doctorUser] = $this->makeDoctor();
        $otherPharmacyUser = $this->makePharmacy()->user;

        $this->actingAs($otherPharmacyUser)->post('/doctors/'.$doctor->id.'/ban')->assertForbidden();
        $this->actingAs($otherPharmacyUser)->post('/doctors/'.$doctor->id.'/unban')->assertForbidden();
        $this->assertSame(0, (int) $doctor->fresh()->is_banned);
        $this->assertFalse($doctorUser->fresh()->isBanned());
    }

    public function test_pharmacy_can_ban_own_doctor(): void
    {
        [$doctor, $doctorUser] = $this->makeDoctor();

        $this->actingAs($doctor->pharmacy->user)
            ->post('/doctors/'.$doctor->id.'/ban')
            ->assertRedirect();

        $this->assertSame(1, (int) $doctor->fresh()->is_banned);
        $this->assertTrue($doctorUser->fresh()->isBanned());
    }

    public function test_admin_keeps_full_access(): void
    {
        [$doctor] = $this->makeDoctor();
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/doctors/'.$doctor->id)->assertOk();
        $this->actingAs($admin)->post('/doctors/'.$doctor->id.'/ban')->assertRedirect();
    }

    public function test_pharmacy_cannot_create_doctor_for_another_pharmacy(): void
    {
        $pharmacy = $this->makePharmacy();
        $otherPharmacy = $this->makePharmacy();

        $response = $this->actingAs($pharmacy->user)->post('/doctors', [
            'id' => (string) random_int(10000000000000, 99999999999999),
            'pharmacy_id' => $otherPharmacy->id,
            'name' => 'New Doctor',
            'email' => 'new-doctor@example.test',
            'password' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Doctor::where('pharmacy_id', $otherPharmacy->id)->count());
    }

    private function makeUser(string $role): User
    {
        $user = User::create([
            'name' => 'User '.random_int(1, 999999999),
            'email' => 'user'.random_int(1, 999999999).'@example.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makePharmacy(): Pharmacy
    {
        $id = random_int(1, 999999999);
        Pharmacy::create([
            'id' => $id,
            'user_id' => $this->makeUser('pharmacy')->id,
            'pharmacy_name' => 'Pharmacy '.random_int(1, 999999),
            'avatar_image' => 'default-avatar.jpg',
            'area_id' => $this->areaId,
            'priority' => 1,
        ]);

        return Pharmacy::findOrFail($id);
    }

    /**
     * @return array{0: Doctor, 1: User}
     */
    private function makeDoctor(): array
    {
        $user = $this->makeUser('doctor');
        $id = random_int(10000000000000, 99999999999999);
        Doctor::create([
            'id' => $id,
            'user_id' => $user->id,
            'pharmacy_id' => $this->makePharmacy()->id,
            'is_banned' => 0,
            'avatar_image' => 'default-avatar.jpg',
        ]);

        return [Doctor::findOrFail($id), $user];
    }
}
