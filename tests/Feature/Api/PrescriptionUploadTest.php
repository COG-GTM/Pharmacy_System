<?php

namespace Tests\Feature\Api;

use App\Models\Address;
use App\Models\Client;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrescriptionUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        Storage::fake('prescriptions');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        $this->dropSchema();

        parent::tearDown();
    }

    public function test_prescription_with_disallowed_type_is_rejected()
    {
        $client = $this->createClientWithAddress();
        Sanctum::actingAs($client['user']);

        $response = $this->post('/api/orders', [
            'delivering_address_id' => $client['address']->id,
            'is_insured' => 1,
            'prescriptions' => [
                UploadedFile::fake()->createWithContent('payload.html', '<script>alert(1)</script>'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame(0, Prescription::count());
        $this->assertSame(0, Order::count());
        $this->assertEmpty(Storage::disk('prescriptions')->allFiles());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_prescription_is_stored_privately_under_a_hashed_name()
    {
        $client = $this->createClientWithAddress();
        Sanctum::actingAs($client['user']);

        $response = $this->post('/api/orders', [
            'delivering_address_id' => $client['address']->id,
            'is_insured' => 1,
            'prescriptions' => [
                UploadedFile::fake()->image('patient-scan.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $prescription = Prescription::firstOrFail();
        $this->assertStringNotContainsString('patient-scan', $prescription->image);
        $this->assertNotSame('image-patient-scan.jpg', $prescription->image);
        Storage::disk('prescriptions')->assertExists($prescription->image);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_order_details_expose_an_authorized_url_instead_of_the_file_name()
    {
        $client = $this->createClientWithAddress();
        $order = $this->createOrderFor($client);
        $prescription = $this->storePrescriptionFor($order);

        Sanctum::actingAs($client['user']);
        $response = $this->getJson('/api/orders/' . $order->id);

        $response->assertStatus(200);
        $payload = $response->json('prescriptions.0');
        $this->assertArrayNotHasKey('image', $payload);
        $this->assertSame(
            route('api.orders.prescriptions.show', ['order' => $order->id, 'prescription' => $prescription->id]),
            $payload['url']
        );

        $this->get($payload['url'])->assertStatus(200);
    }

    public function test_prescription_is_not_served_to_another_client()
    {
        $owner = $this->createClientWithAddress();
        $order = $this->createOrderFor($owner);
        $prescription = $this->storePrescriptionFor($order);

        $attacker = $this->createClientWithAddress();
        Sanctum::actingAs($attacker['user']);

        $this->getJson('/api/orders/' . $order->id)->assertStatus(404);
        $this->get(route('api.orders.prescriptions.show', [
            'order' => $order->id,
            'prescription' => $prescription->id,
        ]))->assertStatus(404);
    }

    public function test_another_client_cannot_replace_prescriptions_of_an_order()
    {
        $owner = $this->createClientWithAddress();
        $order = $this->createOrderFor($owner);
        $prescription = $this->storePrescriptionFor($order);

        $attacker = $this->createClientWithAddress();
        Sanctum::actingAs($attacker['user']);

        $response = $this->put('/api/orders/' . $order->id, [
            'prescriptions' => [UploadedFile::fake()->image('overwrite.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(404);
        $this->assertSame(1, Prescription::where('order_id', $order->id)->count());
        Storage::disk('prescriptions')->assertExists($prescription->image);
    }

    private function createClientWithAddress(): array
    {
        $user = User::create([
            'name' => 'Client ' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'id' => $user->id,
            'user_id' => $user->id,
        ]);

        $address = Address::create([
            'client_id' => $user->id,
            'area_id' => 1,
            'street_name' => 'Main Street',
            'building_number' => 1,
            'floor_number' => 1,
            'flat_number' => 1,
            'is_main' => 1,
        ]);

        return ['user' => $user, 'client' => $client, 'address' => $address];
    }

    private function createOrderFor(array $client): Order
    {
        return Order::create([
            'user_id' => $client['user']->id,
            'pharmacy_id' => null,
            'doctor_id' => null,
            'delivering_address_id' => $client['address']->id,
            'is_insured' => 1,
            'status' => 'New',
            'creator_type' => 'client',
            'price' => 0,
        ]);
    }

    private function storePrescriptionFor(Order $order): Prescription
    {
        $name = UploadedFile::fake()->image('scan.jpg')->hashName();
        Storage::disk('prescriptions')->put($name, 'prescription-bytes');

        return Prescription::create([
            'order_id' => $order->id,
            'image' => $name,
        ]);
    }

    private function createSchema(): void
    {
        $this->dropSchema();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('avatar_image')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street_name');
            $table->integer('building_number');
            $table->integer('floor_number');
            $table->integer('flat_number');
            $table->boolean('is_main');
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('client_id');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('delivering_address_id')->nullable();
            $table->string('status');
            $table->boolean('is_insured');
            $table->string('creator_type');
            $table->double('price');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->integer('quantity');
            $table->double('price');
            $table->timestamps();
        });

        Schema::create('orders_medicines', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
            $table->primary(['order_id', 'medicine_id']);
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        foreach (['prescriptions', 'orders_medicines', 'medicines', 'orders', 'addresses', 'clients', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
