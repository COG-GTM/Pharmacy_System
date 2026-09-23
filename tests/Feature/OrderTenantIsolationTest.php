<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\ForeignKeyLessMySqlGrammar;
use Tests\TestCase;

class OrderTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Order $foreignOrder;
    private User $pharmacyUser;
    private User $doctorUser;

    /**
     * Several migrations declare foreign keys before the table they reference is
     * created, so the schema is built without foreign key constraints.
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $connection = DB::connection();
            $grammar = new ForeignKeyLessMySqlGrammar();
            $grammar->setTablePrefix($connection->getTablePrefix());
            $connection->setSchemaGrammar($grammar);

            try {
                $this->artisan('db:wipe');
                $this->artisan('migrate');
            } finally {
                $connection->useDefaultSchemaGrammar();
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'pharmacy', 'doctor', 'client'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        DB::table('countries')->insert(['id' => 1, 'name' => 'Testland']);
        Area::create(['id' => 1, 'name' => 'Area One', 'address' => 'Address One', 'country_id' => 1]);

        $this->pharmacyUser = $this->makeUser('pharmacy');
        $ownPharmacy = $this->makePharmacy(1, $this->pharmacyUser);
        $this->pharmacyUser->assignRole('pharmacy');

        $foreignPharmacyUser = $this->makeUser('pharmacy');
        $foreignPharmacy = $this->makePharmacy(2, $foreignPharmacyUser);
        $foreignPharmacyUser->assignRole('pharmacy');

        $this->doctorUser = $this->makeUser('doctor');
        $this->doctorUser->assignRole('doctor');
        Doctor::create([
            'id' => 1,
            'avatar_image' => 'doctor.png',
            'pharmacy_id' => $ownPharmacy->id,
            'is_banned' => false,
            'user_id' => $this->doctorUser->id,
        ]);

        $patient = $this->makeUser('client');
        $patient->assignRole('client');
        Client::create([
            'id' => $patient->id,
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'avatar_image' => 'patient.png',
            'phone' => '0100000000',
            'area_id' => 1,
            'street_name' => 'Secret Street',
            'building_no' => 1,
            'floor_number' => 1,
            'flat_number' => 1,
            'is_main' => true,
            'user_id' => $patient->id,
        ]);
        $address = Address::create([
            'street_name' => 'Secret Street',
            'building_number' => 1,
            'floor_number' => 1,
            'flat_number' => 1,
            'is_main' => true,
            'area_id' => 1,
            'client_id' => $patient->id,
        ]);

        $this->foreignOrder = Order::create([
            'user_id' => $patient->id,
            'pharmacy_id' => $foreignPharmacy->id,
            'doctor_id' => null,
            'creator_type' => 'client',
            'status' => 'WaitingForUserConfirmation',
            'is_insured' => false,
            'delivering_address_id' => $address->id,
            'price' => 10,
        ]);
    }

    public function test_pharmacy_user_cannot_read_another_pharmacys_order()
    {
        $response = $this->actingAs($this->pharmacyUser)->get(route('orders.show', $this->foreignOrder->id));

        $response->assertForbidden();
        $response->assertDontSee('Secret Street');
    }

    public function test_doctor_user_cannot_read_another_pharmacys_order()
    {
        $response = $this->actingAs($this->doctorUser)->get(route('orders.show', $this->foreignOrder->id));

        $response->assertForbidden();
    }

    public function test_pharmacy_user_cannot_delete_another_pharmacys_order()
    {
        $response = $this->actingAs($this->pharmacyUser)->delete(route('orders.destroy', $this->foreignOrder->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $this->foreignOrder->id]);
    }

    public function test_pharmacy_user_cannot_cancel_another_pharmacys_order()
    {
        $response = $this->actingAs($this->pharmacyUser)->get(route('orders.updatestatus', $this->foreignOrder->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('orders', [
            'id' => $this->foreignOrder->id,
            'status' => 'WaitingForUserConfirmation',
        ]);
    }

    public function test_pharmacy_user_cannot_edit_another_pharmacys_order()
    {
        $response = $this->actingAs($this->pharmacyUser)->get(route('orders.edit', $this->foreignOrder->id));

        $response->assertForbidden();
    }

    public function test_owning_pharmacy_user_can_still_read_its_own_order()
    {
        $ownOrder = Order::create([
            'user_id' => $this->foreignOrder->user_id,
            'pharmacy_id' => $this->pharmacyUser->pharmacy->id,
            'doctor_id' => null,
            'creator_type' => 'client',
            'status' => 'New',
            'is_insured' => false,
            'delivering_address_id' => $this->foreignOrder->delivering_address_id,
            'price' => 10,
        ]);

        $response = $this->actingAs($this->pharmacyUser)->get(route('orders.show', $ownOrder->id));

        $response->assertOk();
        $response->assertJsonPath('order.id', $ownOrder->id);
    }

    private function makeUser(string $name): User
    {
        return User::create([
            'name' => $name . '-' . uniqid(),
            'email' => $name . '-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function makePharmacy(int $id, User $owner): Pharmacy
    {
        Pharmacy::create([
            'id' => $id,
            'pharmacy_name' => 'Pharmacy ' . $id,
            'avatar_image' => 'pharmacy.png',
            'area_id' => 1,
            'priority' => 1,
            'user_id' => $owner->id,
        ]);

        return Pharmacy::findOrFail($id);
    }
}
