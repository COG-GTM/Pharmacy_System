<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineTest extends TestCase
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

    public function test_index_renders_datatable()
    {
        $admin = $this->createAdmin();
        Medicine::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/medicines');
        $response->assertStatus(200);
    }

    public function test_store_medicine()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/medicines', [
            'name' => 'Aspirin',
            'type' => 'Tablet',
            'quantity' => 100,
            'price' => 500,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('medicines', ['name' => 'Aspirin']);
    }

    public function test_show_medicine_returns_json()
    {
        $admin = $this->createAdmin();
        $medicine = Medicine::factory()->create();

        $response = $this->actingAs($admin)->get("/medicines/{$medicine->id}");
        $response->assertStatus(200);
    }

    public function test_update_medicine()
    {
        $admin = $this->createAdmin();
        $medicine = Medicine::factory()->create();

        $response = $this->actingAs($admin)->put("/medicines/{$medicine->id}", [
            'name' => 'Updated Medicine',
            'type' => 'Capsule',
            'quantity' => 200,
            'price' => 1000,
        ]);

        $response->assertRedirect();
    }

    public function test_destroy_medicine_blocked_if_in_order()
    {
        $admin = $this->createAdmin();
        $medicine = Medicine::factory()->create();
        $order = Order::factory()->create();
        $order->medicines()->attach($medicine->id, ['quantity' => 2]);

        $response = $this->actingAs($admin)->delete("/medicines/{$medicine->id}");
        $response->assertRedirect();
    }
}
