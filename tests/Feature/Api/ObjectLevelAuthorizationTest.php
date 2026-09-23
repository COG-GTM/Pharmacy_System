<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Order;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObjectLevelAuthorizationTest extends TestCase
{
    /**
     * The legacy migrations cannot be replayed on a fresh database (their
     * filenames order child tables before their parents and one foreign key
     * does not match the type of the key it references), so these tests build
     * the handful of tables the client and order endpoints touch. Because that
     * is destructive, it only runs against a database whose name marks it as a
     * test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        if (!preg_match('/test/i', $database)) {
            $this->markTestSkipped(
                'Refusing to rebuild tables in "' . $database . '": point DB_DATABASE at a test database.'
            );
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('orders_medicines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('avatar_image');
            $table->string('phone');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('delivering_address_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->boolean('is_insured');
            $table->string('status');
            $table->string('creator_type');
            $table->double('price');
            $table->timestamps();
        });

        Schema::create('orders_medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->integer('quantity');
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
        });
    }

    private function createClient($nationalId, $name)
    {
        $user = User::factory()->create([
            'name' => $name,
            'email_verified_at' => now(),
        ]);

        Client::create([
            'id' => $nationalId,
            'user_id' => $user->id,
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'avatar_image' => 'default.jpg',
            'phone' => '01012345678',
        ]);

        // The clients table is keyed by the national id, which Eloquent
        // overwrites on the created instance with the auto-increment value.
        return [$user, Client::findOrFail($nationalId)];
    }

    private function createOrder(User $user)
    {
        $order = Order::create([
            'user_id' => $user->id,
            'doctor_id' => null,
            'delivering_address_id' => null,
            'pharmacy_id' => null,
            'is_insured' => false,
            'status' => 'New',
            'creator_type' => 'client',
            'price' => 0,
        ]);

        Prescription::create([
            'order_id' => $order->id,
            'image' => 'image-victim-prescription.png',
        ]);

        return $order;
    }

    public function test_client_cannot_read_another_clients_record()
    {
        [$attacker] = $this->createClient(10000000000001, 'Attacker');
        [, $victimClient] = $this->createClient(10000000000002, 'Victim');

        Sanctum::actingAs($attacker);

        $response = $this->getJson('/api/client/' . $victimClient->id);

        $response->assertStatus(403);
        $response->assertJsonMissing(['phone' => $victimClient->phone]);
    }

    public function test_client_cannot_update_another_clients_record()
    {
        [$attacker] = $this->createClient(10000000000001, 'Attacker');
        [$victim, $victimClient] = $this->createClient(10000000000002, 'Victim');

        Sanctum::actingAs($attacker);

        $response = $this->putJson('/api/client/' . $victimClient->id, [
            'name' => 'Hacked Name',
            'gender' => 'Female',
            'date_of_birth' => '1970-01-01',
            'phone' => '01112345678',
        ]);

        $response->assertStatus(403);
        $this->assertSame('Victim', $victim->fresh()->name);
        $this->assertSame('01012345678', $victimClient->fresh()->phone);
    }

    public function test_client_can_read_and_update_own_record()
    {
        [$user, $client] = $this->createClient(10000000000001, 'Owner');

        Sanctum::actingAs($user);

        $this->getJson('/api/client/' . $client->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $client->id);

        $this->putJson('/api/client/' . $client->id, [
            'name' => 'Owner Renamed',
            'gender' => 'Female',
            'date_of_birth' => '1991-02-03',
            'phone' => '01112345678',
        ])->assertStatus(200);

        $this->assertSame('Owner Renamed', $user->fresh()->name);
        $this->assertSame('01112345678', $client->fresh()->phone);
    }

    public function test_client_cannot_read_another_clients_order_and_prescriptions()
    {
        [$attacker] = $this->createClient(10000000000001, 'Attacker');
        [$victim] = $this->createClient(10000000000002, 'Victim');
        $victimOrder = $this->createOrder($victim);

        Sanctum::actingAs($attacker);

        $response = $this->getJson('/api/orders/' . $victimOrder->id);

        $response->assertStatus(403);
        $response->assertJsonMissing(['image' => 'image-victim-prescription.png']);
    }

    public function test_client_cannot_destroy_another_clients_prescriptions()
    {
        [$attacker] = $this->createClient(10000000000001, 'Attacker');
        [$victim] = $this->createClient(10000000000002, 'Victim');
        $victimOrder = $this->createOrder($victim);

        Sanctum::actingAs($attacker);

        $response = $this->putJson('/api/orders/' . $victimOrder->id, []);

        $response->assertStatus(403);
        $this->assertDatabaseHas('prescriptions', [
            'order_id' => $victimOrder->id,
            'image' => 'image-victim-prescription.png',
        ]);
    }

    public function test_client_can_replace_prescriptions_on_own_order()
    {
        Storage::fake();

        [$user] = $this->createClient(10000000000001, 'Owner');
        $order = $this->createOrder($user);

        Sanctum::actingAs($user);

        $this->putJson('/api/orders/' . $order->id, [
            'prescriptions' => [UploadedFile::fake()->image('owner-prescription.png')],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('prescriptions', [
            'order_id' => $order->id,
            'image' => 'image-victim-prescription.png',
        ]);
        $this->assertDatabaseHas('prescriptions', [
            'order_id' => $order->id,
            'image' => 'image-owner-prescription.png',
        ]);
    }

    public function test_client_can_read_own_order()
    {
        [$user] = $this->createClient(10000000000001, 'Owner');
        $order = $this->createOrder($user);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders/' . $order->id)
            ->assertStatus(200)
            ->assertJsonPath('prescriptions.0.image', 'image-victim-prescription.png');
    }
}
