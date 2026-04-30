<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_only_routes_reject_pharmacy_doctor_client()
    {
        $adminOnlyRoutes = [
            '/areas',
            '/clients',
        ];

        $roles = ['pharmacy', 'doctor', 'client'];

        foreach ($roles as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            foreach ($adminOnlyRoutes as $route) {
                $response = $this->actingAs($user)->get($route);
                $this->assertTrue(
                    in_array($response->getStatusCode(), [403, 302]),
                    "Route {$route} should be restricted for {$role} role but got {$response->getStatusCode()}"
                );
            }
        }
    }

    public function test_pharmacy_routes_reject_client()
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $pharmacyRoutes = [
            '/pharmacies',
            '/revenue',
        ];

        foreach ($pharmacyRoutes as $route) {
            $response = $this->actingAs($user)->get($route);
            $this->assertTrue(
                in_array($response->getStatusCode(), [403, 302]),
                "Route {$route} should be restricted for client role but got {$response->getStatusCode()}"
            );
        }
    }

    public function test_unauthenticated_redirects_to_login()
    {
        $protectedRoutes = [
            '/orders',
            '/doctors',
            '/pharmacies',
            '/medicines',
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }
}
