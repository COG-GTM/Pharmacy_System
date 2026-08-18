<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected()
    {
        $client = Client::factory()->create();

        $this->getJson("/api/client/{$client->id}")->assertUnauthorized();
    }

    public function test_index_returns_the_client_resource()
    {
        $client = Client::factory()->create(['gender' => 'Male']);
        Sanctum::actingAs($client->user);

        $this->getJson("/api/client/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', $client->user->name)
            ->assertJsonPath('data.email', $client->user->email)
            ->assertJsonPath('data.gender', 'Male');
    }

    public function test_update_changes_the_client_and_the_related_user_name()
    {
        $client = Client::factory()->create(['gender' => 'Male']);
        Sanctum::actingAs($client->user);

        $this->putJson("/api/client/{$client->id}", [
            'name' => 'Updated Name',
            'gender' => 'Female',
            'date_of_birth' => '1995-05-05',
            'phone' => '01112345678',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Client updated successfully')
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', ['id' => $client->user_id, 'name' => 'Updated Name']);
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'gender' => 'Female',
            'phone' => '01112345678',
        ]);
    }

    public function test_update_rejects_attempts_to_change_the_national_id_or_email()
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $this->putJson("/api/client/{$client->id}", [
            'id' => '29001011234567',
            'email' => 'new@example.com',
            'name' => 'Updated Name',
            'gender' => 'Male',
            'date_of_birth' => '1995-05-05',
            'phone' => '01112345678',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id', 'email']);
    }

    public function test_update_rejects_an_invalid_phone_number()
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client->user);

        $this->putJson("/api/client/{$client->id}", [
            'name' => 'Updated Name',
            'gender' => 'Male',
            'date_of_birth' => '1995-05-05',
            'phone' => '12345',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }
}
