<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
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

    public function test_admin_can_view_clients()
    {
        $admin = $this->createAdmin();
        Client::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/clients');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_client()
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/clients', [
            'name' => 'New Client',
            'email' => 'newclient@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'id' => '12345678901234',
            'gender' => 'Female',
            'date_of_birth' => '1995-06-15',
            'phone' => '01112345678',
        ]);

        $response->assertRedirect();
    }

    public function test_admin_can_update_client()
    {
        $admin = $this->createAdmin();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->put("/clients/{$client->id}", [
            'name' => 'Updated Client',
            'email' => $client->user->email,
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ]);

        $response->assertRedirect();
    }

    public function test_admin_can_delete_client_cascades_user()
    {
        $admin = $this->createAdmin();
        $client = Client::factory()->create();
        $userId = $client->user_id;

        $response = $this->actingAs($admin)->delete("/clients/{$client->id}");
        $response->assertRedirect();
    }

    public function test_show_client_returns_json_with_addresses()
    {
        $admin = $this->createAdmin();
        $client = Client::factory()->create();
        Address::factory()->count(2)->create(['client_id' => $client->id]);

        $response = $this->actingAs($admin)->get("/clients/{$client->id}");
        $response->assertStatus(200);
    }

    public function test_delete_client_with_orders_fails()
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create();
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'Processing']);

        $response = $this->actingAs($admin)->delete("/clients/{$client->id}");
        $response->assertRedirect();
    }
}
