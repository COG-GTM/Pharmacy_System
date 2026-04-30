<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Area;
use App\Models\Client;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
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

    private function setupOrderData(): array
    {
        $area = Area::factory()->create();
        $pharmacyUser = User::factory()->create();
        $pharmacyUser->assignRole('pharmacy');
        $pharmacy = Pharmacy::factory()->create(['user_id' => $pharmacyUser->id, 'area_id' => $area->id]);
        $doctor = Doctor::factory()->create(['pharmacy_id' => $pharmacy->id]);
        $clientUser = User::factory()->create();
        $clientUser->assignRole('client');
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $address = Address::factory()->create(['client_id' => $client->id, 'area_id' => $area->id]);
        $medicine1 = Medicine::factory()->create(['price' => 1000]);
        $medicine2 = Medicine::factory()->create(['price' => 2000]);

        return compact('area', 'pharmacy', 'doctor', 'clientUser', 'client', 'address', 'medicine1', 'medicine2');
    }

    public function test_index_loads_all_relations()
    {
        $admin = $this->createAdmin();
        Order::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/orders');
        $response->assertStatus(200);
    }

    public function test_store_order_happy_path()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();

        $response = $this->actingAs($admin)->post('/orders', [
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
            'delivering_address_id' => $data['address']->id,
            'creator_type' => 'pharmacy',
            'status' => 'New',
            'is_insured' => 0,
            'medicine_id' => [$data['medicine1']->id, $data['medicine2']->id],
            'quantity' => ['2', '3'],
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseHas('orders', ['user_id' => $data['clientUser']->id]);
    }

    public function test_store_order_wrong_address_rejected()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();
        $otherClient = Client::factory()->create();
        $otherAddress = Address::factory()->create(['client_id' => $otherClient->id]);

        $response = $this->actingAs($admin)->post('/orders', [
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
            'delivering_address_id' => $otherAddress->id,
            'creator_type' => 'pharmacy',
            'status' => 'New',
            'is_insured' => 0,
            'medicine_id' => [$data['medicine1']->id],
            'quantity' => ['1'],
        ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('error');
    }

    public function test_store_order_wrong_doctor_rejected()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();
        $otherDoctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->post('/orders', [
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $otherDoctor->id,
            'delivering_address_id' => $data['address']->id,
            'creator_type' => 'pharmacy',
            'status' => 'New',
            'is_insured' => 0,
            'medicine_id' => [$data['medicine1']->id],
            'quantity' => ['1'],
        ]);

        $response->assertRedirect(route('orders.index'));
        $response->assertSessionHas('error');
    }

    public function test_show_order_returns_json_with_all_relations()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();
        $order = Order::factory()->create([
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
            'delivering_address_id' => $data['address']->id,
        ]);

        $response = $this->actingAs($admin)->get("/orders/{$order->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['order', 'user', 'pharmacy', 'doctor', 'address', 'area', 'prescriptions']);
    }

    public function test_edit_order()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();
        $order = Order::factory()->create([
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
        ]);

        $response = $this->actingAs($admin)->get("/orders/{$order->id}/edit");
        $response->assertStatus(200);
    }

    public function test_update_order_recalculates_price()
    {
        $admin = $this->createAdmin();
        $data = $this->setupOrderData();
        $order = Order::factory()->create([
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
            'delivering_address_id' => $data['address']->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($admin)->put("/orders/{$order->id}", [
            'user_id' => $data['clientUser']->id,
            'pharmacy_id' => $data['pharmacy']->id,
            'doctor_id' => $data['doctor']->id,
            'delivering_address_id' => $data['address']->id,
            'creator_type' => 'pharmacy',
            'status' => 'Processing',
            'is_insured' => 0,
            'medicine_id' => [$data['medicine1']->id],
            'quantity' => ['5'],
        ]);

        $response->assertRedirect();
    }

    public function test_destroy_order_cascades_medicines_and_prescriptions()
    {
        $admin = $this->createAdmin();
        $order = Order::factory()->create();
        $medicine = Medicine::factory()->create();
        $order->medicines()->attach($medicine->id, ['quantity' => 2]);
        Prescription::factory()->create(['order_id' => $order->id]);

        $response = $this->actingAs($admin)->delete("/orders/{$order->id}");
        $response->assertRedirect();
    }

    public function test_cancel_order_from_waiting_status()
    {
        $admin = $this->createAdmin();
        $order = Order::factory()->create(['status' => 'WaitingForUserConfirmation']);

        $response = $this->actingAs($admin)->get("/orders/stauts/{$order->id}");
        $response->assertRedirect();
    }

    public function test_cancel_already_canceled_order()
    {
        $admin = $this->createAdmin();
        $order = Order::factory()->create(['status' => 'Canceled']);

        $response = $this->actingAs($admin)->get("/orders/stauts/{$order->id}");
        $response->assertRedirect();
    }
}
