<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client', 'web');
    }

    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nour Hassan',
            'email' => 'nour@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'id' => '29001011234567',
            'gender' => 'Female',
            'date_of_birth' => '1990-01-01',
            'phone' => '01012345678',
        ], $overrides);
    }

    public function test_register_creates_a_user_and_client_and_assigns_the_client_role()
    {
        Event::fake([Registered::class]);

        $this->postJson(route('auth.register'), $this->registrationPayload())
            ->assertOk()
            ->assertJsonPath('message', 'Client added successfully')
            ->assertJsonPath('data.id', 29001011234567)
            ->assertJsonPath('data.name', 'Nour Hassan')
            ->assertJsonPath('data.email', 'nour@example.com');

        $this->assertDatabaseHas('users', ['email' => 'nour@example.com']);
        $this->assertDatabaseHas('clients', ['id' => '29001011234567', 'gender' => 'Female']);

        $user = User::where('email', 'nour@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
        $this->assertTrue(Hash::check('secret123', $user->password));

        Event::assertDispatched(Registered::class);
    }

    public function test_register_rejects_an_invalid_national_id_and_creates_nothing()
    {
        $this->postJson(route('auth.register'), $this->registrationPayload(['id' => '123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('id');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_register_rejects_a_duplicate_email()
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(route('auth.register'), $this->registrationPayload(['email' => 'taken@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_get_token_returns_a_token_for_a_client_and_records_the_login()
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
            'last_login' => null,
        ]);
        $user->assignRole('client');

        $response = $this->postJson(route('auth.getToken'), [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ])->assertOk();

        $this->assertNotEmpty($response->getContent());
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'phpunit',
        ]);
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_get_token_rejects_wrong_credentials()
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $user->assignRole('client');

        $this->postJson(route('auth.getToken'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_resend_rejects_an_already_verified_client()
    {
        $client = Client::factory()->create();

        $this->getJson("/api/email/resend/{$client->id}")
            ->assertStatus(422)
            ->assertExactJson(['User already have verified email!']);
    }

    public function test_resend_sends_the_verification_notification_to_an_unverified_client()
    {
        $client = Client::factory()->create([
            'user_id' => User::factory()->unverified(),
        ]);

        $this->getJson("/api/email/resend/{$client->id}")
            ->assertOk()
            ->assertExactJson(['The email verification has been resubmitted']);
    }
}
