<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_chart_status_data()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Order::factory()->count(5)->create(['status' => 'New']);
        Order::factory()->count(3)->create(['status' => 'Delivered']);

        $response = $this->actingAs($admin)->get('/status/statusbarchart');
        $response->assertStatus(200);
        $response->assertJsonStructure(['labels', 'data']);
    }

    public function test_pharmacy_chart_status_data()
    {
        $user = User::factory()->create();
        $user->assignRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $user->id]);
        Order::factory()->count(3)->create(['pharmacy_id' => $pharmacy->id, 'status' => 'Processing']);

        $response = $this->actingAs($user)->get('/status/statusbarchart');
        $response->assertStatus(200);
        $response->assertJsonStructure(['labels', 'data']);
    }
}
