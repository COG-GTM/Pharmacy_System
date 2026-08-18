<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Client;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
    }

    private function authenticate(): void
    {
        Sanctum::actingAs($this->client->user);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }

    public function test_index_returns_only_the_orders_of_the_authenticated_user()
    {
        $this->authenticate();
        $medicine = Medicine::factory()->create();
        $own = Order::factory()->create(['user_id' => $this->client->user_id, 'status' => 'New']);
        $own->medicines()->attach($medicine->id, ['quantity' => 2]);
        $other = Order::factory()->create();

        $response = $this->getJson('/api/orders')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($own->id, $response->json('data.0.user_id'));
        $this->assertSame('New', $response->json('data.0.status'));
        $this->assertNotSame($other->user_id, $this->client->user_id);
    }

    /**
     * Documents a live bug: the controller inserts a null pharmacy_id while
     * orders.pharmacy_id is NOT NULL, so client order creation cannot succeed
     * on a strict database.
     */
    public function test_create_fails_because_the_order_is_inserted_without_a_pharmacy()
    {
        Storage::fake('local');
        $this->authenticate();
        $address = Address::factory()->create(['client_id' => $this->client->id]);

        $this->postJson('/api/orders', [
            'delivering_address_id' => $address->id,
            'is_insured' => false,
            'prescriptions' => [UploadedFile::fake()->image('scan.jpg')],
        ])->assertStatus(500);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_create_rejects_an_address_that_does_not_belong_to_the_client()
    {
        $this->authenticate();
        $foreignAddress = Address::factory()->create();

        $this->postJson('/api/orders', [
            'delivering_address_id' => $foreignAddress->id,
            'is_insured' => false,
            'prescriptions' => [UploadedFile::fake()->image('scan.jpg')],
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'address id does not belong to this user');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_create_rejects_an_order_without_prescriptions()
    {
        $this->authenticate();
        $address = Address::factory()->create(['client_id' => $this->client->id]);

        $this->postJson('/api/orders', [
            'delivering_address_id' => $address->id,
            'is_insured' => false,
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'No prescriptions');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_show_returns_the_order_with_its_prescriptions()
    {
        $this->authenticate();
        $order = Order::factory()->create(['user_id' => $this->client->user_id]);
        $prescription = Prescription::factory()->create(['order_id' => $order->id]);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Order details')
            ->assertJsonPath('data.user_id', $order->id)
            ->assertJsonPath('prescriptions.0.id', $prescription->id);
    }

    public function test_update_replaces_the_prescriptions_of_a_new_order()
    {
        Storage::fake('local');
        $this->authenticate();
        $order = Order::factory()->create(['user_id' => $this->client->user_id, 'status' => 'New']);
        $old = Prescription::factory()->create(['order_id' => $order->id]);

        $this->putJson("/api/orders/{$order->id}", [
            'prescriptions' => [UploadedFile::fake()->image('new-scan.jpg')],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Order updated successfully');

        $this->assertDatabaseMissing('prescriptions', ['id' => $old->id]);
        $this->assertDatabaseHas('prescriptions', [
            'order_id' => $order->id,
            'image' => 'image-new-scan.jpg',
        ]);
    }

    public function test_update_leaves_the_prescriptions_of_a_processed_order_untouched()
    {
        $this->authenticate();
        $order = Order::factory()->create([
            'user_id' => $this->client->user_id,
            'status' => 'Processing',
        ]);
        $existing = Prescription::factory()->create(['order_id' => $order->id]);

        $this->putJson("/api/orders/{$order->id}", [
            'prescriptions' => [UploadedFile::fake()->image('new-scan.jpg')],
        ])->assertOk();

        $this->assertDatabaseHas('prescriptions', ['id' => $existing->id]);
        $this->assertDatabaseCount('prescriptions', 1);
    }
}
