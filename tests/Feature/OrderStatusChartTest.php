<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderStatusChartTest extends TestCase
{
    public function testBothChartsPreserveStatusCountsAndRoleScopes(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        // These unrelated migrations use DDL unsupported by SQLite.
        $migrations = array_diff(glob(database_path('migrations/*.php')), [
            database_path('migrations/2023_04_01_160056_drop_address_columns_from_clients_table.php'),
            database_path('migrations/2023_04_05_104316_charify_countries_table.php'),
        ]);
        $this->artisan('migrate', ['--path' => array_values($migrations), '--realpath' => true])
            ->assertExitCode(0);
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create()->assignRole('admin');
        $pharmacy = User::factory()->create()->assignRole('pharmacy');
        $doctor = User::factory()->create()->assignRole('doctor');
        $client = User::factory()->create()->assignRole('client');
        DB::table('countries')->insert(['id' => 1]);
        DB::table('areas')->insert(['id' => 1, 'name' => 'Area', 'address' => 'Address', 'country_id' => 1]);
        foreach ([1 => $pharmacy, 2 => $admin] as $id => $owner) {
            DB::table('pharmacies')->insert([
                'id' => $id, 'area_id' => 1, 'priority' => 1, 'avatar_image' => 'sample.png',
                'user_id' => $owner->id, 'pharmacy_name' => "Pharmacy {$id}",
            ]);
        }
        DB::table('doctors')->insert([
            'id' => 1, 'pharmacy_id' => 1, 'is_banned' => false,
            'avatar_image' => 'sample.png', 'user_id' => $doctor->id,
        ]);

        $labels = ['New', 'Processing', 'WaitingForUserConfirmation', 'Canceled', 'Delivered'];
        $routes = ['statusbarchart.data', 'statuspiechart.data'];
        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->getJson(route($route))->assertOk();
            $this->assertSame(['labels' => $labels, 'data' => [0, 0, 0, 0, 0]], $response->json());
        }

        foreach ([1 => ['New', 'New', 'Confirmed', 'Delivered'], 2 => ['Canceled', 'Processing']] as $id => $statuses) {
            foreach ($statuses as $status) {
                DB::table('orders')->insert([
                    'user_id' => $client->id, 'pharmacy_id' => $id, 'status' => $status,
                    'is_insured' => false, 'creator_type' => 'client', 'price' => 10,
                ]);
            }
        }

        $cases = [
            [$admin, [2, 1, 0, 1, 1]],
            [$pharmacy, [2, 0, 0, 0, 1]],
            [$doctor, [2, 0, 0, 0, 1]],
        ];
        foreach ($routes as $route) {
            foreach ($cases as [$user, $counts]) {
                $response = $this->actingAs($user)->getJson(route($route))->assertOk();
                $this->assertSame(['labels' => $labels, 'data' => $counts], $response->json());
            }
            $this->actingAs($client)->getJson(route($route))->assertForbidden();
        }
    }
}
