<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PharmacyAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The full migration set cannot be replayed on an empty database (several
     * migrations declare foreign keys against tables that do not exist yet), so the
     * tables these tests touch are migrated explicitly, in dependency order.
     */
    private const MIGRATIONS = [
        '2014_10_12_000000_create_users_table.php',
        '2023_04_03_180722_add_email_verified_at_to_users_table.php',
        '2023_04_04_151455_add_last_login_to_users_table.php',
        '2023_04_04_233733_add_banned_at_to_users_table.php',
        '2023_03_27_200914_create_areas_table.php',
        '2023_03_27_195504_create_pharmacies_table.php',
        '2023_03_31_135405_add_updated_at_to_pharmacies_table.php',
        '2023_04_01_155310_add_pharmacy_name_to_pharmacies_table.php',
        '2023_03_28_225313_create_permission_tables.php',
    ];

    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('db:wipe', ['--drop-views' => true]);

            foreach (self::MIGRATIONS as $migration) {
                $this->artisan('migrate', ['--path' => 'database/migrations/'.$migration]);
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'pharmacy'] as $role) {
            Role::findOrCreate($role);
        }

        Area::create(['id' => 1, 'name' => 'Nasr City', 'address' => 'Cairo']);
    }

    private function makePharmacy($id, $email)
    {
        $user = User::create([
            'name' => 'Owner '.$id,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('pharmacy');

        Pharmacy::create([
            'id' => $id,
            'user_id' => $user->id,
            'pharmacy_name' => 'Pharmacy '.$id,
            'area_id' => 1,
            'priority' => 1,
            'avatar_image' => 'default-avatar.jpg',
        ]);

        return [$user, Pharmacy::where('id', $id)->firstOrFail()];
    }

    public function test_pharmacy_cannot_view_another_pharmacy()
    {
        [$attacker] = $this->makePharmacy(11111111111111, 'attacker@example.com');
        [, $victimPharmacy] = $this->makePharmacy(22222222222222, 'victim@example.com');

        $this->actingAs($attacker)
            ->getJson(route('pharmacies.show', $victimPharmacy->id))
            ->assertForbidden();
    }

    public function test_pharmacy_can_view_its_own_pharmacy()
    {
        [$owner, $pharmacy] = $this->makePharmacy(11111111111111, 'owner@example.com');

        $this->actingAs($owner)
            ->getJson(route('pharmacies.show', $pharmacy->id))
            ->assertOk()
            ->assertJsonPath('pharmacy.id', $pharmacy->id);
    }

    public function test_pharmacy_cannot_update_another_pharmacy_or_its_owner_account()
    {
        [$attacker] = $this->makePharmacy(11111111111111, 'attacker@example.com');
        [$victim, $victimPharmacy] = $this->makePharmacy(22222222222222, 'victim@example.com');

        $this->actingAs($attacker)
            ->put(route('pharmacies.update', $victimPharmacy->id), [
                'id' => $victimPharmacy->id,
                'pharmacy_name' => 'Hijacked',
                'name' => 'Hijacked Owner',
                'email' => 'hijacked@example.com',
                'password' => 'password',
                'user_id' => $victim->id,
                'area_id' => 1,
                'priority' => 9,
            ])
            ->assertForbidden();

        $this->assertSame('victim@example.com', $victim->fresh()->email);
        $this->assertSame('Pharmacy 22222222222222', $victimPharmacy->fresh()->pharmacy_name);
    }

    public function test_pharmacy_can_update_its_own_pharmacy()
    {
        [$owner, $pharmacy] = $this->makePharmacy(11111111111111, 'owner@example.com');

        $this->actingAs($owner)
            ->put(route('pharmacies.update', $pharmacy->id), [
                'id' => $pharmacy->id,
                'pharmacy_name' => 'Renamed',
                'name' => 'Renamed Owner',
                'email' => 'owner@example.com',
                'password' => 'password',
                'user_id' => $owner->id,
                'area_id' => 1,
                'priority' => 5,
            ])
            ->assertRedirect(route('pharmacies.index'));

        $this->assertSame('Renamed', $pharmacy->fresh()->pharmacy_name);
        $this->assertSame('Renamed Owner', $owner->fresh()->name);
    }

    public function test_admin_can_view_and_update_any_pharmacy()
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        [$owner, $pharmacy] = $this->makePharmacy(11111111111111, 'owner@example.com');

        $this->actingAs($admin)
            ->getJson(route('pharmacies.show', $pharmacy->id))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('pharmacies.update', $pharmacy->id), [
                'id' => $pharmacy->id,
                'pharmacy_name' => 'Admin Renamed',
                'name' => 'Owner',
                'email' => 'owner@example.com',
                'password' => 'password',
                'user_id' => $owner->id,
                'area_id' => 1,
                'priority' => 7,
            ])
            ->assertRedirect(route('pharmacies.index'));

        $this->assertSame('Admin Renamed', $pharmacy->fresh()->pharmacy_name);
    }

    public function test_pharmacy_index_datatable_only_returns_own_pharmacy()
    {
        [$owner, $pharmacy] = $this->makePharmacy(11111111111111, 'owner@example.com');
        [, $otherPharmacy] = $this->makePharmacy(22222222222222, 'other@example.com');

        $response = $this->actingAs($owner)
            ->get(route('pharmacies.index').'?draw=1&start=0&length=10', [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ]);

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($pharmacy->id, $ids);
        $this->assertNotContains($otherPharmacy->id, $ids);
    }
}
