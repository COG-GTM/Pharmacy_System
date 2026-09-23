<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private const PHARMACY_A_ID = '10000000000001';
    private const PHARMACY_B_ID = '10000000000002';
    private const DOCTOR_A_ID = '20000000000001';
    private const DOCTOR_B_ID = '20000000000002';

    private User $pharmacyAUser;
    private User $pharmacyBUser;
    private User $doctorAUser;
    private User $adminUser;
    private Order $orderA;
    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'pharmacy', 'doctor', 'client'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $areaId = $this->createArea();

        $this->adminUser = $this->createUser('admin');
        $this->pharmacyAUser = $this->createUser('pharmacy');
        $this->pharmacyBUser = $this->createUser('pharmacy');

        $this->createPharmacy(self::PHARMACY_A_ID, $areaId, $this->pharmacyAUser);
        $this->createPharmacy(self::PHARMACY_B_ID, $areaId, $this->pharmacyBUser);

        $this->doctorAUser = $this->createUser('doctor');
        $this->createDoctor(self::DOCTOR_A_ID, self::PHARMACY_A_ID, $this->doctorAUser);
        $this->createDoctor(self::DOCTOR_B_ID, self::PHARMACY_B_ID, $this->createUser('doctor'));

        $this->orderA = $this->createOrder(self::PHARMACY_A_ID);
        $this->orderB = $this->createOrder(self::PHARMACY_B_ID);
    }

    public function test_pharmacy_user_cannot_read_another_pharmacys_order(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('orders.show', $this->orderB->id))
            ->assertForbidden();
    }

    public function test_doctor_cannot_read_an_order_of_another_pharmacy(): void
    {
        $this->actingAs($this->doctorAUser)
            ->get(route('orders.show', $this->orderB->id))
            ->assertForbidden();
    }

    public function test_pharmacy_user_cannot_delete_another_pharmacys_order(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->delete(route('orders.destroy', $this->orderB->id))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $this->orderB->id]);
    }

    public function test_pharmacy_user_cannot_change_the_status_of_another_pharmacys_order(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('orders.updatestatus', $this->orderB->id))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $this->orderB->id,
            'status' => 'WaitingForUserConfirmation',
        ]);
    }

    public function test_pharmacy_user_can_delete_its_own_order(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->delete(route('orders.destroy', $this->orderA->id))
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseMissing('orders', ['id' => $this->orderA->id]);
    }

    public function test_admin_can_delete_any_order(): void
    {
        $this->actingAs($this->adminUser)
            ->delete(route('orders.destroy', $this->orderB->id))
            ->assertRedirect(route('orders.index'));
    }

    public function test_pharmacy_user_cannot_read_another_pharmacys_doctor(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('doctors.show', self::DOCTOR_B_ID))
            ->assertForbidden();
    }

    public function test_pharmacy_user_cannot_update_another_pharmacys_doctor(): void
    {
        $doctor = Doctor::findOrFail(self::DOCTOR_B_ID);

        $this->actingAs($this->pharmacyAUser)
            ->put(route('doctors.update', self::DOCTOR_B_ID), [
                'id' => self::DOCTOR_B_ID,
                'pharmacy_id' => self::PHARMACY_B_ID,
                'name' => 'Attacker Controlled',
                'email' => 'attacker@example.com',
                'password' => 'password123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'id' => $doctor->user_id,
            'email' => 'attacker@example.com',
        ]);
    }

    public function test_pharmacy_user_cannot_delete_another_pharmacys_doctor(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->delete(route('doctors.destroy', self::DOCTOR_B_ID))
            ->assertForbidden();

        $this->assertDatabaseHas('doctors', ['id' => self::DOCTOR_B_ID]);
    }

    public function test_pharmacy_user_cannot_ban_another_pharmacys_doctor(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->post(route('doctors.ban', self::DOCTOR_B_ID))
            ->assertForbidden();
    }

    public function test_pharmacy_user_can_read_its_own_doctor(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('doctors.show', self::DOCTOR_A_ID))
            ->assertOk();
    }

    public function test_pharmacy_user_cannot_read_another_pharmacy(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('pharmacies.show', self::PHARMACY_B_ID))
            ->assertForbidden();
    }

    public function test_pharmacy_user_cannot_update_another_pharmacy(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->put(route('pharmacies.update', self::PHARMACY_B_ID), [
                'id' => self::PHARMACY_B_ID,
                'pharmacy_name' => 'Attacker Pharmacy',
                'name' => 'Attacker Controlled',
                'email' => 'attacker-pharmacy@example.com',
                'password' => 'password123',
                'area_id' => Pharmacy::findOrFail(self::PHARMACY_B_ID)->area_id,
                'priority' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'id' => $this->pharmacyBUser->id,
            'email' => 'attacker-pharmacy@example.com',
        ]);
    }

    public function test_pharmacy_user_can_read_its_own_pharmacy(): void
    {
        $this->actingAs($this->pharmacyAUser)
            ->get(route('pharmacies.show', self::PHARMACY_A_ID))
            ->assertOk();
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'name' => 'Test ' . $role,
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createArea(): int
    {
        $areaId = (int) (DB::table('areas')->max('id') ?? 0) + 1;
        DB::table('areas')->insert([
            'id' => $areaId,
            'name' => 'Test Area',
            'address' => 'Test Address',
            'country_id' => DB::table('countries')->min('id') ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $areaId;
    }

    private function createPharmacy(string $id, int $areaId, User $user): void
    {
        DB::table('pharmacies')->insert([
            'id' => $id,
            'pharmacy_name' => 'Pharmacy ' . $id,
            'avatar_image' => 'default-avatar.jpg',
            'area_id' => $areaId,
            'priority' => 1,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createDoctor(string $id, string $pharmacyId, User $user): void
    {
        DB::table('doctors')->insert([
            'id' => $id,
            'avatar_image' => 'default-avatar.jpg',
            'pharmacy_id' => $pharmacyId,
            'is_banned' => 0,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(string $pharmacyId): Order
    {
        return Order::create([
            'user_id' => $this->createUser('client')->id,
            'pharmacy_id' => $pharmacyId,
            'status' => 'WaitingForUserConfirmation',
            'is_insured' => 0,
            'creator_type' => 'pharmacy',
            'price' => 10,
        ]);
    }
}
