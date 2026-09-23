<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shipped migrations cannot be replayed from scratch (clients declares a
        // foreign key on areas before that table exists), so build the two tables this
        // test needs directly.
        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('id');
            $table->primary('id');
            $table->unsignedBigInteger('user_id');
            $table->enum('gender', ['Male', 'Female']);
            $table->date('date_of_birth');
            $table->string('avatar_image');
            $table->string('phone');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('clients');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_client_cannot_read_another_clients_record(): void
    {
        [$attacker] = $this->createClient(10000000000001, 'attacker@example.com');
        [, $victimClient] = $this->createClient(10000000000002, 'victim@example.com');

        Sanctum::actingAs($attacker);

        $response = $this->getJson("/api/client/{$victimClient->id}");

        $response->assertStatus(403);
        $response->assertJsonMissingPath('data');
    }

    public function test_client_cannot_update_another_clients_record(): void
    {
        [$attacker] = $this->createClient(10000000000003, 'attacker2@example.com');
        [$victim, $victimClient] = $this->createClient(10000000000004, 'victim2@example.com');

        Sanctum::actingAs($attacker);

        $response = $this->putJson("/api/client/{$victimClient->id}", [
            'name' => 'Hacked Name',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'phone' => '01112223334',
        ]);

        $response->assertStatus(403);
        $this->assertSame($victim->name, $victim->fresh()->name);
        $this->assertSame($victimClient->phone, $victimClient->fresh()->phone);
    }

    public function test_client_can_read_and_update_own_record(): void
    {
        [$user, $client] = $this->createClient(10000000000005, 'owner@example.com');

        Sanctum::actingAs($user);

        $this->getJson("/api/client/{$client->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $client->id);

        $this->putJson("/api/client/{$client->id}", [
            'name' => 'New Name',
            'gender' => 'Female',
            'date_of_birth' => '1991-02-03',
            'phone' => '01112223335',
        ])->assertStatus(200);

        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('01112223335', $client->fresh()->phone);
    }

    /**
     * @return array{0: User, 1: Client}
     */
    private function createClient(int $nationalId, string $email): array
    {
        $user = User::create([
            'name' => 'User ' . $nationalId,
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Client::create([
            'id' => $nationalId,
            'user_id' => $user->id,
            'avatar_image' => 'default.jpg',
            'gender' => 'Male',
            'date_of_birth' => '1990-05-05',
            'phone' => '01000000000',
        ]);

        // The clients table has no auto-increment key, so re-read the row to get a
        // model whose key is the national id.
        return [$user, Client::find($nationalId)];
    }
}
