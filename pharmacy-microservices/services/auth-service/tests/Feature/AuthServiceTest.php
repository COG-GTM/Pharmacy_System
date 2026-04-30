<?php

namespace Tests\Feature;

use App\Models\User;
use Cog\Laravel\Ban\Models\Ban;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_register_creates_user_and_client_role()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test Client',
            'email' => 'client@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'client@test.com']);
        $user = User::where('email', 'client@test.com')->first();
        $this->assertTrue($user->hasRole('client'));
    }

    public function test_login_returns_token()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->assignRole('client');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'test',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);
    }

    public function test_token_validation_endpoint()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['email' => $user->email]);
    }

    public function test_create_user_internal_endpoint()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New Pharmacy User',
            'email' => 'pharmacy@test.com',
            'password' => 'password',
            'role' => 'pharmacy',
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'pharmacy@test.com')->first();
        $this->assertTrue($user->hasRole('pharmacy'));
    }

    public function test_ban_user()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $response = $this->actingAs($admin)->postJson("/api/users/{$doctor->id}/ban", [
            'comment' => 'Test ban',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($doctor->fresh()->isBanned());
    }

    public function test_unban_user()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $doctor->ban();

        $response = $this->actingAs($admin)->postJson("/api/users/{$doctor->id}/unban");

        $response->assertStatus(200);
        $this->assertFalse($doctor->fresh()->isBanned());
    }

    public function test_remove_old_bans_job()
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');
        $user->ban(['comment' => 'Old ban']);

        $ban = Ban::first();
        $ban->created_at = now()->subDays(31);
        $ban->save();

        (new \App\Jobs\RemoveOldBans())->handle();

        $this->assertFalse($user->fresh()->isBanned());
    }
}
