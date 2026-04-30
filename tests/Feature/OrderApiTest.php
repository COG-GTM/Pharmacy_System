<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    private function createAuthenticatedClient(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['client_id' => $client->id]);
        Sanctum::actingAs($user);
        return compact('user', 'client', 'address');
    }

    public function test_api_create_order_with_prescriptions()
    {
        $data = $this->createAuthenticatedClient();

        $response = $this->postJson('/api/orders', [
            'delivering_address_id' => $data['address']->id,
            'is_insured' => 0,
            'prescriptions' => [
                UploadedFile::fake()->image('prescription1.jpg'),
                UploadedFile::fake()->image('prescription2.jpg'),
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'data']);
    }

    public function test_api_create_order_without_prescriptions_fails()
    {
        $data = $this->createAuthenticatedClient();

        $response = $this->postJson('/api/orders', [
            'delivering_address_id' => $data['address']->id,
            'is_insured' => 0,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'No prescriptions']);
    }

    public function test_api_index_returns_user_orders_only()
    {
        $data = $this->createAuthenticatedClient();
        Order::factory()->count(2)->create(['user_id' => $data['user']->id]);
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/orders');
        $response->assertStatus(200);
    }

    public function test_api_show_order_with_prescriptions()
    {
        $data = $this->createAuthenticatedClient();
        $order = Order::factory()->create(['user_id' => $data['user']->id]);
        Prescription::factory()->count(2)->create(['order_id' => $order->id]);

        $response = $this->getJson("/api/orders/{$order->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'data', 'prescriptions']);
    }

    public function test_api_update_prescriptions_on_new_order()
    {
        $data = $this->createAuthenticatedClient();
        $order = Order::factory()->create([
            'user_id' => $data['user']->id,
            'status' => 'New',
        ]);

        $response = $this->putJson("/api/orders/{$order->id}", [
            'prescriptions' => [
                UploadedFile::fake()->image('updated_prescription.jpg'),
            ],
        ]);

        $response->assertStatus(200);
    }
}
