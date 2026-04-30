<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_sees_revenue_datatable()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/revenue');
        $response->assertStatus(200);
    }

    public function test_pharmacy_sees_own_revenue()
    {
        $user = User::factory()->create();
        $user->assignRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id]);
        Order::factory()->count(3)->create(['pharmacy_id' => $pharmacy->id, 'status' => 'Delivered', 'price' => 100]);

        $response = $this->actingAs($user)->get('/revenue');
        $response->assertStatus(200);
    }

    public function test_other_roles_get_403()
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');

        $response = $this->actingAs($user)->get('/revenue');
        $response->assertStatus(403);
    }
}
