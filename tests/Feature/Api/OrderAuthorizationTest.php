<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private function createUser(): User
    {
        return User::create([
            'name' => 'client',
            'email' => 'client-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    }

    private function createOrder(User $owner): Order
    {
        return Order::create([
            'user_id' => $owner->id,
            'pharmacy_id' => null,
            'doctor_id' => null,
            'delivering_address_id' => null,
            'is_insured' => false,
            'status' => 'New',
            'creator_type' => 'client',
            'price' => 0,
        ]);
    }

    public function test_show_does_not_expose_another_users_order()
    {
        $owner = $this->createUser();
        $order = $this->createOrder($owner);
        Prescription::create(['order_id' => $order->id, 'image' => 'image-owner.png']);

        Sanctum::actingAs($this->createUser());
        $response = $this->getJson('/api/orders/' . $order->id);

        $response->assertStatus(404);
        $this->assertStringNotContainsString('image-owner.png', $response->getContent());
    }

    public function test_show_returns_the_order_to_its_owner()
    {
        $owner = $this->createUser();
        $order = $this->createOrder($owner);
        Prescription::create(['order_id' => $order->id, 'image' => 'image-owner.png']);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/orders/' . $order->id);

        $response->assertStatus(200);
        $this->assertStringContainsString('image-owner.png', $response->getContent());
    }

    public function test_update_does_not_replace_another_users_prescriptions()
    {
        Storage::fake('local');
        $owner = $this->createUser();
        $order = $this->createOrder($owner);
        Prescription::create(['order_id' => $order->id, 'image' => 'image-owner.png']);

        Sanctum::actingAs($this->createUser());
        $response = $this->putJson('/api/orders/' . $order->id, [
            'prescriptions' => [UploadedFile::fake()->image('attacker.png')],
        ]);

        $response->assertStatus(404);
        $this->assertSame(1, Prescription::where('order_id', $order->id)->count());
        $this->assertSame(
            'image-owner.png',
            Prescription::where('order_id', $order->id)->first()->image
        );
    }

    public function test_update_replaces_prescriptions_for_the_owner()
    {
        Storage::fake('local');
        $owner = $this->createUser();
        $order = $this->createOrder($owner);
        Prescription::create(['order_id' => $order->id, 'image' => 'image-owner.png']);

        Sanctum::actingAs($owner);
        $response = $this->put('/api/orders/' . $order->id, [
            'prescriptions' => [UploadedFile::fake()->image('owner-new.png')],
        ]);

        $response->assertStatus(200);
        $this->assertSame(
            'image-owner-new.png',
            Prescription::where('order_id', $order->id)->first()->image
        );
    }

    public function test_show_returns_404_for_a_missing_order()
    {
        Sanctum::actingAs($this->createUser());

        $this->getJson('/api/orders/999999')->assertStatus(404);
    }

    public function test_update_returns_404_for_a_missing_order()
    {
        Sanctum::actingAs($this->createUser());

        $this->putJson('/api/orders/999999')->assertStatus(404);
    }
}
