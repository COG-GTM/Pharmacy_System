<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_client_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'id' => '12345678901234',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['email' => 'client@test.com']);
        $this->assertDatabaseHas('clients', ['id' => '12345678901234']);
    }

    public function test_register_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Test Client',
            'email' => 'existing@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'id' => '12345678901234',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ]);

        $response->assertStatus(422);
    }

    public function test_client_can_login()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('client');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(200);
    }

    public function test_login_fails_with_wrong_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('client');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_non_client_role()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertStatus(422);
    }

    public function test_email_verification_works()
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('client');

        $this->assertNull($user->email_verified_at);

        $user->markEmailAsVerified();
        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_resend_works()
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('client');
        $client = Client::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/email/resend/{$client->id}");

        $response->assertStatus(200);
    }
}
